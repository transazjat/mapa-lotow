#!/usr/bin/env python3
"""Rozstrzyga zweryfikowane konflikty starych lotnisk z OurAirports."""

from __future__ import annotations

import csv
from pathlib import Path


# legacy_id, OurAirports source_id, data przejscia (None = wszystkie loty),
# historyczna nazwa, IATA, ICAO
RULES = (
    (2241, 44686, "2014-05-27", "Doha International Airport (historical)", "DIA", "OTBD"),
    (1701, 317457, "2019-04-06", "Ataturk Airport (historical)", "ISL", "LTBA"),
    (6815, 301881, "2020-11-09", "Berlin metropolitan code (historical)", None, None),
    (4168, 26705, "2012-12-02", "Duong Dong Airport (historical)", None, None),
    (3141, 35141, None, "Begumpet Airport (historical)", "BPM", "VOHY"),
    (2223, 333692, None, "PAF Base Nur Khan (historical Islamabad airport)", None, "OPRN"),
    (2767, 333229, None, "Juana Azurduy de Padilla Airport (historical)", None, "SLSU"),
    (6011, 5709, None, "Rajah Buayan Air Base (historical General Santos airport)", None, "RPMB"),
    (3278, 26858, None, "Tabing Airport (historical)", None, None),
    (1064, 3101, None, "Inezgane Airport (legacy duplicate)", None, None),
    (4053, 4226, None, "EuroAirport Basel legacy duplicate", None, None),
    (4330, 5166, None, "Imam Khomeini legacy duplicate", None, None),
    (4002, 5736, None, "Godofredo P. Ramos legacy duplicate", None, None),
    (6431, 27204, None, "Lanzhou legacy duplicate", None, None),
    (4082, 26702, None, "Phu Bai legacy duplicate", None, None),
    (3245, 26756, None, "Wamena legacy duplicate", None, None),
    (4150, 5156, None, "Isfahan legacy duplicate", None, None),
    (5852, 4831, None, "Jardines del Rey legacy duplicate", None, None),
    (6068, 6242, None, "Maria Reiche Neuman legacy duplicate", None, None),
    (7529, 5941, None, "Ponta Pelada legacy duplicate", None, None),
    (4001, 26712, None, "Bagan Nyaung U legacy duplicate", None, None),
)

RETAINED = (2400, 308633, "Lumbia Airport (historical Cagayan de Oro airport)", None, "RPML")
DIRECT_CANONICAL = (2614, 5969)  # Lot 4480 IGU-Rio potwierdza SNZ/SBSC.


def _sql(value: str | None) -> str:
    if value is None:
        return "NULL"
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def resolve_airport_conflicts(mysql, database: str, csv_path: Path, temp_dir: Path,
                              snapshot_date: str, log=None) -> dict:
    """Przenosi odwolania lotow do kanonicznych rekordow, zachowujac historie."""
    source_ids = [rule[1] for rule in RULES] + [RETAINED[1], DIRECT_CANONICAL[1]]
    source_list = ",".join(str(value) for value in source_ids)
    rows = mysql.query(
        "SELECT source_id,airport_id FROM ml_airport_reference_links "
        f"WHERE source_name='OurAirports' AND source_id IN ({source_list})",
        database,
    ).splitlines()
    targets = {int(line.split("\t")[0]): int(line.split("\t")[1]) for line in rows if line}
    # Ten rekord zachowuje swoje historyczne ID, bo lot 4480 potwierdzil,
    # ze ICAO SBSC bylo poprawne, a bledny byl jedynie kod IATA STU.
    targets[DIRECT_CANONICAL[1]] = DIRECT_CANONICAL[0]
    missing = sorted(set(source_ids) - set(targets))
    with csv_path.open("r", encoding="utf-8-sig", newline="") as source:
        source_rows = {int(row["id"]): row for row in csv.DictReader(source)}
    country_rows = mysql.query(
        "SELECT id,UPPER(COALESCE(iso2,'')),name FROM ml_countries", database
    ).splitlines()
    countries = {parts[1]: (int(parts[0]), parts[2]) for line in country_rows
                 if (parts := line.split("\t", 2))[1]}
    unresolved = [source_id for source_id in missing
                  if source_id not in source_rows or source_rows[source_id].get("iso_country", "").upper() not in countries]
    if unresolved:
        return {"status": "FAIL", "rules_total": len(RULES) + 1, "rules_applied": 0,
                "target_source_ids_missing": unresolved}
    for source_id in missing:
        targets[source_id] = 1_000_000 + source_id

    statements = [
        "SET NAMES utf8mb4;", "START TRANSACTION;",
        "CREATE TABLE IF NOT EXISTS ml_airport_resolution_log ("
        "legacy_airport_id INT UNSIGNED NOT NULL PRIMARY KEY,"
        "target_airport_id INT UNSIGNED NULL,rule_kind VARCHAR(30) NOT NULL,"
        "cutover_date DATE NULL,departure_refs_moved INT UNSIGNED NOT NULL DEFAULT 0,"
        "arrival_refs_moved INT UNSIGNED NOT NULL DEFAULT 0,notes VARCHAR(255) NULL,"
        "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;",
        "DELETE FROM ml_airport_resolution_log;",
        "SET @moved_departures=0;", "SET @moved_arrivals=0;",
    ]

    for source_id in missing:
        row = source_rows[source_id]
        iso2 = row["iso_country"].upper()
        country_id, country_name = countries[iso2]
        iata = (row.get("iata_code") or "").strip().upper() or None
        icao = (row.get("icao_code") or row.get("gps_code") or "").strip().upper() or None
        ident = (row.get("ident") or "").strip().upper() or None
        altitude = (row.get("elevation_ft") or "").strip() or None
        target_id = targets[source_id]
        statements.extend([
            "INSERT INTO ml_airports "
            "(id,name,city,country_name,country_code,country_id,iata_code,icao_code,latitude,longitude,altitude_ft,is_active) VALUES "
            f"({target_id},{_sql(row['name'].strip())},{_sql((row.get('municipality') or row['name']).strip())},"
            f"{_sql(country_name)},{_sql(iso2)},{country_id},{_sql(iata)},{_sql(icao)},"
            f"{row['latitude_deg']},{row['longitude_deg']},{altitude or 'NULL'},1);",
            "INSERT INTO ml_airport_reference_links "
            "(airport_id,source_name,source_id,source_ident,source_type,scheduled_service,snapshot_date) VALUES "
            f"({target_id},'OurAirports',{source_id},{_sql(ident)},{_sql(row.get('type') or None)},"
            f"{1 if row.get('scheduled_service') == 'yes' else 0},{_sql(snapshot_date)});",
        ])

    for legacy_id, source_id, cutoff, old_name, old_iata, old_icao in RULES:
        target_id = targets[source_id]
        dep_condition = f" AND departure_date >= '{cutoff}'" if cutoff else ""
        arr_condition = f" AND COALESCE(arrival_date,departure_date) >= '{cutoff}'" if cutoff else ""
        kind = "cutover_by_date" if cutoff else "canonical_all"
        statements.extend([
            f"UPDATE ml_flights SET departure_airport_id={target_id} "
            f"WHERE departure_airport_id={legacy_id}{dep_condition};",
            "SET @dep=ROW_COUNT(); SET @moved_departures=@moved_departures+@dep;",
            f"UPDATE ml_flights SET arrival_airport_id={target_id} "
            f"WHERE arrival_airport_id={legacy_id}{arr_condition};",
            "SET @arr=ROW_COUNT(); SET @moved_arrivals=@moved_arrivals+@arr;",
            f"UPDATE ml_airports SET name={_sql(old_name)},iata_code={_sql(old_iata)},"
            f"icao_code={_sql(old_icao)},is_active=0,updated_at=CURRENT_TIMESTAMP WHERE id={legacy_id};",
            "INSERT INTO ml_airport_resolution_log "
            "(legacy_airport_id,target_airport_id,rule_kind,cutover_date,departure_refs_moved,arrival_refs_moved,notes) VALUES "
            f"({legacy_id},{target_id},'{kind}',{_sql(cutoff)},@dep,@arr,'OurAirports source {source_id}');",
        ])

    legacy_id, source_id, old_name, old_iata, old_icao = RETAINED
    direct_id, direct_source_id = DIRECT_CANONICAL
    direct = source_rows[direct_source_id]
    statements.extend([
        f"UPDATE ml_airports SET name={_sql(old_name)},iata_code={_sql(old_iata)},"
        f"icao_code={_sql(old_icao)},is_active=0,updated_at=CURRENT_TIMESTAMP WHERE id={legacy_id};",
        "INSERT INTO ml_airport_resolution_log "
        "(legacy_airport_id,target_airport_id,rule_kind,notes) VALUES "
        f"({legacy_id},{targets[source_id]},'historical_retained','Lot z 2012 pozostaje w Lumbia; source {source_id} jest nowym Laguindingan');",
        f"UPDATE ml_airports SET name='Santa Cruz Air Force Base',city='Rio de Janeiro',"
        f"iata_code='SNZ',icao_code='SBSC',latitude={direct['latitude_deg']},"
        f"longitude={direct['longitude_deg']},altitude_ft={direct['elevation_ft']},"
        "is_active=1,updated_at=CURRENT_TIMESTAMP WHERE id=2614;",
        "INSERT INTO ml_airport_reference_links "
        "(airport_id,source_name,source_id,source_ident,source_type,scheduled_service,snapshot_date) VALUES "
        f"(2614,'OurAirports',5969,'SBSC',{_sql(direct.get('type') or None)},0,{_sql(snapshot_date)});",
        "INSERT INTO ml_airport_resolution_log "
        "(legacy_airport_id,target_airport_id,rule_kind,notes) VALUES "
        "(2614,2614,'direct_correction','Lot 4480 IGU-Rio: STU corrected to SNZ; ICAO SBSC confirmed');",
        "COMMIT;",
    ])

    temp_dir.mkdir(parents=True, exist_ok=True)
    path = temp_dir / "airport_conflict_resolution.sql"
    path.write_text("\n".join(statements) + "\n", encoding="utf-8", newline="\n")
    mysql.run_file(path, database, log)

    totals = mysql.query(
        "SELECT COALESCE(SUM(departure_refs_moved),0),COALESCE(SUM(arrival_refs_moved),0) "
        "FROM ml_airport_resolution_log", database,
    ).split("\t")
    return {
        "status": "PASS",
        "rules_total": len(RULES) + 2,
        "rules_applied": len(RULES),
        "canonical_airports_added_for_resolution": len(missing),
        "historical_records_retained": 1,
        "direct_corrections": 1,
        "departure_references_moved": int(totals[0]),
        "arrival_references_moved": int(totals[1]),
        "target_source_ids_missing": [],
        "manual_review": None,
    }
