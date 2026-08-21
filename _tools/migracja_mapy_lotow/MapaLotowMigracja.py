#!/usr/bin/env python3
"""Powtarzalna migracja Mapy Lotow: BUILD kandydata i kontrolowana PROMOCJA."""

from __future__ import annotations

import argparse
import datetime as dt
import getpass
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
from collections import Counter
from pathlib import Path

from AircraftTypeReferenceUpdate import update_aircraft_types
from AirlineReferenceUpdate import update_airlines
from AirportConflictResolution import resolve_airport_conflicts
from AirportReferenceUpdate import update_airport_reference


ROOT = Path(__file__).resolve().parent
CONFIG_PATH = ROOT / "config.json"
REPORTS = ROOT / "reports"
BACKUPS = ROOT / "backup"
TEMP = ROOT / "temp"
LEGACY = ROOT / "sql" / "legacy"
EXTENSIONS = ROOT / "sql" / "extensions"
REFERENCE_DATA = ROOT / "reference_data"


class MigrationError(RuntimeError):
    pass


def stamp() -> str:
    return dt.datetime.now().strftime("%Y-%m-%d_%H%M%S")


def load_config() -> dict:
    if not CONFIG_PATH.exists():
        raise MigrationError(f"Brak pliku konfiguracji: {CONFIG_PATH}")
    return json.loads(CONFIG_PATH.read_text(encoding="utf-8"))


def executable(config_value: str, program: str) -> str:
    if config_value:
        path = Path(config_value)
        if path.exists():
            return str(path)
        raise MigrationError(f"Nie znaleziono programu: {path}")
    found = shutil.which(program) or shutil.which(program + ".exe")
    if found:
        return found
    patterns = [
        f"C:/laragon/bin/mysql/mysql-*/bin/{program}.exe",
        f"D:/laragon/bin/mysql/mysql-*/bin/{program}.exe",
    ]
    candidates = []
    for pattern in patterns:
        candidates.extend(Path(pattern.split("*")[0]).parent.glob(Path(pattern).name) if "*" not in pattern else [])
    # Globowanie pełnej ścieżki przez pathlib dla typowych instalacji Laragon.
    for drive in ("C:/", "D:/"):
        candidates.extend(Path(drive).glob(f"laragon/bin/mysql/mysql-*/bin/{program}.exe"))
    if candidates:
        return str(sorted(candidates)[-1])
    raise MigrationError(f"Nie znaleziono {program}. Ustaw ścieżkę w config.json.")


class Mysql:
    def __init__(self, cfg: dict):
        self.cfg = cfg
        self.mysql = executable(cfg.get("mysql_executable", ""), "mysql")
        self.mysqldump = executable(cfg.get("mysqldump_executable", ""), "mysqldump")
        password = os.environ.get(cfg.get("password_env", "MAPA_LOTOW_DB_PASSWORD"))
        if password is None and cfg.get("ask_for_password", True):
            password = getpass.getpass("Hasło MySQL (Enter = puste): ")
        self.env = os.environ.copy()
        if password:
            self.env["MYSQL_PWD"] = password

    def common(self) -> list[str]:
        return [
            "--host", str(self.cfg.get("host", "127.0.0.1")),
            "--port", str(self.cfg.get("port", 3306)),
            "--user", str(self.cfg.get("user", "root")),
            "--default-character-set=utf8mb4",
        ]

    def query(self, sql: str, database: str | None = None) -> str:
        cmd = [self.mysql, *self.common(), "--batch", "--raw", "--skip-column-names"]
        if database:
            cmd.extend(["--database", database])
        result = subprocess.run(cmd, input=sql, text=True, encoding="utf-8", env=self.env,
                                capture_output=True)
        if result.returncode:
            raise MigrationError(result.stderr.strip() or "Błąd klienta mysql")
        # Usuwamy wyłącznie końce linii. Końcowy pusty element TSV (np. pusta
        # timezone_name) musi zachować tabulator, aby parser widział wszystkie kolumny.
        return result.stdout.rstrip("\r\n")

    def run_file(self, path: Path, database: str | None = None, log=None) -> None:
        cmd = [self.mysql, *self.common(), "--show-warnings"]
        if database:
            cmd.extend(["--database", database])
        with path.open("r", encoding="utf-8-sig") as source:
            result = subprocess.run(cmd, stdin=source, text=True, encoding="utf-8", env=self.env,
                                    stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
        if log is not None:
            log.write(f"\n===== {path.name} =====\n{result.stdout}\n")
        if result.returncode:
            raise MigrationError(f"Błąd wykonywania {path.name}. Szczegóły w logu.")

    def dump(self, database: str, output: Path) -> None:
        cmd = [
            self.mysqldump, *self.common(), "--single-transaction", "--routines", "--triggers",
            "--events", "--hex-blob", "--set-gtid-purged=OFF", database,
        ]
        with output.open("w", encoding="utf-8", newline="\n") as target:
            result = subprocess.run(cmd, text=True, encoding="utf-8", env=self.env,
                                    stdout=target, stderr=subprocess.PIPE)
        if result.returncode:
            output.unlink(missing_ok=True)
            raise MigrationError(result.stderr.strip() or f"Nie udał się eksport {database}")


def valid_db_name(value: str) -> str:
    if not re.fullmatch(r"[A-Za-z0-9_]+", value):
        raise MigrationError(f"Niedozwolona nazwa bazy: {value}")
    return value


def db_exists(mysql: Mysql, name: str) -> bool:
    escaped = name.replace("'", "''")
    return mysql.query(f"SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='{escaped}'") == "1"


def scalar(mysql: Mysql, sql: str, database: str | None = None) -> int:
    value = mysql.query(sql, database)
    return int(value or 0)


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def sql_quote(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def update_airport_timezones(mysql: Mysql, database: str) -> dict:
    """Wyznacza strefy IANA na podstawie wspolrzednych wszystkich lotnisk."""
    try:
        from timezonefinder import TimezoneFinder
    except ImportError as exc:
        raise MigrationError(
            "Brak biblioteki timezonefinder. Uruchom 00_INSTALUJ_WYMAGANIA.bat."
        ) from exc

    rows = mysql.query(
        "SELECT id, latitude, longitude, COALESCE(timezone_name,'') FROM ml_airports ORDER BY id",
        database,
    ).splitlines()
    finder = TimezoneFinder()
    changes: list[tuple[int, str]] = []
    not_found = []
    unchanged = 0
    invalid_coordinates = []

    for line in rows:
        parts = line.split("\t")
        if len(parts) != 4:
            raise MigrationError("Nieoczekiwany format danych lotniska podczas ustalania stref")
        airport_id, latitude, longitude, old_timezone = parts
        lat, lon = float(latitude), float(longitude)
        if lat == 0.0 and lon == 0.0:
            invalid_coordinates.append(int(airport_id))
        timezone_name = finder.timezone_at(lng=lon, lat=lat)
        if timezone_name is None:
            not_found.append({"id": int(airport_id), "latitude": lat, "longitude": lon})
        elif timezone_name == old_timezone:
            unchanged += 1
        else:
            changes.append((int(airport_id), timezone_name))

    for offset in range(0, len(changes), 400):
        batch = changes[offset:offset + 400]
        case_sql = " ".join(f"WHEN {airport_id} THEN {sql_quote(zone)}" for airport_id, zone in batch)
        ids = ",".join(str(airport_id) for airport_id, _ in batch)
        mysql.query(
            f"UPDATE ml_airports SET timezone_name = CASE id {case_sql} END WHERE id IN ({ids})",
            database,
        )

    remaining = scalar(mysql, "SELECT COUNT(*) FROM ml_airports WHERE timezone_name IS NULL OR TRIM(timezone_name)=''", database)
    return {
        "airports_total": len(rows),
        "updated": len(changes),
        "unchanged": unchanged,
        "not_found_count": len(not_found),
        "not_found": not_found[:200],
        "invalid_zero_coordinates": invalid_coordinates,
        "remaining_without_timezone": remaining,
    }


def duration_audit(mysql: Mysql, database: str) -> dict:
    """Diagnostycznie porownuje historyczne czasy z czasem obliczonym w UTC."""
    try:
        from zoneinfo import ZoneInfo, ZoneInfoNotFoundError
    except ImportError as exc:
        raise MigrationError("Python nie udostepnia modulu zoneinfo") from exc

    sql = """
        SELECT f.id, f.user_id, f.departure_date, COALESCE(f.departure_time,''),
               COALESCE(f.arrival_date,''), COALESCE(f.arrival_time,''),
               COALESCE(f.duration_seconds,''), dep.timezone_name, arr.timezone_name,
               COALESCE(dep.iata_code,'---'), COALESCE(arr.iata_code,'---')
        FROM ml_flights f
        JOIN ml_airports dep ON dep.id=f.departure_airport_id
        JOIN ml_airports arr ON arr.id=f.arrival_airport_id
        WHERE f.departure_time IS NOT NULL AND f.arrival_time IS NOT NULL
          AND f.arrival_date IS NOT NULL
          AND dep.timezone_name IS NOT NULL AND arr.timezone_name IS NOT NULL
        ORDER BY f.id
    """
    lines = mysql.query(sql, database).splitlines()
    checked = identical = different = without_old = 0
    negative = []
    invalid_zones = []
    distribution = Counter()
    user75 = {"checked": 0, "identical": 0, "different": 0, "without_old_duration": 0, "negative": 0}
    difference_samples = []

    for line in lines:
        p = line.split("\t")
        if len(p) != 11:
            raise MigrationError("Nieoczekiwany format danych podczas kontroli czasow lotow")
        flight_id, user_id = int(p[0]), int(p[1])
        try:
            departure = dt.datetime.fromisoformat(f"{p[2]} {p[3]}").replace(tzinfo=ZoneInfo(p[7]))
            arrival = dt.datetime.fromisoformat(f"{p[4]} {p[5]}").replace(tzinfo=ZoneInfo(p[8]))
        except ZoneInfoNotFoundError:
            invalid_zones.append({"id": flight_id, "departure_timezone": p[7], "arrival_timezone": p[8]})
            continue
        calculated = int((arrival.astimezone(dt.timezone.utc) - departure.astimezone(dt.timezone.utc)).total_seconds())
        old = int(p[6]) if p[6] else None
        checked += 1
        if user_id == 75:
            user75["checked"] += 1
        if calculated < 0:
            item = {"id": flight_id, "user_id": user_id, "route": f"{p[9]}-{p[10]}", "calculated_seconds": calculated}
            negative.append(item)
            if user_id == 75:
                user75["negative"] += 1
        if old is None:
            without_old += 1
            if user_id == 75:
                user75["without_old_duration"] += 1
            continue
        difference = calculated - old
        distribution[difference] += 1
        if difference == 0:
            identical += 1
            if user_id == 75:
                user75["identical"] += 1
        else:
            different += 1
            if user_id == 75:
                user75["different"] += 1
            if len(difference_samples) < 300:
                difference_samples.append({
                    "id": flight_id, "user_id": user_id, "route": f"{p[9]}-{p[10]}",
                    "old_seconds": old, "calculated_seconds": calculated, "difference_seconds": difference,
                })

    return {
        "checked": checked,
        "identical": identical,
        "different": different,
        "without_old_duration": without_old,
        "negative_count": len(negative),
        "negative": negative[:300],
        "invalid_zone_count": len(invalid_zones),
        "invalid_zones": invalid_zones[:200],
        "difference_distribution_seconds": {str(k): v for k, v in sorted(distribution.items())},
        "difference_samples": difference_samples,
        "user_75": user75,
    }


def preflight(mysql: Mysql, cfg: dict) -> dict:
    source = valid_db_name(cfg["source_db"])
    required = {
        "tr_sp_users", "tr_sp_mapapodrozy", "tr_abc_airports",
        "tr_abc_airlines", "tr_abc_aircrafts",
    }
    if not db_exists(mysql, source):
        raise MigrationError(f"Nie istnieje baza źródłowa {source}")
    present = set(mysql.query("SHOW TABLES", source).splitlines())
    missing = sorted(required - present)
    if missing:
        raise MigrationError("Brak tabel źródłowych: " + ", ".join(missing))

    checks = {
        "source_users_with_flights": scalar(mysql, "SELECT COUNT(DISTINCT user_ID) FROM tr_sp_mapapodrozy", source),
        "source_flights": scalar(mysql, "SELECT COUNT(*) FROM tr_sp_mapapodrozy", source),
        "source_airports": scalar(mysql, "SELECT COUNT(*) FROM tr_abc_airports", source),
        "source_airlines": scalar(mysql, "SELECT COUNT(*) FROM tr_abc_airlines WHERE airline_ID > 0", source),
        "source_aircraft_types": scalar(mysql, "SELECT COUNT(*) FROM tr_abc_aircrafts", source),
        "source_distance": scalar(mysql, "SELECT COALESCE(SUM(distance),0) FROM tr_sp_mapapodrozy", source),
        "source_duration": scalar(mysql, "SELECT COALESCE(SUM(duration),0) FROM tr_sp_mapapodrozy", source),
        "flights_missing_user": scalar(mysql, "SELECT COUNT(*) FROM tr_sp_mapapodrozy f LEFT JOIN tr_sp_users u ON u.user_ID=f.user_ID WHERE u.user_ID IS NULL", source),
        "flights_missing_departure": scalar(mysql, "SELECT COUNT(*) FROM tr_sp_mapapodrozy f LEFT JOIN tr_abc_airports a ON a.airport_ID=f.departure WHERE a.airport_ID IS NULL", source),
        "flights_missing_arrival": scalar(mysql, "SELECT COUNT(*) FROM tr_sp_mapapodrozy f LEFT JOIN tr_abc_airports a ON a.airport_ID=f.arrival WHERE a.airport_ID IS NULL", source),
        "unknown_class_codes": scalar(mysql, "SELECT COUNT(*) FROM tr_sp_mapapodrozy WHERE classType IS NOT NULL AND classType NOT IN ('e','p','b','f')", source),
        "unknown_seat_codes": scalar(mysql, "SELECT COUNT(*) FROM tr_sp_mapapodrozy WHERE seatType IS NOT NULL AND seatType NOT IN ('w','m','a')", source),
        "unknown_reason_codes": scalar(mysql, "SELECT COUNT(*) FROM tr_sp_mapapodrozy WHERE reason IS NOT NULL AND reason NOT IN ('b','p')", source),
    }
    critical = ["flights_missing_user", "flights_missing_departure", "flights_missing_arrival"]
    failures = [key for key in critical if checks[key] != 0]
    if failures:
        raise MigrationError("Preflight FAIL: " + ", ".join(f"{x}={checks[x]}" for x in failures))
    return checks


def transformed_legacy_files(candidate: str, legacy_files: list[str], run_dir: Path) -> list[Path]:
    run_dir.mkdir(parents=True, exist_ok=True)
    result = []
    for filename in legacy_files:
        source = LEGACY / filename
        if not source.exists():
            raise MigrationError(f"Brak pliku historycznego: {source}")
        content = source.read_text(encoding="utf-8-sig")
        # Zmiana dotyczy wyłącznie tymczasowej kopii wykonawczej.
        content = content.replace("`mapa_lotow`", f"`{candidate}`")
        content = re.sub(r"(?<![A-Za-z0-9_])mapa_lotow(?![A-Za-z0-9_])", candidate, content)
        target = run_dir / filename
        target.write_text(content, encoding="utf-8", newline="\n")
        result.append(target)
    return result


def integrity(mysql: Mysql, cfg: dict, source_stats: dict, target: str) -> tuple[str, dict, list[str]]:
    stats = {
        "target_users": scalar(mysql, "SELECT COUNT(*) FROM ml_users", target),
        "target_flights": scalar(mysql, "SELECT COUNT(*) FROM ml_flights", target),
        "target_airports": scalar(mysql, "SELECT COUNT(*) FROM ml_airports", target),
        "target_airlines": scalar(mysql, "SELECT COUNT(*) FROM ml_airlines", target),
        "target_aircraft_types": scalar(mysql, "SELECT COUNT(*) FROM ml_aircraft_types", target),
        "target_countries": scalar(mysql, "SELECT COUNT(*) FROM ml_countries", target),
        "target_distance": scalar(mysql, "SELECT COALESCE(SUM(distance_km),0) FROM ml_flights", target),
        "target_duration": scalar(mysql, "SELECT COALESCE(SUM(duration_seconds),0) FROM ml_flights", target),
        "orphan_users": scalar(mysql, "SELECT COUNT(*) FROM ml_flights f LEFT JOIN ml_users u ON u.id=f.user_id WHERE u.id IS NULL", target),
        "orphan_departures": scalar(mysql, "SELECT COUNT(*) FROM ml_flights f LEFT JOIN ml_airports a ON a.id=f.departure_airport_id WHERE a.id IS NULL", target),
        "orphan_arrivals": scalar(mysql, "SELECT COUNT(*) FROM ml_flights f LEFT JOIN ml_airports a ON a.id=f.arrival_airport_id WHERE a.id IS NULL", target),
        "orphan_airlines": scalar(mysql, "SELECT COUNT(*) FROM ml_flights f LEFT JOIN ml_airlines a ON a.id=f.airline_id WHERE f.airline_id IS NOT NULL AND a.id IS NULL", target),
        "orphan_aircraft": scalar(mysql, "SELECT COUNT(*) FROM ml_flights f LEFT JOIN ml_aircraft_types a ON a.id=f.aircraft_type_id WHERE f.aircraft_type_id IS NOT NULL AND a.id IS NULL", target),
        "airports_without_country": scalar(mysql, "SELECT COUNT(*) FROM ml_airports WHERE country_id IS NULL", target),
        "airports_without_timezone": scalar(mysql, "SELECT COUNT(*) FROM ml_airports WHERE timezone_name IS NULL OR TRIM(timezone_name)=''", target),
    }
    expected_exact = {
        "target_users": source_stats["source_users_with_flights"],
        "target_flights": source_stats["source_flights"],
        "target_distance": source_stats["source_distance"],
        "target_duration": source_stats["source_duration"],
        "target_countries": 249,
    }
    failures = [f"{key}: {stats[key]} != {value}" for key, value in expected_exact.items() if stats[key] != value]
    # Slowniki moga zostac rozszerzone przez kolejne migracje, ale nie wolno
    # utracic zadnego rekordu pochodzacego ze starej bazy.
    minimums = {
        "target_airports": source_stats["source_airports"],
        "target_airlines": source_stats["source_airlines"],
        "target_aircraft_types": source_stats["source_aircraft_types"],
    }
    failures.extend(f"{key}: {stats[key]} < {value}" for key, value in minimums.items() if stats[key] < value)
    for key in ("orphan_users", "orphan_departures", "orphan_arrivals", "orphan_airlines", "orphan_aircraft", "airports_without_country", "airports_without_timezone"):
        if stats[key] != 0:
            failures.append(f"{key}: {stats[key]} != 0")
    warnings = []
    for key in ("unknown_class_codes", "unknown_seat_codes", "unknown_reason_codes"):
        if source_stats.get(key):
            warnings.append(f"{key}={source_stats[key]}")
    status = "FAIL" if failures else ("PASS WITH WARNINGS" if warnings else "PASS")
    return status, stats, failures + warnings


def write_report(path: Path, payload: dict) -> None:
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")


def build(mysql: Mysql, cfg: dict) -> None:
    REPORTS.mkdir(parents=True, exist_ok=True)
    TEMP.mkdir(parents=True, exist_ok=True)
    candidate = valid_db_name(cfg["candidate_db"])
    run_stamp = stamp()
    report_path = REPORTS / f"{run_stamp}_build_report.json"
    log_path = REPORTS / f"{run_stamp}_build.log"
    payload = {"operation": "BUILD", "started_at": dt.datetime.now().isoformat(), "status": "RUNNING"}
    try:
        source_stats = preflight(mysql, cfg)
        payload["source"] = source_stats
        payload["legacy_sha256"] = {name: sha256(LEGACY / name) for name in cfg["legacy_files"]}
        mysql.query(f"DROP DATABASE IF EXISTS `{candidate}`; CREATE DATABASE `{candidate}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;")
        run_dir = TEMP / run_stamp
        files = transformed_legacy_files(candidate, cfg["legacy_files"], run_dir)
        airport_reference_stats = None
        airport_resolution_stats = None
        aircraft_reference_stats = None
        airline_reference_stats = None
        with log_path.open("w", encoding="utf-8") as log:
            for path in files:
                mysql.run_file(path, candidate, log)
            for path in sorted(EXTENSIONS.glob("*.sql")):
                mysql.run_file(path, candidate, log)
            airport_cfg = cfg.get("airport_reference", {})
            if airport_cfg.get("enabled", False):
                snapshot_path = REFERENCE_DATA / airport_cfg["snapshot_file"]
                manifest_path = REFERENCE_DATA / "source_manifest.json"
                manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
                expected_sha = manifest["airports"]["sha256"]
                actual_sha = sha256(snapshot_path)
                if actual_sha != expected_sha:
                    raise MigrationError(
                        "Migawka lotnisk ma niezgodna sume SHA-256; przerwano aktualizacje"
                    )
                airport_reference_stats = update_airport_reference(
                    mysql, candidate, snapshot_path, run_dir,
                    airport_cfg["snapshot_date"], log,
                )
                airport_reference_stats["snapshot_sha256"] = actual_sha
                airport_resolution_stats = resolve_airport_conflicts(
                    mysql, candidate, snapshot_path, run_dir,
                    airport_cfg["snapshot_date"], log,
                )
            aircraft_reference_stats = update_aircraft_types(
                mysql, candidate, run_dir, log,
            )
            airline_reference_stats = update_airlines(
                mysql, candidate, run_dir, log,
            )
        timezone_stats = update_airport_timezones(mysql, candidate)
        duration_stats = duration_audit(mysql, candidate)
        status, target_stats, findings = integrity(mysql, cfg, source_stats, candidate)
        if timezone_stats["invalid_zero_coordinates"]:
            findings.append(
                "airports_with_zero_coordinates=" + str(len(timezone_stats["invalid_zero_coordinates"]))
            )
            if status == "PASS":
                status = "PASS WITH WARNINGS"
        if duration_stats["negative_count"]:
            findings.append("negative_calculated_durations=" + str(duration_stats["negative_count"]))
            if status == "PASS":
                status = "PASS WITH WARNINGS"
        if duration_stats["invalid_zone_count"]:
            findings.append("invalid_iana_timezones=" + str(duration_stats["invalid_zone_count"]))
            status = "FAIL"
        if airport_reference_stats and airport_reference_stats["conflict_count"]:
            findings.append(
                "airport_reference_conflicts=" + str(airport_reference_stats["conflict_count"])
            )
            if status == "PASS":
                status = "PASS WITH WARNINGS"
        if airport_resolution_stats:
            if airport_resolution_stats["status"] == "FAIL":
                findings.append(
                    "airport_resolution_missing_targets="
                    + str(airport_resolution_stats["target_source_ids_missing"])
                )
                status = "FAIL"
            manual = airport_resolution_stats.get("manual_review") or {}
            if manual.get("flight_references", 0):
                findings.append(
                    "airport_STU_SBSC_manual_review=" + str(manual["flight_references"])
                )
                if status == "PASS":
                    status = "PASS WITH WARNINGS"
        if aircraft_reference_stats and aircraft_reference_stats["historical_date_anomaly_count"]:
            findings.append(
                "aircraft_historical_date_anomalies="
                + str(aircraft_reference_stats["historical_date_anomaly_count"])
            )
            if status == "PASS":
                status = "PASS WITH WARNINGS"
        if airline_reference_stats and airline_reference_stats["used_airlines_still_missing_iata_or_icao"]:
            findings.append(
                "used_airlines_missing_iata_or_icao="
                + str(airline_reference_stats["used_airlines_still_missing_iata_or_icao"])
            )
            if status == "PASS":
                status = "PASS WITH WARNINGS"
        payload.update({
            "status": status,
            "target": target_stats,
            "airport_reference_update": airport_reference_stats,
            "airport_conflict_resolution": airport_resolution_stats,
            "aircraft_type_reference_update": aircraft_reference_stats,
            "airline_reference_update": airline_reference_stats,
            "timezone_update": timezone_stats,
            "duration_audit": duration_stats,
            "findings": findings,
        })
        if status == "FAIL":
            raise MigrationError("Walidacja bazy kandydującej zakończyła się wynikiem FAIL")
    except Exception as exc:
        payload["status"] = "FAIL"
        payload["error"] = str(exc)
        raise
    finally:
        payload["finished_at"] = dt.datetime.now().isoformat()
        write_report(report_path, payload)
    print(f"BUILD {payload['status']}\nRaport: {report_path}\nLog: {log_path}")


def latest_passing_report(candidate: str) -> tuple[Path, dict]:
    reports = sorted(REPORTS.glob("*_build_report.json"), reverse=True)
    for path in reports:
        data = json.loads(path.read_text(encoding="utf-8"))
        if data.get("status") in ("PASS", "PASS WITH WARNINGS"):
            return path, data
    raise MigrationError("Brak poprawnego raportu BUILD. Najpierw uruchom budowę kandydata.")


def promote(mysql: Mysql, cfg: dict, confirmation: str) -> None:
    if confirmation != "PROMUJ":
        raise MigrationError("Promocja wymaga potwierdzenia PROMUJ")
    REPORTS.mkdir(parents=True, exist_ok=True)
    BACKUPS.mkdir(parents=True, exist_ok=True)
    candidate = valid_db_name(cfg["candidate_db"])
    final = valid_db_name(cfg["final_db"])
    if not db_exists(mysql, candidate):
        raise MigrationError(f"Nie istnieje baza kandydująca {candidate}")
    build_report, build_data = latest_passing_report(candidate)
    current_counts = {
        "target_users": scalar(mysql, "SELECT COUNT(*) FROM ml_users", candidate),
        "target_flights": scalar(mysql, "SELECT COUNT(*) FROM ml_flights", candidate),
        "target_airports": scalar(mysql, "SELECT COUNT(*) FROM ml_airports", candidate),
        "target_airlines": scalar(mysql, "SELECT COUNT(*) FROM ml_airlines", candidate),
        "target_aircraft_types": scalar(mysql, "SELECT COUNT(*) FROM ml_aircraft_types", candidate),
        "target_countries": scalar(mysql, "SELECT COUNT(*) FROM ml_countries", candidate),
    }
    recorded = build_data.get("target", {})
    changed = [key for key, value in current_counts.items() if recorded.get(key) != value]
    if changed:
        raise MigrationError("Baza kandydujaca zmienila sie od ostatniego BUILD: " + ", ".join(changed))
    run_stamp = stamp()
    candidate_dump = BACKUPS / f"{run_stamp}_{candidate}.sql"
    final_backup = BACKUPS / f"{run_stamp}_{final}_before_promotion.sql"
    report_path = REPORTS / f"{run_stamp}_promotion_report.json"
    payload = {"operation": "PROMOTE", "started_at": dt.datetime.now().isoformat(),
               "candidate": candidate, "final": final, "build_report": str(build_report), "status": "RUNNING"}
    final_existed = db_exists(mysql, final)
    try:
        if final_existed:
            mysql.dump(final, final_backup)
            payload["previous_database_backup"] = str(final_backup)
        mysql.dump(candidate, candidate_dump)
        payload["candidate_export"] = str(candidate_dump)
        mysql.query(f"DROP DATABASE IF EXISTS `{final}`; CREATE DATABASE `{final}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;")
        mysql.run_file(candidate_dump, final)
        candidate_counts = mysql.query("SELECT (SELECT COUNT(*) FROM ml_users),(SELECT COUNT(*) FROM ml_flights),(SELECT COUNT(*) FROM ml_airports),(SELECT COUNT(*) FROM ml_airlines),(SELECT COUNT(*) FROM ml_aircraft_types)", candidate)
        final_counts = mysql.query("SELECT (SELECT COUNT(*) FROM ml_users),(SELECT COUNT(*) FROM ml_flights),(SELECT COUNT(*) FROM ml_airports),(SELECT COUNT(*) FROM ml_airlines),(SELECT COUNT(*) FROM ml_aircraft_types)", final)
        if candidate_counts != final_counts:
            raise MigrationError(f"Niezgodne liczby po promocji: candidate={candidate_counts}, final={final_counts}")
        payload.update({"status": "PASS", "verified_counts": final_counts.split("\t")})
    except Exception as exc:
        payload["status"] = "FAIL"
        payload["error"] = str(exc)
        if final_existed and final_backup.exists():
            try:
                mysql.query(f"DROP DATABASE IF EXISTS `{final}`; CREATE DATABASE `{final}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;")
                mysql.run_file(final_backup, final)
                payload["automatic_restore"] = "PASS"
            except Exception as restore_exc:
                payload["automatic_restore"] = "FAIL: " + str(restore_exc)
        raise
    finally:
        payload["finished_at"] = dt.datetime.now().isoformat()
        write_report(report_path, payload)
    print(f"PROMOCJA PASS\nBaza {candidate} została wyeksportowana i zaimportowana jako {final}.\nRaport: {report_path}")


def main() -> int:
    parser = argparse.ArgumentParser(description="Migracja bazy Mapa Lotow")
    sub = parser.add_subparsers(dest="command", required=True)
    sub.add_parser("build", help="Zbuduj i zweryfikuj bazę kandydującą")
    p = sub.add_parser("promote", help="Wyeksportuj kandydata i zaimportuj jako bazę finalną")
    p.add_argument("--confirm", default="")
    args = parser.parse_args()
    try:
        cfg = load_config()
        mysql = Mysql(cfg)
        if args.command == "build":
            build(mysql, cfg)
        else:
            promote(mysql, cfg, args.confirm)
        return 0
    except (MigrationError, OSError, json.JSONDecodeError) as exc:
        print(f"BŁĄD: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
