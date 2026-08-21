<script setup lang="ts">
import {
  computed,
} from 'vue'

import type {
  Flight,
} from '../types/flight'


interface CountRecord {
  label: string
  count: number
}


interface RouteRecord {
  departureCode: string
  departureCity: string
  arrivalCode: string
  arrivalCity: string
  count: number
}


const props = defineProps<{
  flights: Flight[]
}>()


const emit = defineEmits<{
  close: []
}>()


function validDistance(
  flight:
    Flight,
): number | null {
  const value =
    Number(
      flight.distance_km,
    )

  return Number.isFinite(
    value,
  )
    ? value
    : null
}


function validDuration(
  flight:
    Flight,
): number | null {
  const value =
    Number(
      flight.duration_seconds,
    )

  return Number.isFinite(
    value,
  )
    ? value
    : null
}


function chooseFlightBy(
  selector:
    (
      flight:
        Flight,
    ) =>
      number | null,

  direction:
    'max' | 'min',

  positiveOnly =
    false,
): Flight | null {
  let selected:
    Flight | null =
    null

  let selectedValue:
    number | null =
    null

  for (
    const flight
    of props.flights
  ) {
    const value =
      selector(
        flight,
      )

    if (
      value ===
      null
    ) {
      continue
    }

    if (
      positiveOnly &&
      value <= 0
    ) {
      continue
    }

    if (
      selectedValue ===
      null
    ) {
      selected =
        flight

      selectedValue =
        value

      continue
    }

    if (
      direction ===
        'max'
        ? value >
          selectedValue
        : value <
          selectedValue
    ) {
      selected =
        flight

      selectedValue =
        value
    }
  }

  return selected
}


const longestFlight =
  computed(
    () =>
      chooseFlightBy(
        validDistance,
        'max',
      ),
  )


const shortestFlight =
  computed(
    () =>
      chooseFlightBy(
        validDistance,
        'min',
        true,
      ),
  )


const longestDurationFlight =
  computed(
    () =>
      chooseFlightBy(
        validDuration,
        'max',
      ),
  )


const mostFrequentRoute =
  computed<RouteRecord | null>(
    () => {
      const counts =
        new Map<
          string,
          RouteRecord
        >()

      for (
        const flight
        of props.flights
      ) {
        const key =
          `${flight.departure_airport_id}>${flight.arrival_airport_id}`

        const existing =
          counts.get(
            key,
          )

        if (
          existing
        ) {
          existing.count++

          continue
        }

        counts.set(
          key,
          {
            departureCode:
              flight.departure_iata ??
              '—',

            departureCity:
              flight.departure_city,

            arrivalCode:
              flight.arrival_iata ??
              '—',

            arrivalCity:
              flight.arrival_city,

            count: 1,
          },
        )
      }

      return [
        ...counts.values(),
      ]
        .sort(
          (a, b) =>
            b.count -
              a.count ||
            (
              `${a.departureCode}>${a.arrivalCode}`
            ).localeCompare(
              `${b.departureCode}>${b.arrivalCode}`,
            ),
        )[0] ??
        null
    },
  )


const mostFrequentAirport =
  computed<CountRecord | null>(
    () => {
      const counts =
        new Map<
          number,
          CountRecord
        >()

      function add(
        id:
          number,

        code:
          string | null,

        name:
          string,
      ): void {
        const existing =
          counts.get(
            id,
          )

        if (
          existing
        ) {
          existing.count++

          return
        }

        counts.set(
          id,
          {
            label:
              code
                ? `${code} · ${name}`
                : name,

            count: 1,
          },
        )
      }

      for (
        const flight
        of props.flights
      ) {
        add(
          flight.departure_airport_id,
          flight.departure_iata,
          flight.departure_airport_name,
        )

        add(
          flight.arrival_airport_id,
          flight.arrival_iata,
          flight.arrival_airport_name,
        )
      }

      return [
        ...counts.values(),
      ]
        .sort(
          (a, b) =>
            b.count -
              a.count ||
            a.label.localeCompare(
              b.label,
              undefined,
              {
                sensitivity:
                  'base',
              },
            ),
        )[0] ??
        null
    },
  )


const mostFrequentAirline =
  computed<CountRecord | null>(
    () =>
      mostFrequentNamedValue(
        props.flights,
        (flight) =>
          flight.airline_id !==
            null
            ? `id:${flight.airline_id}`
            : flight.airline_name
              ? `name:${flight.airline_name}`
              : null,
        (flight) =>
          flight.airline_name,
      ),
  )


const mostFrequentAircraft =
  computed<CountRecord | null>(
    () =>
      mostFrequentNamedValue(
        props.flights,
        (flight) =>
          flight.aircraft_type_id !==
            null
            ? `id:${flight.aircraft_type_id}`
            : flight.aircraft_name
              ? `name:${flight.aircraft_name}`
              : null,
        (flight) =>
          flight.aircraft_name,
      ),
  )


function mostFrequentNamedValue(
  flights:
    Flight[],

  keySelector:
    (
      flight:
        Flight,
    ) =>
      string | null,

  labelSelector:
    (
      flight:
        Flight,
    ) =>
      string | null,
): CountRecord | null {
  const counts =
    new Map<
      string,
      CountRecord
    >()

  for (
    const flight
    of flights
  ) {
    const key =
      keySelector(
        flight,
      )

    const label =
      labelSelector(
        flight,
      )?.trim()

    if (
      !key ||
      !label
    ) {
      continue
    }

    const existing =
      counts.get(
        key,
      )

    if (
      existing
    ) {
      existing.count++

      continue
    }

    counts.set(
      key,
      {
        label,
        count: 1,
      },
    )
  }

  return [
    ...counts.values(),
  ]
    .sort(
      (a, b) =>
        b.count -
          a.count ||
        a.label.localeCompare(
          b.label,
          undefined,
          {
            sensitivity:
              'base',
          },
        ),
    )[0] ??
    null
}


function mostFrequentDateBucket(
  keySelector:
    (
      date:
        string,
    ) =>
      string,
): CountRecord | null {
  const counts =
    new Map<
      string,
      number
    >()

  for (
    const flight
    of props.flights
  ) {
    const date =
      flight.departure_date

    if (
      !date
    ) {
      continue
    }

    const key =
      keySelector(
        date,
      )

    if (
      !key
    ) {
      continue
    }

    counts.set(
      key,
      (
        counts.get(
          key,
        ) ??
        0
      ) +
        1,
    )
  }

  const best =
    [...counts.entries()]
      .sort(
        (a, b) =>
          b[1] -
            a[1] ||
          a[0].localeCompare(
            b[0],
          ),
      )[0]

  if (
    !best
  ) {
    return null
  }

  return {
    label:
      best[0],

    count:
      best[1],
  }
}


const busiestDay =
  computed(
    () =>
      mostFrequentDateBucket(
        (date) =>
          date.slice(
            0,
            10,
          ),
      ),
  )


const busiestMonth =
  computed(
    () =>
      mostFrequentDateBucket(
        (date) =>
          date.slice(
            0,
            7,
          ),
      ),
  )


const busiestYear =
  computed(
    () =>
      mostFrequentDateBucket(
        (date) =>
          date.slice(
            0,
            4,
          ),
      ),
  )


function formatNumber(
  value:
    number,
): string {
  return new Intl.NumberFormat(
    'pl-PL',
  ).format(
    value,
  )
}


function formatDuration(
  seconds:
    number | null,
): string {
  if (
    seconds ===
    null
  ) {
    return '—'
  }

  const totalMinutes =
    Math.round(
      seconds /
      60,
    )

  const hours =
    Math.floor(
      totalMinutes /
      60,
    )

  const minutes =
    totalMinutes %
    60

  return `${formatNumber(hours)} h ${minutes} min`
}


function routeLabel(
  flight:
    Flight | null,
): string {
  if (
    !flight
  ) {
    return 'Brak danych'
  }

  return [
    flight.departure_iata ??
      flight.departure_city,
    '→',
    flight.arrival_iata ??
      flight.arrival_city,
  ].join(
    ' ',
  )
}


function routeDetails(
  flight:
    Flight | null,
): string {
  if (
    !flight
  ) {
    return '—'
  }

  return `${flight.departure_city} → ${flight.arrival_city}`
}


function formatDate(
  value:
    string,
): string {
  const [
    year,
    month,
    day,
  ] =
    value.split(
      '-',
    )

  if (
    !year ||
    !month ||
    !day
  ) {
    return value
  }

  return `${day}.${month}.${year}`
}


function formatMonth(
  value:
    string,
): string {
  const [
    year,
    month,
  ] =
    value.split(
      '-',
    )

  if (
    !year ||
    !month
  ) {
    return value
  }

  const date =
    new Date(
      Number(
        year,
      ),
      Number(
        month,
      ) -
        1,
      1,
    )

  const monthName =
    new Intl.DateTimeFormat(
      'pl-PL',
      {
        month:
          'long',
        year:
          'numeric',
      },
    ).format(
      date,
    )

  return monthName
    .charAt(0)
    .toUpperCase() +
    monthName.slice(
      1,
    )
}
</script>


<template>
  <aside class="records-panel">
    <header class="panel-header">
      <div class="title-area">
        <div class="title-icon">
          <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path d="M8 4h8v3a4 4 0 0 1-8 0V4z" />
            <path d="M9 16h6" />
            <path d="M12 11v5" />
            <path d="M7 6H4v1a4 4 0 0 0 4 4" />
            <path d="M17 6h3v1a4 4 0 0 1-4 4" />
            <path d="M8 20h8" />
          </svg>
        </div>

        <div>
          <div class="eyebrow">
            Statystyki
          </div>

          <h2>
            Rekordy
          </h2>

          <p>
            Najważniejsze rekordy z wybranego zakresu lotów
          </p>
        </div>
      </div>

      <button
        type="button"
        class="close-button"
        title="Zamknij"
        aria-label="Zamknij"
        @click="emit('close')"
      >
        ×
      </button>
    </header>

    <div
      v-if="flights.length === 0"
      class="empty"
    >
      Brak lotów w wybranym zakresie.
    </div>

    <section
      v-else
      class="records-grid"
    >
      <article class="record-card">
        <span class="record-card__label">
          Najdłuższy lot
        </span>

        <strong>
          {{ routeLabel(longestFlight) }}
        </strong>

        <div class="record-card__value">
          {{
            longestFlight
              ? `${formatNumber(Number(longestFlight.distance_km ?? 0))} km`
              : '—'
          }}
        </div>

        <small>
          {{ routeDetails(longestFlight) }}
        </small>
      </article>

      <article class="record-card">
        <span class="record-card__label">
          Najkrótszy lot
        </span>

        <strong>
          {{ routeLabel(shortestFlight) }}
        </strong>

        <div class="record-card__value">
          {{
            shortestFlight
              ? `${formatNumber(Number(shortestFlight.distance_km ?? 0))} km`
              : '—'
          }}
        </div>

        <small>
          {{ routeDetails(shortestFlight) }}
        </small>
      </article>

      <article class="record-card">
        <span class="record-card__label">
          Najdłuższy czas lotu
        </span>

        <strong>
          {{ routeLabel(longestDurationFlight) }}
        </strong>

        <div class="record-card__value">
          {{
            formatDuration(
              longestDurationFlight
                ? Number(longestDurationFlight.duration_seconds ?? 0)
                : null,
            )
          }}
        </div>

        <small>
          {{ routeDetails(longestDurationFlight) }}
        </small>
      </article>

      <article class="record-card">
        <span class="record-card__label">
          Najczęstsza trasa
        </span>

        <strong>
          {{
            mostFrequentRoute
              ? `${mostFrequentRoute.departureCode} → ${mostFrequentRoute.arrivalCode}`
              : 'Brak danych'
          }}
        </strong>

        <div class="record-card__value">
          {{
            mostFrequentRoute
              ? `${formatNumber(mostFrequentRoute.count)} lotów`
              : '—'
          }}
        </div>

        <small>
          {{
            mostFrequentRoute
              ? `${mostFrequentRoute.departureCity} → ${mostFrequentRoute.arrivalCity}`
              : '—'
          }}
        </small>
      </article>

      <article class="record-card">
        <span class="record-card__label">
          Najczęstsze lotnisko
        </span>

        <strong>
          {{
            mostFrequentAirport?.label ??
            'Brak danych'
          }}
        </strong>

        <div class="record-card__value">
          {{
            mostFrequentAirport
              ? `${formatNumber(mostFrequentAirport.count)} operacji`
              : '—'
          }}
        </div>

        <small>
          Odloty i przyloty łącznie
        </small>
      </article>

      <article class="record-card">
        <span class="record-card__label">
          Najczęstsza linia lotnicza
        </span>

        <strong>
          {{
            mostFrequentAirline?.label ??
            'Brak danych'
          }}
        </strong>

        <div class="record-card__value">
          {{
            mostFrequentAirline
              ? `${formatNumber(mostFrequentAirline.count)} lotów`
              : '—'
          }}
        </div>

        <small>
          Najwięcej zapisanych lotów
        </small>
      </article>

      <article class="record-card">
        <span class="record-card__label">
          Najczęstszy samolot
        </span>

        <strong>
          {{
            mostFrequentAircraft?.label ??
            'Brak danych'
          }}
        </strong>

        <div class="record-card__value">
          {{
            mostFrequentAircraft
              ? `${formatNumber(mostFrequentAircraft.count)} lotów`
              : '—'
          }}
        </div>

        <small>
          Typ samolotu
        </small>
      </article>

      <article class="record-card">
        <span class="record-card__label">
          Najwięcej lotów jednego dnia
        </span>

        <strong>
          {{
            busiestDay
              ? formatDate(busiestDay.label)
              : 'Brak danych'
          }}
        </strong>

        <div class="record-card__value">
          {{
            busiestDay
              ? `${formatNumber(busiestDay.count)} lotów`
              : '—'
          }}
        </div>

        <small>
          Według daty wylotu
        </small>
      </article>

      <article class="record-card">
        <span class="record-card__label">
          Najwięcej lotów w miesiącu
        </span>

        <strong>
          {{
            busiestMonth
              ? formatMonth(busiestMonth.label)
              : 'Brak danych'
          }}
        </strong>

        <div class="record-card__value">
          {{
            busiestMonth
              ? `${formatNumber(busiestMonth.count)} lotów`
              : '—'
          }}
        </div>

        <small>
          Według daty wylotu
        </small>
      </article>

      <article class="record-card">
        <span class="record-card__label">
          Najwięcej lotów w roku
        </span>

        <strong>
          {{
            busiestYear?.label ??
            'Brak danych'
          }}
        </strong>

        <div class="record-card__value">
          {{
            busiestYear
              ? `${formatNumber(busiestYear.count)} lotów`
              : '—'
          }}
        </div>

        <small>
          Według daty wylotu
        </small>
      </article>
    </section>
  </aside>
</template>


<style scoped>
.records-panel {
  position: absolute;
  top: 18px;
  right: 18px;
  z-index: 40;
  display: flex;
  width:
    min(
      880px,
      calc(
        100vw -
        430px
      )
    );
  height:
    calc(
      100vh -
      36px
    );
  flex-direction: column;
  overflow: hidden;
  padding: 18px;
  border:
    1px solid
    rgba(
      0,
      0,
      0,
      0.08
    );
  border-radius: 16px;
  background:
    rgba(
      255,
      255,
      255,
      0.98
    );
  box-shadow:
    0 14px 40px
    rgba(
      0,
      0,
      0,
      0.18
    );
  backdrop-filter:
    blur(
      12px
    );
}

.panel-header {
  display: flex;
  flex: 0 0 auto;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
}

.title-area {
  display: flex;
  align-items: center;
  gap: 13px;
}

.title-icon {
  display: flex;
  width: 44px;
  height: 44px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border-radius: 11px;
  background:
    rgba(
      11,
      45,
      92,
      0.06
    );
  color: #0b2d5c;
}

.title-icon svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.eyebrow {
  color: #9ca3af;
  font-size: 11px;
  font-weight: 650;
  text-transform: uppercase;
}

.panel-header h2 {
  margin: 2px 0 0;
  color: #222;
  font-size: 21px;
  font-weight: 700;
}

.panel-header p {
  margin: 4px 0 0;
  color: #777;
  font-size: 12px;
}

.close-button {
  display: flex;
  width: 36px;
  height: 36px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  border-radius: 8px;
  background: #f3f3f3;
  color: #444;
  cursor: pointer;
  font-size: 22px;
}

.records-grid {
  display: grid;
  grid-template-columns:
    repeat(
      2,
      minmax(
        0,
        1fr
      )
    );
  gap: 9px;
  margin-top: 18px;
  padding-right: 3px;
  overflow-y: auto;
}

.record-card {
  display: grid;
  min-height: 136px;
  align-content: start;
  padding: 15px 16px;
  border: 1px solid #e0e4e8;
  border-radius: 11px;
  background: #f8f9fa;
}

.record-card__label {
  color: #8d96a1;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.record-card strong {
  margin-top: 9px;
  color: #24384d;
  font-size: 16px;
  line-height: 1.25;
}

.record-card__value {
  margin-top: 5px;
  color: #0b2d5c;
  font-size: 20px;
  font-weight: 800;
  line-height: 1.15;
}

.record-card small {
  margin-top: 7px;
  color: #7c8793;
  font-size: 10.5px;
  line-height: 1.4;
}

.empty {
  display: flex;
  flex: 1 1 auto;
  align-items: center;
  justify-content: center;
  color: #8b949e;
  font-size: 13px;
}

@media (
  max-width:
    1000px
) {
  .records-panel {
    width:
      min(
        680px,
        calc(
          100vw -
          390px
        )
      );
  }

  .records-grid {
    grid-template-columns:
      1fr;
  }
}

@media (
  max-width:
    760px
) {
  .records-panel {
    top: 10px;
    right: 10px;
    width:
      calc(
        100vw -
        20px
      );
    height:
      calc(
        100vh -
        20px
      );
  }
}
</style>
