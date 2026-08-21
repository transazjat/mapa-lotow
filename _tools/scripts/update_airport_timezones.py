import mysql.connector
from timezonefinder import TimezoneFinder


# ============================================================
# KONFIGURACJA
# ============================================================

DB_CONFIG = {
    "host": "127.0.0.1",
    "port": 3306,
    "user": "root",
    "password": "",
    "database": "mapa_lotow",
}


# ============================================================
# POŁĄCZENIE
# ============================================================

connection = mysql.connector.connect(**DB_CONFIG)

read_cursor = connection.cursor(dictionary=True)
update_cursor = connection.cursor()

timezone_finder = TimezoneFinder()


# ============================================================
# POBRANIE LOTNISK
# ============================================================

read_cursor.execute("""
    SELECT
        id,
        name,
        city,
        iata_code,
        latitude,
        longitude,
        timezone_name
    FROM ml_airports
    ORDER BY id
""")

airports = read_cursor.fetchall()

print(f"Liczba lotnisk: {len(airports)}")
print()


# ============================================================
# USTALANIE TIMEZONE
# ============================================================

updated = 0
unchanged = 0
not_found = []

for airport in airports:

    airport_id = airport["id"]
    latitude = float(airport["latitude"])
    longitude = float(airport["longitude"])

    timezone_name = timezone_finder.timezone_at(
        lng=longitude,
        lat=latitude
    )

    if timezone_name is None:
        not_found.append(airport)

        print(
            f"[BRAK] "
            f"{airport_id} "
            f"{airport['iata_code'] or '---'} "
            f"{airport['name']} "
            f"({latitude}, {longitude})"
        )

        continue

    if airport["timezone_name"] == timezone_name:
        unchanged += 1
        continue

    update_cursor.execute("""
        UPDATE ml_airports
        SET timezone_name = %s
        WHERE id = %s
    """, (
        timezone_name,
        airport_id
    ))

    updated += 1

    print(
        f"[OK] "
        f"{airport_id} "
        f"{airport['iata_code'] or '---'} "
        f"{airport['city']} "
        f"-> {timezone_name}"
    )


# ============================================================
# ZAPIS ZMIAN
# ============================================================

connection.commit()


# ============================================================
# PODSUMOWANIE
# ============================================================

print()
print("=" * 60)
print("PODSUMOWANIE")
print("=" * 60)

print(f"Lotniska razem:       {len(airports)}")
print(f"Zaktualizowane:       {updated}")
print(f"Bez zmian:            {unchanged}")
print(f"Brak timezone:        {len(not_found)}")


if not_found:

    print()
    print("LOTNISKA BEZ ROZPOZNANEJ STREFY:")
    print()

    for airport in not_found:
        print(
            f"{airport['id']:5} | "
            f"{airport['iata_code'] or '---':3} | "
            f"{airport['city']} | "
            f"{airport['name']} | "
            f"{airport['latitude']}, "
            f"{airport['longitude']}"
        )


# ============================================================
# ZAMKNIĘCIE
# ============================================================

read_cursor.close()
update_cursor.close()
connection.close()