#!/usr/bin/env python3
"""Konserwatywna aktualizacja uzywanych linii i wspolczesnego slownika."""

from __future__ import annotations

from pathlib import Path


# id: name, alias, IATA, ICAO, callsign, active
UPDATES = {
    130:("Aeroflot", "Aeroflot Russian Airlines", "SU", "AFL", "AEROFLOT", 1),
    137:("Air France", None, "AF", "AFR", "AIRFRANS", 1),
    214:("Air Berlin", None, "AB", "BER", "AIR BERLIN", 0),
    218:("Air India", "Air India Limited", "AI", "AIC", "AIR INDIA", 1),
    242:("Air Malta", None, "KM", "AMC", "AIR MALTA", 0),
    333:("airBaltic", "Air Baltic", "BT", "BTI", "AIR BALTIC", 1),
    515:("Avianca", "Avianca - Aerovias Nacionales de Colombia", "AV", "AVA", "AVIANCA", 1),
    576:("AirAsia", "Air Asia", "AK", "AXM", "RED CAP", 1),
    596:("Alitalia", None, "AZ", "AZA", "ALITALIA", 0),
    751:("Air China", None, "CA", "CCA", "AIR CHINA", 1),
    879:("Fiji Airways", "Air Pacific", "FJ", "FJI", "FIJI", 1),
    1094:("Air Cairo", None, "SM", "MSC", "AIR CAIRO", 1),
    1355:("British Airways", None, "BA", "BAW", "SPEEDBIRD", 1),
    1396:("Buddha Air", None, "U4", "BHA", "BUDDHA AIR", 1),
    1680:("Cathay Pacific", None, "CX", "CPA", "CATHAY", 1),
    1708:("Centralwings", None, "C0", "CLW", "CENTRALWINGS", 0),
    1758:("China Eastern Airlines", None, "MU", "CES", "CHINA EASTERN", 1),
    1767:("China Southern Airlines", None, "CZ", "CSN", "CHINA SOUTHERN", 1),
    1946:("Czech Airlines", None, "OK", "CSA", "CSA-LINES", 0),
    2183:("Emirates", "Emirates Airline", "EK", "UAE", "EMIRATES", 1),
    2222:("Etihad Airways", None, "EY", "ETD", "ETIHAD", 1),
    2245:("Eurolot", None, "K2", "ELO", "EUROLOT", 0),
    2297:("easyJet", "EasyJet Airline", "U2", "EZY", "EASY", 1),
    2581:("GOL Linhas Aereas", "Gol Transportes Aereos", "G3", "GLO", "GOL", 1),
    2850:("IndiGo", "IndiGo Airlines", "6E", "IGO", "IFLY", 1),
    2857:("Indonesia AirAsia", None, "QZ", "AWQ", "WAGON AIR", 1),
    2822:("Iberia", "Iberia Airlines", "IB", "IBE", "IBERIA", 1),
    2987:("Japan Airlines", None, "JL", "JAL", "JAPANAIR", 1),
    3000:("Jet Airways", None, "9W", "JAI", "JET AIRWAYS", 0),
    3090:("KLM Royal Dutch Airlines", None, "KL", "KLM", "KLM", 1),
    3200:("LATAM Airlines Chile", "LAN Airlines", "LA", "LAN", "LAN CHILE", 1),
    3210:("LOT Polish Airlines", None, "LO", "LOT", "POLLOT", 1),
    3320:("Lufthansa", None, "LH", "DLH", "LUFTHANSA", 1),
    3737:("Norwegian Air Shuttle", None, "DY", "NAX", "NOR SHUTTLE", 1),
    3926:("Pegasus Airlines", None, "PC", "PGT", "SUNTURK", 1),
    4091:("Qatar Airways", None, "QR", "QTR", "QATARI", 1),
    4296:("Ryanair", None, "FR", "RYR", "RYANAIR", 1),
    4319:("Scandinavian Airlines", "Scandinavian Airlines System", "SK", "SAS", "SCANDINAVIAN", 1),
    4559:("SWISS", "Swiss International Air Lines", "LX", "SWR", "SWISS", 1),
    4560:("Swissair", None, "SR", None, "SWISSAIR", 0),
    4750:("SilkAir", None, "MI", "SLK", "SILKAIR", 0),
    4867:("LATAM Airlines Brasil", "TAM Brazilian Airlines", "JJ", "TAM", "TAM", 1),
    4869:("TAP Air Portugal", "TAP Portugal", "TP", "TAP", "AIR PORTUGAL", 1),
    4936:("Tigerair Singapore", "Tiger Airways", "TR", "TGW", "GO CAT", 0),
    4940:("Thai Airways", "Thai Airways International", "TG", "THA", "THAI", 1),
    4951:("Turkish Airlines", None, "TK", "THY", "TURKISH", 1),
    5094:("Travel Service Hungary", "Travel Service", None, "TVL", "TRAVEL SERVICE", 0),
    5097:("Smartwings", "Travel Service", "QS", "TVS", "SKYTRAVEL", 1),
    5282:("Ukraine International Airlines", None, "PS", "AUI", "UKRAINE INTERNATIONAL", 0),
    5309:("Vietnam Airlines", None, "VN", "HVN", "VIET NAM AIRLINES", 1),
    5352:("Vueling", "Vueling Airlines", "VY", "VLG", "VUELING", 1),
    5360:("Virgin Australia", None, "VA", "VOZ", "VELOCITY", 1),
    5461:("Wizz Air", None, "W6", "WZZ", "WIZZ AIR", 1),
    5462:("Wizz Air Bulgaria", "Wizz Air Hungary", "8Z", "WVL", "WIZZBUL", 0),
    6855:("easyJet Switzerland", "EasyJet (DS)", "DS", "EZS", "TOPSWISS", 1),
    14485:("flydubai", "Fly Dubai", "FZ", "FDB", "SKYDUBAI", 1),
    16198:("Small Planet Airlines Lithuania", "Small Planet Airlines", None, "ELC", None, 0),
    16556:("Enter Air", "ENTERair", "E4", "ENT", "ENTERAIR", 1),
    17577:("VietJet Air", "VietJetAir", "VJ", "VJC", "VIETJET", 1),
    17578:("Batik Air Malaysia", "Malindo Air", "OD", "MXD", "MALINDO", 1),
    17579:("Summit Air", "Goma Air", None, "SMA", "SUMMIT AIR", 1),
    17585:("Thai Smile", "Thai Smile Airways", "WE", "THD", "THAI SMILE", 0),
}


# duplicate_id -> canonical_id; tylko duplikaty potwierdzone nazwa i kontekstem.
MERGES = {
    1572:1355,   # British Airways
    16373:3926,  # Pegasus Airlines
    3001:3000,   # Jet Airways
    2988:2987,   # Japan Airlines Domestic
}


# id, name, alias, IATA, ICAO, callsign, ISO2, active
ADDITIONS = (
    (3000001,"ITA Airways",None,"AZ","ITY","ITARROW","IT",1),
    (3000002,"Norse Atlantic Airways",None,"N0","NBT","LONGSHIP","NO",1),
    (3000003,"Breeze Airways",None,"MX","MXY","MOXY","US",1),
    (3000004,"Avelo Airlines",None,"XP","VXP","AVELO","US",1),
    (3000005,"Akasa Air",None,"QP","AKJ","AKASA AIR","IN",1),
    (3000006,"Air Premia",None,"YP","APZ","AIR PREMIA","KR",1),
    (3000007,"STARLUX Airlines",None,"JX","SJX","STARWALKER","TW",1),
    (3000008,"Vietravel Airlines",None,"VU","VAG","VIETRAVEL AIR","VN",1),
    (3000009,"Bamboo Airways",None,"QH","BAV","BAMBOO","VN",1),
    (3000010,"flyadeal",None,"F3","FAD","PURPLE","SA",1),
    (3000011,"flynas",None,"XY","KNE","NAS EXPRESS","SA",1),
    (3000012,"Greater Bay Airlines",None,"HB","HGB","GREATER BAY","HK",1),
    (3000013,"AirJapan",None,"NQ","AJX","AIR JAPAN","JP",1),
    (3000014,"AJet",None,"VF","TKJ","ANATOLIA","TR",1),
    (3000015,"Discover Airlines",None,"4Y","OCN","OCEAN","DE",1),
    (3000016,"French bee",None,"BF","FBU","FRENCH BEE","FR",1),
    (3000017,"Arajet",None,"DM","DWI","DOMINICAN","DO",1),
    (3000018,"JetSMART Airlines",None,"JA","JAT","ROCKSMART","CL",1),
    (3000019,"ZIPAIR Tokyo",None,"ZG","TZP","ZIPPY","JP",1),
    (3000020,"Air Montenegro",None,"4O","MNE","MOUNT EAGLE","ME",1),
    (3000021,"Air Albania",None,"ZB","ABN","AIR ALBANIA","AL",1),
    (3000022,"KM Malta Airlines",None,"KM","KMM","SKY KNIGHT","MT",1),
    (3000023,"Scoot",None,"TR","TGW","SCOOTER","SG",1),
)


def _sql(value):
    if value is None:
        return "NULL"
    return "'" + str(value).replace("\\", "\\\\").replace("'", "''") + "'"


def update_airlines(mysql, database: str, temp_dir: Path, log=None) -> dict:
    before = int(mysql.query("SELECT COUNT(*) FROM ml_airlines", database) or 0)
    country_lines = mysql.query(
        "SELECT id,UPPER(COALESCE(iso2,'')),name FROM ml_countries", database
    ).splitlines()
    countries = {p[1]:(int(p[0]),p[2]) for line in country_lines
                 if (p:=line.split("\t",2))[1]}
    missing_countries = sorted({row[6] for row in ADDITIONS} - set(countries))
    if missing_countries:
        raise RuntimeError("Brak krajow dla nowych linii: " + ", ".join(missing_countries))

    statements = [
        "SET NAMES utf8mb4;", "START TRANSACTION;",
        "CREATE TABLE IF NOT EXISTS ml_airline_resolution_log ("
        "airline_id INT UNSIGNED NOT NULL PRIMARY KEY,canonical_airline_id INT UNSIGNED NULL,"
        "rule_kind VARCHAR(30) NOT NULL,flight_refs_moved INT UNSIGNED NOT NULL DEFAULT 0,"
        "notes VARCHAR(255) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;",
        "DELETE FROM ml_airline_resolution_log;",
        "UPDATE ml_airlines SET alias=NULL WHERE alias='N';",
    ]
    for airline_id,(name,alias,iata,icao,callsign,active) in UPDATES.items():
        statements.extend([
            "UPDATE ml_airlines SET "
            f"name={_sql(name)},alias={_sql(alias)},iata_code={_sql(iata)},"
            f"icao_code={_sql(icao)},callsign={_sql(callsign)},is_active={active},"
            f"updated_at=CURRENT_TIMESTAMP WHERE id={airline_id};",
            "INSERT INTO ml_airline_resolution_log (airline_id,canonical_airline_id,rule_kind,notes) VALUES "
            f"({airline_id},{airline_id},'normalized','Verified conservative mapping');",
        ])
    for duplicate_id,canonical_id in MERGES.items():
        statements.extend([
            f"UPDATE ml_flights SET airline_id={canonical_id} WHERE airline_id={duplicate_id};",
            "SET @moved=ROW_COUNT();",
            f"UPDATE ml_airlines SET iata_code=NULL,icao_code=NULL,callsign=NULL,is_active=0,"
            f"updated_at=CURRENT_TIMESTAMP WHERE id={duplicate_id};",
            "INSERT INTO ml_airline_resolution_log "
            "(airline_id,canonical_airline_id,rule_kind,flight_refs_moved,notes) VALUES "
            f"({duplicate_id},{canonical_id},'merged_duplicate',@moved,'Legacy duplicate retained as inactive');",
        ])
    for airline_id,name,alias,iata,icao,callsign,iso2,active in ADDITIONS:
        country_id,country_name=countries[iso2]
        statements.append(
            "INSERT INTO ml_airlines "
            "(id,name,alias,iata_code,icao_code,callsign,country_name,country_id,is_active) VALUES "
            f"({airline_id},{_sql(name)},{_sql(alias)},{_sql(iata)},{_sql(icao)},{_sql(callsign)},"
            f"{_sql(country_name)},{country_id},{active}) AS new ON DUPLICATE KEY UPDATE "
            "name=new.name,alias=new.alias,iata_code=new.iata_code,icao_code=new.icao_code,"
            "callsign=new.callsign,country_name=new.country_name,country_id=new.country_id,"
            "is_active=new.is_active,updated_at=CURRENT_TIMESTAMP;"
        )
    statements.append("COMMIT;")
    temp_dir.mkdir(parents=True,exist_ok=True)
    path=temp_dir/"airline_reference_update.sql"
    path.write_text("\n".join(statements)+"\n",encoding="utf-8",newline="\n")
    mysql.run_file(path,database,log)

    after=int(mysql.query("SELECT COUNT(*) FROM ml_airlines",database) or 0)
    moved=int(mysql.query(
        "SELECT COALESCE(SUM(flight_refs_moved),0) FROM ml_airline_resolution_log",database
    ) or 0)
    unresolved_used=int(mysql.query(
        "SELECT COUNT(DISTINCT a.id) FROM ml_airlines a JOIN ml_flights f ON f.airline_id=a.id "
        "WHERE (a.iata_code IS NULL OR a.icao_code IS NULL)",database
    ) or 0)
    return {
        "sources":["IATA official airline code directory","ICAO Doc 8585 designators"],
        "airlines_before":before,"used_airlines_normalized":len(UPDATES),
        "duplicates_merged":len(MERGES),"flight_references_moved":moved,
        "modern_airlines_added":len(ADDITIONS),"airlines_after":after,
        "used_airlines_still_missing_iata_or_icao":unresolved_used,
        "id_policy":"legacy IDs preserved; curated additions use fixed IDs 3000001+",
    }
