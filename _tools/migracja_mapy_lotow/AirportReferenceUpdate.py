#!/usr/bin/env python3
"""Powtarzalna aktualizacja slownika lotnisk z migawki OurAirports."""

from __future__ import annotations

import csv
import datetime as dt
from collections import defaultdict
from pathlib import Path


SOURCE_NAME = "OurAirports"
NEW_ID_OFFSET = 1_000_000


def _clean(value: str | None) -> str:
    value = (value or "").strip()
    return "" if value in {"\\N", "NULL"} else value


def _code(value: str | None) -> str:
    return _clean(value).upper()


def _sql(value: str | None) -> str:
    if value is None:
        return "NULL"
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def _preferred_icao(row: dict[str, str]) -> str:
    direct = _code(row.get("icao_code"))
    if direct:
        return direct
    gps = _code(row.get("gps_code"))
    if len(gps) == 4 and gps.isalpha():
        return gps
    ident = _code(row.get("ident"))
    if len(ident) == 4 and ident.isalpha():
        return ident
    return ""


def _unique_index(rows: list[dict[str, str]], field) -> dict[str, dict[str, str]]:
    grouped: dict[str, list[dict[str, str]]] = defaultdict(list)
    for row in rows:
        key = field(row) if callable(field) else _code(row.get(field))
        if key:
            grouped[key].append(row)
    return {key: values[0] for key, values in grouped.items() if len(values) == 1}


def update_airport_reference(mysql, database: str, csv_path: Path, temp_dir: Path,
                             snapshot_date: str, log=None) -> dict:
    """Aktualizuje istniejace rekordy i dodaje lotniska pasazerskie.

    Istniejace ID sa zawsze zachowywane. Nowe ID sa deterministyczne i maja
    postac 1000000 + stabilne ID rekordu OurAirports.
    """
    if not csv_path.exists():
        raise RuntimeError(f"Brak migawki lotnisk: {csv_path}")

    with csv_path.open("r", encoding="utf-8-sig", newline="") as source:
        source_rows = list(csv.DictReader(source))

    by_icao = _unique_index(source_rows, _preferred_icao)
    by_iata = _unique_index(source_rows, "iata_code")

    country_rows = mysql.query(
        "SELECT id, COALESCE(iso2,''), name FROM ml_countries ORDER BY id", database
    ).splitlines()
    countries = {}
    for line in country_rows:
        country_id, iso2, name = line.split("\t", 2)
        if iso2:
            countries[iso2.upper()] = {"id": int(country_id), "name": name}

    target_lines = mysql.query(
        """
        SELECT id, name, city, COALESCE(country_code,''), COALESCE(iata_code,''),
               COALESCE(icao_code,''), latitude, longitude, COALESCE(altitude_ft,''), is_active
        FROM ml_airports ORDER BY id
        """, database
    ).splitlines()

    targets = []
    target_iata: dict[str, list[int]] = defaultdict(list)
    target_icao: dict[str, list[int]] = defaultdict(list)
    for line in target_lines:
        p = line.split("\t")
        row = {
            "id": int(p[0]), "name": p[1], "city": p[2], "country_code": p[3].upper(),
            "iata": p[4].upper(), "icao": p[5].upper(), "latitude": p[6],
            "longitude": p[7], "altitude": p[8], "is_active": int(p[9]),
        }
        targets.append(row)
        if row["iata"]:
            target_iata[row["iata"]].append(row["id"])
        if row["icao"]:
            target_icao[row["icao"]].append(row["id"])

    statements = [
        "SET NAMES utf8mb4;",
        "START TRANSACTION;",
        "DELETE FROM ml_airport_reference_links WHERE source_name='OurAirports';",
    ]
    matched_source_ids: set[int] = set()
    claimed_source_ids: set[int] = set()
    matched = updated = unchanged = 0
    conflicts = []
    update_samples = []

    for target in targets:
        # Kod musi byc jednoznaczny nie tylko w nowym zrodle, ale rowniez
        # w starej bazie. Zapobiega to przypisaniu jednego rekordu zrodla
        # do kilku historycznych lotnisk majacych ten sam kod.
        icao_match = (
            by_icao.get(target["icao"])
            if target["icao"] and len(target_icao[target["icao"]]) == 1 else None
        )
        iata_match = (
            by_iata.get(target["iata"])
            if target["iata"] and len(target_iata[target["iata"]]) == 1 else None
        )
        if icao_match and iata_match and icao_match["id"] != iata_match["id"]:
            conflicts.append({
                "airport_id": target["id"], "reason": "ICAO and IATA point to different source rows",
                "icao": target["icao"], "iata": target["iata"],
            })
            continue
        source_row = icao_match or iata_match
        if not source_row:
            continue

        source_id = int(source_row["id"])
        if source_id in claimed_source_ids:
            conflicts.append({
                "airport_id": target["id"], "source_id": source_id,
                "reason": "source row already assigned to another legacy airport",
                "icao": target["icao"] or None, "iata": target["iata"] or None,
            })
            continue
        claimed_source_ids.add(source_id)
        matched += 1
        matched_source_ids.add(source_id)
        source_icao = _preferred_icao(source_row) or None
        source_iata = _code(source_row.get("iata_code")) or None
        lat = _clean(source_row.get("latitude_deg"))
        lon = _clean(source_row.get("longitude_deg"))
        altitude = _clean(source_row.get("elevation_ft"))
        name = _clean(source_row.get("name")) or target["name"]
        city = _clean(source_row.get("municipality")) or target["city"]
        iso2 = _code(source_row.get("iso_country"))
        is_active = 0 if _clean(source_row.get("type")) == "closed" else 1

        changed = (
            name != target["name"] or city != target["city"] or
            (source_iata or "") != target["iata"] or (source_icao or "") != target["icao"] or
            (lat and float(lat) != float(target["latitude"])) or
            (lon and float(lon) != float(target["longitude"])) or
            (altitude or "") != target["altitude"] or is_active != target["is_active"] or
            (iso2 and iso2 != target["country_code"])
        )

        sets = [
            f"name={_sql(name)}", f"city={_sql(city)}",
            f"iata_code={_sql(source_iata)}", f"icao_code={_sql(source_icao)}",
            f"is_active={is_active}", "updated_at=CURRENT_TIMESTAMP",
        ]
        if lat and lon:
            sets.extend([f"latitude={lat}", f"longitude={lon}"])
        sets.append(f"altitude_ft={altitude}" if altitude else "altitude_ft=NULL")
        if iso2 in countries:
            country = countries[iso2]
            sets.extend([
                f"country_code={_sql(iso2)}", f"country_id={country['id']}",
                f"country_name={_sql(country['name'])}",
            ])
        if changed:
            statements.append(f"UPDATE ml_airports SET {', '.join(sets)} WHERE id={target['id']};")
            updated += 1
            if len(update_samples) < 200:
                update_samples.append({
                    "airport_id": target["id"], "old_name": target["name"], "new_name": name,
                    "iata": source_iata, "icao": source_icao,
                })
        else:
            unchanged += 1

        statements.append(
            "INSERT INTO ml_airport_reference_links "
            "(airport_id,source_name,source_id,source_ident,source_type,scheduled_service,snapshot_date) VALUES "
            f"({target['id']},'OurAirports',{source_id},{_sql(_code(source_row.get('ident')) or None)},"
            f"{_sql(_clean(source_row.get('type')) or None)},{1 if source_row.get('scheduled_service') == 'yes' else 0},"
            f"{_sql(snapshot_date)});"
        )

    eligible = [
        row for row in source_rows
        if _clean(row.get("type")) != "closed" and (
            _clean(row.get("type")) in {"large_airport", "medium_airport"}
            or _clean(row.get("scheduled_service")) == "yes"
            or bool(_code(row.get("iata_code")))
        )
    ]
    added = skipped_country = skipped_collision = 0
    added_samples = []
    used_new_ids = {target["id"] for target in targets}

    for row in sorted(eligible, key=lambda item: int(item["id"])):
        source_id = int(row["id"])
        if source_id in matched_source_ids:
            continue
        iata = _code(row.get("iata_code"))
        icao = _preferred_icao(row)
        if (iata and iata in target_iata) or (icao and icao in target_icao):
            skipped_collision += 1
            if len(conflicts) < 300:
                conflicts.append({
                    "source_id": source_id, "reason": "code already used by unmatched legacy row",
                    "iata": iata or None, "icao": icao or None,
                })
            continue
        iso2 = _code(row.get("iso_country"))
        country = countries.get(iso2)
        if not country:
            skipped_country += 1
            continue
        airport_id = NEW_ID_OFFSET + source_id
        if airport_id in used_new_ids:
            skipped_collision += 1
            conflicts.append({"source_id": source_id, "reason": "deterministic ID collision", "airport_id": airport_id})
            continue
        lat, lon = _clean(row.get("latitude_deg")), _clean(row.get("longitude_deg"))
        name = _clean(row.get("name"))
        city = _clean(row.get("municipality")) or name
        altitude = _clean(row.get("elevation_ft"))
        if not (name and lat and lon):
            continue
        statements.append(
            "INSERT INTO ml_airports "
            "(id,name,city,country_name,country_code,country_id,iata_code,icao_code,latitude,longitude,altitude_ft,is_active) VALUES "
            f"({airport_id},{_sql(name)},{_sql(city)},{_sql(country['name'])},{_sql(iso2)},{country['id']},"
            f"{_sql(iata or None)},{_sql(icao or None)},{lat},{lon},{altitude if altitude else 'NULL'},1);"
        )
        statements.append(
            "INSERT INTO ml_airport_reference_links "
            "(airport_id,source_name,source_id,source_ident,source_type,scheduled_service,snapshot_date) VALUES "
            f"({airport_id},'OurAirports',{source_id},{_sql(_code(row.get('ident')) or None)},"
            f"{_sql(_clean(row.get('type')) or None)},{1 if row.get('scheduled_service') == 'yes' else 0},"
            f"{_sql(snapshot_date)});"
        )
        used_new_ids.add(airport_id)
        added += 1
        if len(added_samples) < 200:
            added_samples.append({"airport_id": airport_id, "name": name, "iata": iata or None, "icao": icao or None})

    statements.append("COMMIT;")
    temp_dir.mkdir(parents=True, exist_ok=True)
    sql_path = temp_dir / "airport_reference_update.sql"
    sql_path.write_text("\n".join(statements) + "\n", encoding="utf-8", newline="\n")
    mysql.run_file(sql_path, database, log)

    return {
        "source": SOURCE_NAME,
        "snapshot_date": snapshot_date,
        "source_rows": len(source_rows),
        "eligible_active_passenger_airports": len(eligible),
        "legacy_airports": len(targets),
        "matched": matched,
        "updated": updated,
        "unchanged": unchanged,
        "added": added,
        "skipped_without_country_mapping": skipped_country,
        "skipped_code_or_id_collisions": skipped_collision,
        "conflict_count": len(conflicts),
        "conflicts": conflicts[:300],
        "update_samples": update_samples,
        "added_samples": added_samples,
        "new_id_policy": "1000000 + OurAirports source id",
        "executed_at": dt.datetime.now().isoformat(),
    }
