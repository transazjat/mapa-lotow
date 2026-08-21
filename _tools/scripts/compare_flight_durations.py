import mysql.connector
from datetime import datetime
from zoneinfo import ZoneInfo


DB_CONFIG = {
    "host": "127.0.0.1",
    "port": 3306,
    "user": "root",
    "password": "",
    "database": "mapa_lotow",
}

USER_ID = 75
LIMIT = 30


def format_duration(seconds):
    if seconds is None:
        return "---"

    seconds = int(seconds)
    hours = seconds // 3600
    minutes = (seconds % 3600) // 60

    return f"{hours}:{minutes:02d}"


connection = mysql.connector.connect(**DB_CONFIG)
cursor = connection.cursor(dictionary=True)


cursor.execute(
    """
    SELECT
        f.id,

        f.departure_date,
        f.departure_time,
        f.arrival_date,
        f.arrival_time,

        f.duration_seconds,

        dep.iata_code AS departure_iata,
        dep.city AS departure_city,
        dep.timezone_name AS departure_timezone,

        arr.iata_code AS arrival_iata,
        arr.city AS arrival_city,
        arr.timezone_name AS arrival_timezone

    FROM ml_flights f

    JOIN ml_airports dep
        ON dep.id = f.departure_airport_id

    JOIN ml_airports arr
        ON arr.id = f.arrival_airport_id

    WHERE f.user_id = %s
      AND f.departure_time IS NOT NULL
      AND f.arrival_time IS NOT NULL
      AND dep.timezone_name IS NOT NULL
      AND arr.timezone_name IS NOT NULL

    ORDER BY f.departure_date DESC, f.id DESC
    """,
    (USER_ID,),
)

flights = cursor.fetchall()


print()
print("=" * 120)
print("PORÓWNANIE CZASÓW LOTÓW")
print("=" * 120)

print(
    f"{'ID':>6} "
    f"{'TRASA':<12} "
    f"{'DATA':<12} "
    f"{'STARY':>8} "
    f"{'NOWY':>8} "
    f"{'RÓŻNICA':>10}"
)

print("-" * 120)


differences = []
identical = 0
different = 0
no_old_duration = 0

from collections import Counter

difference_counter = Counter()
negative_new_duration = []

for flight in flights:

    departure_local = (
        datetime.combine(
            flight["departure_date"],
            datetime.min.time()
        )
        + flight["departure_time"]
    ).replace(
        tzinfo=ZoneInfo(flight["departure_timezone"])
    )

    arrival_local = (
        datetime.combine(
            flight["arrival_date"],
            datetime.min.time()
        )
        + flight["arrival_time"]
    ).replace(
        tzinfo=ZoneInfo(flight["arrival_timezone"])
    )

    departure_utc = departure_local.astimezone(ZoneInfo("UTC"))
    arrival_utc = arrival_local.astimezone(ZoneInfo("UTC"))

    calculated_seconds = int(
        (arrival_utc - departure_utc).total_seconds()
    )

    route = (
        f"{flight['departure_iata'] or '---'}"
        f"-"
        f"{flight['arrival_iata'] or '---'}"
    )

    if calculated_seconds < 0:
        negative_new_duration.append({
            "id": flight["id"],
            "route": route,
            "seconds": calculated_seconds,
        })

    old_seconds = flight["duration_seconds"]

    if old_seconds is None:
        difference = None
        no_old_duration += 1

    else:
        difference = calculated_seconds - int(old_seconds)

        if difference == 0:
            identical += 1
        else:
            different += 1

    if difference is not None:
        difference_counter[difference] += 1

    print(
        f"{flight['id']:>6} "
        f"{route:<12} "
        f"{str(flight['departure_date']):<12} "
        f"{format_duration(old_seconds):>8} "
        f"{format_duration(calculated_seconds):>8} "
        f"{format_duration(abs(difference)) if difference is not None else '---':>10}"
    )

    if difference not in (None, 0):
        differences.append(
            {
                "id": flight["id"],
                "route": route,
                "old": old_seconds,
                "new": calculated_seconds,
                "difference": difference,
                "departure_timezone": flight["departure_timezone"],
                "arrival_timezone": flight["arrival_timezone"],
            }
        )


print()
print("=" * 120)
print("PODSUMOWANIE")
print("=" * 120)

print(f"Sprawdzone loty:             {len(flights)}")
print(f"Identyczny czas:             {identical}")
print(f"Różny czas:                  {different}")
print(f"Brak starego czasu:          {no_old_duration}")

print()
print("=" * 120)
print("ROZKŁAD RÓŻNIC")
print("=" * 120)

for seconds, count in sorted(difference_counter.items()):
    minutes = seconds / 60

    print(
        f"{minutes:+8.0f} min : {count:4} lotów"
    )

print()
print("=" * 120)
print("UJEMNY NOWY CZAS LOTU")
print("=" * 120)

print(f"Liczba przypadków: {len(negative_new_duration)}")

for item in negative_new_duration:
    print(
        f"ID {item['id']} | "
        f"{item['route']} | "
        f"{item['seconds'] / 3600:.2f} h"
    )

if differences:

    print()
    print("LOTY Z RÓŻNICĄ:")
    print()

    for item in differences:

        diff_minutes = item["difference"] / 60

        print(
            f"ID {item['id']} | "
            f"{item['route']} | "
            f"stary {format_duration(item['old'])} | "
            f"nowy {format_duration(item['new'])} | "
            f"różnica {diff_minutes:+.0f} min | "
            f"{item['departure_timezone']} -> "
            f"{item['arrival_timezone']}"
        )


cursor.close()
connection.close()