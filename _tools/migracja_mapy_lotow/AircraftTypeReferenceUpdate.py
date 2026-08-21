#!/usr/bin/env python3
"""Powtarzalne porzadkowanie i rozszerzanie slownika typow statkow powietrznych."""

from __future__ import annotations

from pathlib import Path


# id: (name, family, manufacturer, model, variant, ICAO)
LEGACY = {
    1:("Antonov An-140","Antonov An-140","Antonov","An-140",None,"A140"),
    2:("Antonov An-148","Antonov An-148","Antonov","An-148",None,"A148"),
    3:("Antonov An-28","Antonov An-28","Antonov","An-28",None,"AN28"),
    4:("Antonov An-32","Antonov An-32","Antonov","An-32",None,"AN32"),
    5:("ATR 42","ATR 42","ATR","ATR 42",None,None),
    6:("ATR 72","ATR 72","ATR","ATR 72",None,None),
    7:("ATR 42/72-600 (legacy ambiguous)","ATR -600","ATR","ATR 42/72","600",None),
    8:("Airbus A300","Airbus A300","Airbus","A300",None,None),
    9:("Airbus A310","Airbus A310","Airbus","A310",None,"A310"),
    10:("Airbus A318","Airbus A320 family","Airbus","A318",None,"A318"),
    11:("Airbus A319","Airbus A320 family","Airbus","A319",None,"A319"),
    12:("Airbus A320","Airbus A320 family","Airbus","A320",None,"A320"),
    13:("Airbus A321","Airbus A320 family","Airbus","A321",None,"A321"),
    14:("Airbus A330","Airbus A330","Airbus","A330",None,None),
    15:("Airbus A340","Airbus A340","Airbus","A340",None,None),
    16:("Airbus A350","Airbus A350","Airbus","A350",None,None),
    17:("Airbus A380-800","Airbus A380","Airbus","A380","800","A388"),
    18:("Beechcraft 1900","Beechcraft 1900","Beechcraft","1900",None,"B190"),
    19:("Beechcraft 99","Beechcraft 99","Beechcraft","99",None,"BE99"),
    20:("Beechcraft Baron","Beechcraft Baron","Beechcraft","Baron",None,None),
    21:("Beechcraft King Air","Beechcraft King Air","Beechcraft","King Air",None,None),
    22:("Boeing 717-200","Boeing 717","Boeing","717","200","B712"),
    23:("Boeing 727","Boeing 727","Boeing","727",None,None),
    24:("Boeing 737","Boeing 737","Boeing","737",None,None),
    25:("Boeing 747","Boeing 747","Boeing","747",None,None),
    26:("Boeing 747-8 Intercontinental","Boeing 747","Boeing","747","8 Intercontinental","B748"),
    27:("Boeing 757","Boeing 757","Boeing","757",None,None),
    28:("Boeing 767","Boeing 767","Boeing","767",None,None),
    29:("Boeing 777","Boeing 777","Boeing","777",None,None),
    30:("Boeing 787 Dreamliner","Boeing 787","Boeing","787",None,None),
    31:("BAe 125","BAe 125","British Aerospace","125",None,None),
    32:("BAe 146","BAe 146","British Aerospace","146",None,None),
    33:("BAe ATP","BAe ATP","British Aerospace","ATP",None,"ATP"),
    34:("Bombardier CRJ100","Bombardier CRJ","Bombardier","CRJ","100","CRJ1"),
    35:("Bombardier CRJ200","Bombardier CRJ","Bombardier","CRJ","200","CRJ2"),
    36:("Cessna 162 Skycatcher","Cessna 162","Cessna","162","Skycatcher","C162"),
    37:("Cessna 172 Skyhawk","Cessna 172","Cessna","172","Skyhawk","C172"),
    38:("Cessna 182 Skylane","Cessna 182","Cessna","182","Skylane","C182"),
    39:("Cessna 206 Stationair","Cessna 206","Cessna","206","Stationair","C206"),
    40:("Cessna 208 Caravan","Cessna 208","Cessna","208","Caravan","C208"),
    41:("Cessna 340","Cessna 340","Cessna","340",None,"C340"),
    42:("Cessna 404 Titan","Cessna 404","Cessna","404","Titan","C404"),
    43:("Cessna Citation 560","Cessna Citation","Cessna","Citation 560",None,"C560"),
    44:("Cessna Citation 680 Sovereign","Cessna Citation","Cessna","Citation 680","Sovereign","C680"),
    45:("De Havilland Canada DHC-3 Otter","DHC-3 Otter","De Havilland Canada","DHC-3","Otter","DHC3"),
    46:("De Havilland Canada DHC-6 Twin Otter","DHC-6 Twin Otter","De Havilland Canada","DHC-6","Twin Otter","DHC6"),
    47:("De Havilland Canada DHC-7 Dash 7","DHC-7 Dash 7","De Havilland Canada","DHC-7","Dash 7","DHC7"),
    48:("De Havilland Canada Dash 8","Dash 8","De Havilland Canada","Dash 8",None,None),
    49:("Dornier 228","Dornier 228","Dornier","228",None,"D228"),
    50:("Dornier 328","Dornier 328","Dornier","328",None,"D328"),
    51:("Embraer EMB 110 Bandeirante","Embraer EMB 110","Embraer","EMB 110","Bandeirante","E110"),
    52:("Embraer EMB 120 Brasilia","Embraer EMB 120","Embraer","EMB 120","Brasilia","E120"),
    53:("Embraer ERJ 135","Embraer ERJ","Embraer","ERJ","135","E135"),
    54:("Embraer ERJ 140","Embraer ERJ","Embraer","ERJ","140","E135"),
    55:("Embraer ERJ 145","Embraer ERJ","Embraer","ERJ","145","E145"),
    56:("Embraer E170","Embraer E-Jet","Embraer","E-Jet","170","E170"),
    57:("Embraer E175","Embraer E-Jet","Embraer","E-Jet","175",None),
    58:("Embraer E190","Embraer E-Jet","Embraer","E-Jet","190","E190"),
    59:("Embraer E195","Embraer E-Jet","Embraer","E-Jet","195","E195"),
    60:("Fokker 50","Fokker 50","Fokker","50",None,"F50"),
    61:("Fokker 70","Fokker 70","Fokker","70",None,"F70"),
    62:("Fokker 100","Fokker 100","Fokker","100",None,"F100"),
    63:("BAe Jetstream 41","Jetstream 41","British Aerospace","Jetstream","41","JS41"),
    64:("McDonnell Douglas DC-9","McDonnell Douglas DC-9","McDonnell Douglas","DC-9",None,None),
    65:("McDonnell Douglas DC-10","McDonnell Douglas DC-10","McDonnell Douglas","DC-10",None,"DC10"),
    66:("McDonnell Douglas MD-11","McDonnell Douglas MD-11","McDonnell Douglas","MD-11",None,"MD11"),
    67:("McDonnell Douglas MD-80","McDonnell Douglas MD-80","McDonnell Douglas","MD-80",None,None),
    68:("McDonnell Douglas MD-81","McDonnell Douglas MD-80","McDonnell Douglas","MD-80","81","MD81"),
    69:("McDonnell Douglas MD-82","McDonnell Douglas MD-80","McDonnell Douglas","MD-80","82","MD82"),
    70:("McDonnell Douglas MD-83","McDonnell Douglas MD-80","McDonnell Douglas","MD-80","83","MD83"),
    71:("McDonnell Douglas MD-87","McDonnell Douglas MD-80","McDonnell Douglas","MD-80","87","MD87"),
    72:("McDonnell Douglas MD-88","McDonnell Douglas MD-80","McDonnell Douglas","MD-80","88","MD88"),
    73:("McDonnell Douglas MD-90","McDonnell Douglas MD-90","McDonnell Douglas","MD-90",None,"MD90"),
    74:("Pilatus PC-6 Porter","Pilatus PC-6","Pilatus","PC-6","Porter","PC6T"),
    75:("Saab 2000","Saab 2000","Saab","2000",None,"SB20"),
    76:("Saab 340","Saab 340","Saab","340",None,"SF34"),
    77:("Sukhoi Superjet 100","Sukhoi Superjet 100","Sukhoi","Superjet 100",None,"SU95"),
    78:("Tupolev Tu-154","Tupolev Tu-154","Tupolev","Tu-154",None,"T154"),
    79:("Tupolev Tu-204","Tupolev Tu-204","Tupolev","Tu-204",None,"T204"),
    80:("Yakovlev Yak-40","Yakovlev Yak-40","Yakovlev","Yak-40",None,"YK40"),
    81:("Yakovlev Yak-42","Yakovlev Yak-42","Yakovlev","Yak-42",None,"YK42"),
    82:("LET L-410 Turbolet","LET L-410","LET","L-410","Turbolet","L410"),
    83:("Antonov An-24","Antonov An-24","Antonov","An-24",None,"AN24"),
    84:("Ilyushin Il-18","Ilyushin Il-18","Ilyushin","Il-18",None,"IL18"),
    600:("Helicopter - type unspecified","Helicopter",None,None,None,None),
    999:("Other aircraft - type unspecified","Other aircraft",None,None,None,"ZZZZ"),
}


# Stabilne ID 2000001+ sa czescia procedury, a nie numerami z zewnetrznego zrodla.
ADDITIONS = (
    (2000001,"Airbus A220-100","Airbus A220","Airbus","A220","100","BCS1"),
    (2000002,"Airbus A220-300","Airbus A220","Airbus","A220","300","BCS3"),
    (2000003,"Airbus A319neo","Airbus A320 family","Airbus","A319","neo","A19N"),
    (2000004,"Airbus A320neo","Airbus A320 family","Airbus","A320","neo","A20N"),
    (2000005,"Airbus A321neo","Airbus A320 family","Airbus","A321","neo","A21N"),
    (2000006,"Airbus A300-600","Airbus A300","Airbus","A300","600","A306"),
    (2000007,"Airbus A330-200","Airbus A330","Airbus","A330","200","A332"),
    (2000008,"Airbus A330-300","Airbus A330","Airbus","A330","300","A333"),
    (2000009,"Airbus A330-800neo","Airbus A330","Airbus","A330","800neo","A338"),
    (2000010,"Airbus A330-900neo","Airbus A330","Airbus","A330","900neo","A339"),
    (2000011,"Airbus A340-200","Airbus A340","Airbus","A340","200","A342"),
    (2000012,"Airbus A340-300","Airbus A340","Airbus","A340","300","A343"),
    (2000013,"Airbus A340-500","Airbus A340","Airbus","A340","500","A345"),
    (2000014,"Airbus A340-600","Airbus A340","Airbus","A340","600","A346"),
    (2000015,"Airbus A350-900","Airbus A350","Airbus","A350","900","A359"),
    (2000016,"Airbus A350-1000","Airbus A350","Airbus","A350","1000","A35K"),
    (2000017,"ATR 42-500","ATR 42","ATR","ATR 42","500","AT45"),
    (2000018,"ATR 42-600","ATR 42","ATR","ATR 42","600","AT46"),
    (2000019,"ATR 72-500","ATR 72","ATR","ATR 72","500","AT75"),
    (2000020,"ATR 72-600","ATR 72","ATR","ATR 72","600","AT76"),
    (2000021,"Boeing 737-200","Boeing 737","Boeing","737","200","B732"),
    (2000022,"Boeing 737-300","Boeing 737","Boeing","737","300","B733"),
    (2000023,"Boeing 737-400","Boeing 737","Boeing","737","400","B734"),
    (2000024,"Boeing 737-500","Boeing 737","Boeing","737","500","B735"),
    (2000025,"Boeing 737-600","Boeing 737","Boeing","737","600","B736"),
    (2000026,"Boeing 737-700","Boeing 737","Boeing","737","700","B737"),
    (2000027,"Boeing 737-800","Boeing 737","Boeing","737","800","B738"),
    (2000028,"Boeing 737-900","Boeing 737","Boeing","737","900","B739"),
    (2000029,"Boeing 737 MAX 7","Boeing 737","Boeing","737 MAX","7","B37M"),
    (2000030,"Boeing 737 MAX 8","Boeing 737","Boeing","737 MAX","8","B38M"),
    (2000031,"Boeing 737 MAX 9","Boeing 737","Boeing","737 MAX","9","B39M"),
    (2000032,"Boeing 737 MAX 10","Boeing 737","Boeing","737 MAX","10","B3XM"),
    (2000033,"Boeing 747-100","Boeing 747","Boeing","747","100","B741"),
    (2000034,"Boeing 747-200","Boeing 747","Boeing","747","200","B742"),
    (2000035,"Boeing 747-300","Boeing 747","Boeing","747","300","B743"),
    (2000036,"Boeing 747-400","Boeing 747","Boeing","747","400","B744"),
    (2000037,"Boeing 757-200","Boeing 757","Boeing","757","200","B752"),
    (2000038,"Boeing 757-300","Boeing 757","Boeing","757","300","B753"),
    (2000039,"Boeing 767-200","Boeing 767","Boeing","767","200","B762"),
    (2000040,"Boeing 767-300","Boeing 767","Boeing","767","300","B763"),
    (2000041,"Boeing 767-400","Boeing 767","Boeing","767","400","B764"),
    (2000042,"Boeing 777-200","Boeing 777","Boeing","777","200","B772"),
    (2000043,"Boeing 777-200LR","Boeing 777","Boeing","777","200LR","B77L"),
    (2000044,"Boeing 777-300","Boeing 777","Boeing","777","300","B773"),
    (2000045,"Boeing 777-300ER","Boeing 777","Boeing","777","300ER","B77W"),
    (2000046,"Boeing 787-8 Dreamliner","Boeing 787","Boeing","787","8","B788"),
    (2000047,"Boeing 787-9 Dreamliner","Boeing 787","Boeing","787","9","B789"),
    (2000048,"Boeing 787-10 Dreamliner","Boeing 787","Boeing","787","10","B78X"),
    (2000049,"Bombardier CRJ700","Bombardier CRJ","Bombardier","CRJ","700","CRJ7"),
    (2000050,"Bombardier CRJ900","Bombardier CRJ","Bombardier","CRJ","900","CRJ9"),
    (2000051,"Bombardier CRJ1000","Bombardier CRJ","Bombardier","CRJ","1000","CRJX"),
    (2000052,"De Havilland Canada Dash 8-100","Dash 8","De Havilland Canada","Dash 8","100","DH8A"),
    (2000053,"De Havilland Canada Dash 8-200","Dash 8","De Havilland Canada","Dash 8","200","DH8B"),
    (2000054,"De Havilland Canada Dash 8-300","Dash 8","De Havilland Canada","Dash 8","300","DH8C"),
    (2000055,"De Havilland Canada Dash 8-400","Dash 8","De Havilland Canada","Dash 8","400","DH8D"),
    (2000056,"Embraer E190-E2","Embraer E-Jet E2","Embraer","E-Jet E2","190-E2","E290"),
    (2000057,"Embraer E195-E2","Embraer E-Jet E2","Embraer","E-Jet E2","195-E2","E295"),
    (2000058,"COMAC C909 (ARJ21-700)","COMAC C909","COMAC","C909","ARJ21-700","AJ27"),
    (2000059,"COMAC C919","COMAC C919","COMAC","C919",None,"C919"),
    (2000060,"Yakovlev MC-21-300","Yakovlev MC-21","Yakovlev","MC-21","300","MC23"),
)


SERVICE_START = {17:"2007-10-25",26:"2012-06-01",16:"2015-01-15",59:"2006-09-01",30:"2011-10-26"}


def _sql(value):
    if value is None:
        return "NULL"
    return "'" + str(value).replace("\\", "\\\\").replace("'", "''") + "'"


def update_aircraft_types(mysql, database: str, temp_dir: Path, log=None) -> dict:
    before = int(mysql.query("SELECT COUNT(*) FROM ml_aircraft_types", database) or 0)
    statements = ["SET NAMES utf8mb4;", "START TRANSACTION;"]
    for aircraft_id, values in LEGACY.items():
        name, family, manufacturer, model, variant, icao = values
        statements.append(
            "UPDATE ml_aircraft_types SET "
            f"name={_sql(name)},family={_sql(family)},manufacturer={_sql(manufacturer)},"
            f"model={_sql(model)},variant={_sql(variant)},icao_code={_sql(icao)},"
            f"is_active=1,updated_at=CURRENT_TIMESTAMP WHERE id={aircraft_id};"
        )
    for aircraft_id, name, family, manufacturer, model, variant, icao in ADDITIONS:
        statements.append(
            "INSERT INTO ml_aircraft_types "
            "(id,name,family,manufacturer,model,variant,icao_code,is_active) VALUES "
            f"({aircraft_id},{_sql(name)},{_sql(family)},{_sql(manufacturer)},{_sql(model)},"
            f"{_sql(variant)},{_sql(icao)},1) AS new ON DUPLICATE KEY UPDATE "
            "name=new.name,family=new.family,manufacturer=new.manufacturer,"
            "model=new.model,variant=new.variant,icao_code=new.icao_code,"
            "is_active=new.is_active,updated_at=CURRENT_TIMESTAMP;"
        )
    statements.append("COMMIT;")
    temp_dir.mkdir(parents=True, exist_ok=True)
    path = temp_dir / "aircraft_type_reference_update.sql"
    path.write_text("\n".join(statements) + "\n", encoding="utf-8", newline="\n")
    mysql.run_file(path, database, log)

    after = int(mysql.query("SELECT COUNT(*) FROM ml_aircraft_types", database) or 0)
    missing = int(mysql.query(
        "SELECT COUNT(*) FROM ml_aircraft_types WHERE manufacturer IS NULL "
        "AND id NOT IN (600,999)", database,
    ) or 0)
    anomalies = []
    for aircraft_id, date in SERVICE_START.items():
        lines = mysql.query(
            "SELECT id,user_id,departure_date FROM ml_flights "
            f"WHERE aircraft_type_id={aircraft_id} AND departure_date<'{date}' ORDER BY departure_date,id",
            database,
        ).splitlines()
        for line in lines:
            flight_id, user_id, departure_date = line.split("\t")
            anomalies.append({"flight_id":int(flight_id),"user_id":int(user_id),
                              "aircraft_type_id":aircraft_id,"departure_date":departure_date,
                              "earliest_service_date":date})
    return {
        "source":"ICAO Doc 8643 naming/designators; curated hybrid passenger list",
        "legacy_types_before":before,
        "legacy_types_normalized":len(LEGACY),
        "detailed_types_added":len(ADDITIONS),
        "types_after":after,
        "missing_manufacturer_except_special":missing,
        "historical_date_anomaly_count":len(anomalies),
        "historical_date_anomalies":anomalies[:200],
        "id_policy":"legacy IDs preserved; curated additions use fixed IDs 2000001+",
    }
