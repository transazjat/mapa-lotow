<script setup lang="ts">
import {
  computed,
  ref,
  watch,
} from 'vue'

import FlightCard from './FlightCard.vue'

import type {
  Flight,
} from '../types/flight'

import {
  isPlannedFlight,
} from '../utils/flightScope'


const props = defineProps<{
  flights: Flight[]
  activeFlightId: number | null
  initialAircraftFilterKey?: string | null
}>()


const emit = defineEmits<{
  flight: [flight: Flight]
  filtered: [flights: Flight[]]
  aircraftFilterChanged: [key: string | null]
}>()


const search =
  ref('')


const selectedYear =
  ref('all')


const selectedFlightType =
  ref('all')


const selectedAirline =
  ref('all')


const selectedAircraft =
  ref<string>(
    props.initialAircraftFilterKey ??
    'all',
  )


const plannedMode =
  computed(
    () =>
      props.flights.length > 0 &&
      props.flights.every(
        (flight) =>
          isPlannedFlight(
            flight,
          ),
      ),
  )


const plannedView =
  ref<'list' | 'calendar'>(
    'list',
  )


const calendarMonth =
  ref(
    '',
  )


const sortOrder =
  ref<'newest' | 'oldest'>(
    plannedMode.value
      ? 'oldest'
      : 'newest',
  )


const years =
  computed(
    () => {
      const values =
        new Set<string>()

      for (
        const flight
        of props.flights
      ) {
        if (
          flight.departure_date
        ) {
          values.add(
            flight.departure_date.slice(
              0,
              4,
            ),
          )
        }
      }

      return [...values]
        .sort()
        .reverse()
    },
  )


interface AirlineOption {
  key: string
  name: string
}


function airlineKey(
  flight: Flight,
): string {
  if (
    flight.airline_id !==
    null
  ) {
    return `id:${flight.airline_id}`
  }

  return `name:${flight.airline_name ?? ''}`
}


const airlines =
  computed<AirlineOption[]>(
    () => {
      const result =
        new Map<
          string,
          AirlineOption
        >()

      for (
        const flight
        of props.flights
      ) {
        if (
          !flight.airline_name
        ) {
          continue
        }

        const key =
          airlineKey(
            flight,
          )

        result.set(
          key,
          {
            key,
            name:
              flight.airline_name,
          },
        )
      }

      return [...result.values()]
        .sort(
          (a, b) =>
            a.name.localeCompare(
              b.name,
              undefined,
              {
                sensitivity:
                  'base',
              },
            ),
        )
    },
  )



interface AircraftOption {
  key: string
  name: string
}


function aircraftKey(
  flight: Flight,
): string {
  if (
    flight.aircraft_type_id !==
    null
  ) {
    return `id:${flight.aircraft_type_id}`
  }

  return `name:${flight.aircraft_name ?? ''}`
}


const aircraftTypes =
  computed<AircraftOption[]>(
    () => {
      const result =
        new Map<
          string,
          AircraftOption
        >()

      for (
        const flight
        of props.flights
      ) {
        if (
          !flight.aircraft_name
        ) {
          continue
        }

        const key =
          aircraftKey(
            flight,
          )

        result.set(
          key,
          {
            key,
            name:
              flight.aircraft_name,
          },
        )
      }

      return [...result.values()]
        .sort(
          (a, b) =>
            a.name.localeCompare(
              b.name,
              undefined,
              {
                sensitivity:
                  'base',
              },
            ),
        )
    },
  )


const filteredFlights =
  computed(
    () => {
      const query =
        search.value
          .trim()
          .toLowerCase()

      let result =
        props.flights.filter(
          (flight) => {
            if (
              selectedYear.value !==
                'all' &&
              flight.departure_date?.slice(
                0,
                4,
              ) !==
                selectedYear.value
            ) {
              return false
            }


            if (
              selectedFlightType.value !==
                'all' &&
              flight.flight_type !==
                selectedFlightType.value
            ) {
              return false
            }


            if (
              selectedAirline.value !==
                'all' &&
              airlineKey(
                flight,
              ) !==
                selectedAirline.value
            ) {
              return false
            }


            if (
              selectedAircraft.value !==
                'all' &&
              aircraftKey(
                flight,
              ) !==
                selectedAircraft.value
            ) {
              return false
            }


            if (!query) {
              return true
            }


            const text = [
              flight.departure_iata,
              flight.departure_airport_name,
              flight.departure_city,
              flight.departure_country,

              flight.arrival_iata,
              flight.arrival_airport_name,
              flight.arrival_city,
              flight.arrival_country,

              flight.flight_number,
              flight.airline_name,
              flight.aircraft_name,
            ]
              .filter(Boolean)
              .join(' ')
              .toLowerCase()


            return text.includes(
              query,
            )
          },
        )


      result =
        [...result].sort(
          (a, b) => {
            const aDate =
              `${a.departure_date ?? ''} ${a.departure_time ?? ''}`

            const bDate =
              `${b.departure_date ?? ''} ${b.departure_time ?? ''}`


            if (
              sortOrder.value ===
              'newest'
            ) {
              return bDate.localeCompare(
                aDate,
              )
            }


            return aDate.localeCompare(
              bDate,
            )
          },
        )


      return result
    },
  )


const plannedDistance =
  computed(
    () =>
      filteredFlights.value.reduce(
        (
          sum,
          flight,
        ) =>
          sum +
          (
            flight.distance_km ??
            0
          ),
        0,
      ),
  )


const plannedDuration =
  computed(
    () =>
      filteredFlights.value.reduce(
        (
          sum,
          flight,
        ) =>
          sum +
          (
            flight.duration_seconds ??
            0
          ),
        0,
      ),
  )


function datePartsUtc(
  value:
    string | null | undefined,
): number | null {
  if (!value) {
    return null
  }

  const match =
    /^(\d{4})-(\d{2})-(\d{2})$/.exec(
      value,
    )

  if (!match) {
    return null
  }

  return Date.UTC(
    Number(match[1]),
    Number(match[2]) - 1,
    Number(match[3]),
  )
}


function countdownLabel(
  flight: Flight,
): string {
  const target =
    datePartsUtc(
      flight.departure_date,
    )

  if (target === null) {
    return ''
  }

  const now =
    new Date()

  const today =
    Date.UTC(
      now.getFullYear(),
      now.getMonth(),
      now.getDate(),
    )

  const days =
    Math.max(
      0,
      Math.round(
        (
          target -
          today
        ) /
        86_400_000,
      ),
    )

  if (days === 0) {
    return 'dzisiaj'
  }

  if (days === 1) {
    return 'jutro'
  }

  return `za ${days} dni`
}


function monthLabel(
  value:
    string | null | undefined,
): string {
  if (!value) {
    return 'Bez daty'
  }

  const date =
    new Date(
      `${value.slice(0, 7)}-01T12:00:00`,
    )

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return 'Bez daty'
  }

  const label =
    new Intl.DateTimeFormat(
      'pl-PL',
      {
        month: 'long',
        year: 'numeric',
      },
    ).format(
      date,
    )

  return (
    label.charAt(0).toUpperCase() +
    label.slice(1)
  )
}


const flightGroups =
  computed(
    () => {
      const groups =
        new Map<
          string,
          {
            key: string
            label: string
            flights: Flight[]
          }
        >()

      for (
        const flight
        of filteredFlights.value
      ) {
        const key =
          flight.departure_date
            ?.slice(
              0,
              7,
            ) ??
          'unknown'

        if (
          !groups.has(
            key,
          )
        ) {
          groups.set(
            key,
            {
              key,
              label:
                monthLabel(
                  flight.departure_date,
                ),
              flights: [],
            },
          )
        }

        groups.get(
          key,
        )!
          .flights
          .push(
            flight,
          )
      }

      return [
        ...groups.values(),
      ]
    },
  )


const nearestFilteredFlight =
  computed(
    () =>
      plannedMode.value
        ? filteredFlights.value[0] ??
          null
        : null,
  )


const calendarMonthKey =
  computed(
    () => {
      if (
        calendarMonth.value
      ) {
        return calendarMonth.value
      }

      return (
        nearestFilteredFlight.value
          ?.departure_date
          ?.slice(
            0,
            7,
          ) ??
        new Date()
          .toISOString()
          .slice(
            0,
            7,
          )
      )
    },
  )


const calendarMonthLabel =
  computed(
    () =>
      monthLabel(
        `${calendarMonthKey.value}-01`,
      ),
  )


interface CalendarDay {
  key: string
  day: number | null
  currentMonth: boolean
  flights: Flight[]
}


const calendarDays =
  computed<CalendarDay[]>(
    () => {
      const match =
        /^(\d{4})-(\d{2})$/.exec(
          calendarMonthKey.value,
        )

      if (!match) {
        return []
      }

      const year =
        Number(
          match[1],
        )

      const month =
        Number(
          match[2],
        )

      const firstDay =
        new Date(
          year,
          month - 1,
          1,
        )

      const daysInMonth =
        new Date(
          year,
          month,
          0,
        ).getDate()

      // JS: niedziela=0. W kalendarzu zaczynamy od poniedziałku.
      const leading =
        (
          firstDay.getDay() +
          6
        ) %
        7

      const byDate =
        new Map<
          string,
          Flight[]
        >()

      for (
        const flight
        of filteredFlights.value
      ) {
        const key =
          flight.departure_date ??
          ''

        if (
          !key.startsWith(
            `${calendarMonthKey.value}-`,
          )
        ) {
          continue
        }

        if (
          !byDate.has(
            key,
          )
        ) {
          byDate.set(
            key,
            [],
          )
        }

        byDate.get(
          key,
        )!
          .push(
            flight,
          )
      }

      const result:
        CalendarDay[] =
        []

      for (
        let i = 0;
        i < leading;
        i += 1
      ) {
        result.push(
          {
            key:
              `leading-${i}`,
            day: null,
            currentMonth:
              false,
            flights: [],
          },
        )
      }

      for (
        let day = 1;
        day <= daysInMonth;
        day += 1
      ) {
        const dateKey =
          `${calendarMonthKey.value}-${String(day).padStart(2, '0')}`

        result.push(
          {
            key:
              dateKey,
            day,
            currentMonth:
              true,
            flights:
              byDate.get(
                dateKey,
              ) ??
              [],
          },
        )
      }

      while (
        result.length %
          7 !==
        0
      ) {
        result.push(
          {
            key:
              `trailing-${result.length}`,
            day: null,
            currentMonth:
              false,
            flights: [],
          },
        )
      }

      return result
    },
  )


function changeCalendarMonth(
  delta: number,
): void {
  const match =
    /^(\d{4})-(\d{2})$/.exec(
      calendarMonthKey.value,
    )

  if (!match) {
    return
  }

  const date =
    new Date(
      Number(match[1]),
      Number(match[2]) -
        1 +
        delta,
      1,
    )

  calendarMonth.value =
    `${date.getFullYear()}-${String(
      date.getMonth() +
        1,
    ).padStart(
      2,
      '0',
    )}`
}


function goToNearestPlannedMonth(): void {
  const key =
    nearestFilteredFlight.value
      ?.departure_date
      ?.slice(
        0,
        7,
      )

  if (key) {
    calendarMonth.value =
      key
  }
}


function calendarFlightLabel(
  flight: Flight,
): string {
  const route =
    `${flight.departure_iata ?? '???'}→${flight.arrival_iata ?? '???'}`

  return [
    route,
    flight.flight_number,
  ]
    .filter(Boolean)
    .join(
      ' ',
    )
}


const filtersActive =
  computed(
    () =>
      search.value.trim() !== '' ||
      selectedYear.value !== 'all' ||
      selectedFlightType.value !== 'all' ||
      selectedAirline.value !== 'all' ||
      selectedAircraft.value !== 'all' ||
      sortOrder.value !==
        (
          plannedMode.value
            ? 'oldest'
            : 'newest'
        ),
  )


watch(
  plannedMode,

  (planned) => {
    sortOrder.value =
      planned
        ? 'oldest'
        : 'newest'

    if (
      planned &&
      !calendarMonth.value
    ) {
      calendarMonth.value =
        nearestFilteredFlight.value
          ?.departure_date
          ?.slice(
            0,
            7,
          ) ??
        ''
    }
  },

  {
    immediate: true,
  },
)


watch(
  () =>
    nearestFilteredFlight.value
      ?.departure_date
      ?.slice(
        0,
        7,
      ) ??
    '',

  (month) => {
    if (
      month &&
      !calendarMonth.value
    ) {
      calendarMonth.value =
        month
    }
  },
)


watch(
  () =>
    props.initialAircraftFilterKey,

  (value) => {
    selectedAircraft.value =
      value ??
      'all'
  },
)


watch(
  selectedAircraft,

  (value) => {
    emit(
      'aircraftFilterChanged',
      value ===
        'all'
        ? null
        : value,
    )
  },
)


watch(
  filteredFlights,

  (flights) => {
    emit(
      'filtered',
      flights,
    )
  },

  {
    immediate: true,
  },
)


function resetFilters(): void {
  search.value =
    ''

  selectedYear.value =
    'all'

  selectedFlightType.value =
    'all'

  selectedAirline.value =
    'all'

  selectedAircraft.value =
    'all'

  sortOrder.value =
    plannedMode.value
      ? 'oldest'
      : 'newest'
}


function formatNumber(
  value: number,
): string {
  return new Intl.NumberFormat(
    'pl-PL',
  ).format(value)
}


function formatPlannedDuration(
  seconds: number,
): string {
  const minutes =
    Math.floor(
      seconds /
      60,
    )

  const hours =
    Math.floor(
      minutes /
      60,
    )

  const rest =
    minutes %
    60

  return `${formatNumber(hours)} h ${rest} min`
}


function formatPlannedDate(
  value:
    string | null | undefined,
): string {
  if (!value) {
    return ''
  }

  const date =
    new Date(
      `${value}T12:00:00`,
    )

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return value
  }

  return new Intl.DateTimeFormat(
    'pl-PL',
    {
      day: 'numeric',
      month: 'long',
    },
  ).format(
    date,
  )
}
</script>


<template>
  <section class="flights-panel">

    <div class="filters">

      <div class="search-row">

        <input
          v-model="search"
          type="search"
          class="filter-control search-input"
          placeholder="Szukaj lotu..."
        >


        <button
          type="button"
          class="reset-button"
          :disabled="!filtersActive"
          title="Wyczyść wszystkie filtry"
          @click="resetFilters"
        >
          Reset
        </button>

      </div>


      <div class="filter-row">

        <select
          v-model="selectedYear"
          class="filter-control"
        >
          <option value="all">
            Wszystkie lata
          </option>

          <option
            v-for="year in years"
            :key="year"
            :value="year"
          >
            {{ year }}
          </option>
        </select>


        <select
          v-model="sortOrder"
          class="filter-control"
        >
          <option value="newest">
            Najnowsze
          </option>

          <option value="oldest">
            Najstarsze
          </option>
        </select>

      </div>


      <div class="filter-row">

        <select
          v-model="selectedFlightType"
          class="filter-control"
        >
          <option value="all">
            Wszystkie typy
          </option>

          <option value="domestic">
            Krajowe
          </option>

          <option value="continental">
            Kontynentalne
          </option>

          <option value="intercontinental">
            Międzykontynentalne
          </option>

          <option value="other">
            Widokowe / inne
          </option>
        </select>


        <select
          v-model="selectedAirline"
          class="filter-control"
        >
          <option value="all">
            Wszystkie linie
          </option>

          <option
            v-for="airline in airlines"
            :key="airline.key"
            :value="airline.key"
          >
            {{ airline.name }}
          </option>
        </select>

      </div>


      <div class="filter-row filter-row--single">

        <select
          v-model="selectedAircraft"
          class="filter-control"
        >
          <option value="all">
            Wszystkie samoloty
          </option>

          <option
            v-for="aircraftType in aircraftTypes"
            :key="aircraftType.key"
            :value="aircraftType.key"
          >
            {{ aircraftType.name }}
          </option>
        </select>

      </div>

    </div>


    <template v-if="plannedMode">
      <section class="planned-summary">
        <div class="planned-summary__title">
          <span>Plany lotnicze</span>
          <strong>
            {{ formatNumber(filteredFlights.length) }}
            lotów
          </strong>
        </div>

        <div class="planned-summary__stats">
          <div>
            <strong>
              {{ formatNumber(Math.round(plannedDistance)) }}
            </strong>
            <span>km</span>
          </div>

          <div>
            <strong>
              {{ formatPlannedDuration(plannedDuration) }}
            </strong>
            <span>planowanego czasu</span>
          </div>
        </div>
      </section>

      <div class="planned-view-switch">
        <button
          type="button"
          :class="{
            active:
              plannedView === 'list',
          }"
          @click="plannedView = 'list'"
        >
          Lista
        </button>

        <button
          type="button"
          :class="{
            active:
              plannedView === 'calendar',
          }"
          @click="
            plannedView = 'calendar';
            goToNearestPlannedMonth()
          "
        >
          Kalendarz
        </button>
      </div>

      <section
        v-if="plannedView === 'calendar'"
        class="planned-calendar"
      >
        <header class="planned-calendar__header">
          <button
            type="button"
            class="planned-calendar__nav"
            title="Poprzedni miesiąc"
            @click="changeCalendarMonth(-1)"
          >
            ‹
          </button>

          <strong>
            {{ calendarMonthLabel }}
          </strong>

          <button
            type="button"
            class="planned-calendar__nav"
            title="Następny miesiąc"
            @click="changeCalendarMonth(1)"
          >
            ›
          </button>
        </header>

        <button
          v-if="
            nearestFilteredFlight &&
            calendarMonthKey !==
              nearestFilteredFlight.departure_date?.slice(0, 7)
          "
          type="button"
          class="planned-calendar__nearest"
          @click="goToNearestPlannedMonth"
        >
          Wróć do najbliższego lotu
        </button>

        <div class="planned-calendar__weekdays">
          <span>Pn</span>
          <span>Wt</span>
          <span>Śr</span>
          <span>Cz</span>
          <span>Pt</span>
          <span>So</span>
          <span>Nd</span>
        </div>

        <div class="planned-calendar__grid">
          <div
            v-for="day in calendarDays"
            :key="day.key"
            class="planned-calendar__day"
            :class="{
              'planned-calendar__day--empty':
                !day.currentMonth,
              'planned-calendar__day--has-flight':
                day.flights.length > 0,
            }"
          >
            <span
              v-if="day.day !== null"
              class="planned-calendar__number"
            >
              {{ day.day }}
            </span>

            <div
              v-if="day.flights.length"
              class="planned-calendar__flights"
            >
              <button
                v-for="flight in day.flights"
                :key="flight.id"
                type="button"
                class="planned-calendar__flight"
                :title="
                  [
                    flight.airline_name,
                    flight.flight_number,
                    `${flight.departure_iata} → ${flight.arrival_iata}`,
                    countdownLabel(flight),
                  ].filter(Boolean).join(' · ')
                "
                @click="emit('flight', flight)"
              >
                {{ calendarFlightLabel(flight) }}
              </button>
            </div>
          </div>
        </div>
      </section>

      <template v-else>
      <button
        v-if="nearestFilteredFlight"
        type="button"
        class="next-planned-card"
        @click="emit('flight', nearestFilteredFlight)"
      >
        <span class="next-planned-card__label">
          Najbliższy lot
        </span>

        <strong>
          {{ formatPlannedDate(nearestFilteredFlight.departure_date) }}
          ·
          {{ nearestFilteredFlight.departure_iata }}
          →
          {{ nearestFilteredFlight.arrival_iata }}
        </strong>

        <span>
          {{
            [
              nearestFilteredFlight.airline_name,
              nearestFilteredFlight.flight_number,
            ].filter(Boolean).join(' ')
          }}
          ·
          {{ countdownLabel(nearestFilteredFlight) }}
        </span>
      </button>

      <div
        v-if="
          filteredFlights.length ===
          0
        "
        class="empty"
      >
        Brak zaplanowanych lotów spełniających wybrane kryteria.
      </div>

      <section
        v-for="group in flightGroups"
        :key="group.key"
        class="planned-month"
      >
        <h3>
          {{ group.label }}
        </h3>

        <div
          v-for="flight in group.flights"
          :key="flight.id"
          class="planned-flight-wrap"
        >
          <div class="planned-flight-meta">
            <span>
              {{ countdownLabel(flight) }}
            </span>
          </div>

          <FlightCard
            :departure-code="flight.departure_iata"
            :departure-city="flight.departure_city"
            :departure-airport-name="flight.departure_airport_name"
            :departure-country-code="flight.departure_country_code"
            :arrival-code="flight.arrival_iata"
            :arrival-city="flight.arrival_city"
            :arrival-airport-name="flight.arrival_airport_name"
            :arrival-country-code="flight.arrival_country_code"
            :departure-date="flight.departure_date"
            :departure-time="flight.departure_time"
            :arrival-time="flight.arrival_time"
            :flight-number="flight.flight_number"
            :airline-name="flight.airline_name"
            :aircraft-name="flight.aircraft_name"
            :distance-km="flight.distance_km"
            :duration-seconds="flight.duration_seconds"
            :planned="true"
            :active="activeFlightId === flight.id"
            @click="emit('flight', flight)"
          />
        </div>
      </section>
      </template>
    </template>

    <template v-else>
      <section class="result-count">
        <strong>
          {{ formatNumber(filteredFlights.length) }}
        </strong>

        <span>
          lotów
        </span>
      </section>

      <div
        v-if="
          filteredFlights.length ===
          0
        "
        class="empty"
      >
        Brak lotów spełniających wybrane kryteria.
      </div>

      <section
        v-for="group in flightGroups"
        :key="group.key"
        class="planned-month"
      >
        <h3>
          {{ group.label }}
        </h3>

        <FlightCard
          v-for="flight in group.flights"
          :key="flight.id"
          :departure-code="flight.departure_iata"
          :departure-city="flight.departure_city"
          :departure-airport-name="flight.departure_airport_name"
          :departure-country-code="flight.departure_country_code"
          :arrival-code="flight.arrival_iata"
          :arrival-city="flight.arrival_city"
          :arrival-airport-name="flight.arrival_airport_name"
          :arrival-country-code="flight.arrival_country_code"
          :departure-date="flight.departure_date"
          :departure-time="flight.departure_time"
          :arrival-time="flight.arrival_time"
          :flight-number="flight.flight_number"
          :airline-name="flight.airline_name"
          :aircraft-name="flight.aircraft_name"
          :distance-km="flight.distance_km"
          :duration-seconds="flight.duration_seconds"
          :planned="isPlannedFlight(flight)"
          :active="activeFlightId === flight.id"
          @click="emit('flight', flight)"
        />
      </section>
    </template>

  </section>
</template>


<style scoped>
.flights-panel {
  margin-top: 14px;
}


.filters {
  margin-bottom: 13px;
}


.search-row {
  display: grid;

  grid-template-columns:
    minmax(0, 1fr)
    auto;

  align-items: center;

  gap: 6px;

  margin-top: 4px;
}


.filter-control {
  width: 100%;
  height: 32px;

  padding:
    5px 9px;

  border:
    1px solid #d8d8d8;

  border-radius: 7px;

  background: #fff;

  color: #333;

  font-size: 11px;
}


.search-input {
  min-width: 0;
}


.reset-button {
  height: 32px;

  padding:
    0 10px;

  border:
    1px solid #d8d8d8;

  border-radius: 7px;

  background: #f5f5f5;

  color: #555;

  cursor: pointer;

  font-size: 11px;
  font-weight: 650;

  transition:
    background 0.15s ease,
    border-color 0.15s ease,
    color 0.15s ease;
}


.reset-button:hover:not(:disabled) {
  border-color:
    rgba(11, 45, 92, 0.28);

  background:
    rgba(11, 45, 92, 0.07);

  color: #0b2d5c;
}


.reset-button:disabled {
  opacity: 0.42;

  cursor: default;
}


.filter-row {
  display: grid;

  grid-template-columns:
    1fr 1fr;

  gap: 6px;

  margin-top: 6px;
}


.filter-row--single {
  grid-template-columns:
    1fr;
}


.result-count {
  display: flex;

  align-items: baseline;

  gap: 6px;

  margin:
    5px 0 11px;

  padding:
    10px 12px;

  background: #f4f4f4;

  border-radius: 9px;
}


.result-count strong {
  font-size: 30px;

  line-height: 1;
}


.result-count span {
  color: #666;

  font-size: 12px;
}


.empty {
  padding:
    20px 5px;

  color: #777;

  font-size: 11px;

  text-align: center;
}

.planned-view-switch {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4px;
  margin: 6px 0 7px;
  padding: 2px;
  border-radius: 8px;
  background: #eef0f3;
}

.planned-view-switch button {
  height: 27px;
  padding: 0 8px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: #6d7681;
  cursor: pointer;
  font-size: 10px;
  font-weight: 750;
}

.planned-view-switch button.active {
  background: #fff;
  color: #0b2d5c;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.planned-calendar {
  margin: 3px 0 8px;
  padding: 7px;
  border: 1px solid #e0e4e8;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.96);
}

.planned-calendar__header {
  display: grid;
  grid-template-columns: 28px 1fr 28px;
  align-items: center;
  gap: 4px;
  margin-bottom: 5px;
}

.planned-calendar__header strong {
  color: #3f4d5c;
  font-size: 11px;
  text-align: center;
}

.planned-calendar__nav {
  display: grid;
  width: 28px;
  height: 25px;
  place-items: center;
  padding: 0;
  border: 1px solid #e0e4e8;
  border-radius: 6px;
  background: #f7f8f9;
  color: #687483;
  cursor: pointer;
  font-size: 18px;
  line-height: 1;
}

.planned-calendar__nav:hover {
  background: #eef1f4;
  color: #0b2d5c;
}

.planned-calendar__nearest {
  display: block;
  margin: -1px auto 5px;
  padding: 2px 7px;
  border: 0;
  background: transparent;
  color: #687483;
  cursor: pointer;
  font-size: 9px;
  text-decoration: underline;
  text-underline-offset: 2px;
}

.planned-calendar__weekdays,
.planned-calendar__grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 2px;
}

.planned-calendar__weekdays {
  margin-bottom: 2px;
}

.planned-calendar__weekdays span {
  color: #8a929c;
  font-size: 8px;
  font-weight: 750;
  text-align: center;
}

.planned-calendar__day {
  position: relative;
  min-height: 48px;
  padding: 3px;
  overflow: hidden;
  border: 1px solid #edf0f2;
  border-radius: 5px;
  background: #fafbfc;
}

.planned-calendar__day--empty {
  border-color: transparent;
  background: transparent;
}

.planned-calendar__day--has-flight {
  border-color: rgba(124, 58, 237, 0.18);
  background: rgba(124, 58, 237, 0.035);
}

.planned-calendar__number {
  display: block;
  margin-bottom: 2px;
  color: #65717e;
  font-size: 8px;
  font-weight: 750;
}

.planned-calendar__flights {
  display: grid;
  gap: 2px;
}

.planned-calendar__flight {
  width: 100%;
  min-width: 0;
  padding: 2px 3px;
  overflow: hidden;
  border: 0;
  border-radius: 3px;
  background: rgba(124, 58, 237, 0.11);
  color: #5f42a8;
  cursor: pointer;
  font-size: 7px;
  font-weight: 700;
  line-height: 1.15;
  text-align: left;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.planned-calendar__flight:hover {
  background: rgba(124, 58, 237, 0.2);
  color: #4f2f9f;
}

.planned-summary {
  margin: 2px 0 5px;
  padding: 4px 8px;
  border: 1px solid rgba(124, 58, 237, 0.14);
  border-radius: 9px;
  background: linear-gradient(
    135deg,
    rgba(124, 58, 237, 0.07),
    rgba(255, 255, 255, 0.96)
  );
}

.planned-summary__title {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 5px;
}

.planned-summary__title span {
  color: #687483;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.planned-summary__title strong {
  color: #0b2d5c;
  font-size: 13px;
}

.planned-summary__stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 5px;
  margin-top: 2px;
}

.planned-summary__stats div {
  display: grid;
  gap: 0;
}

.planned-summary__stats strong {
  color: #26384b;
  font-size: 12px;
}

.planned-summary__stats span {
  color: #7b8591;
  font-size: 10px;
}

.next-planned-card {
  display: grid;
  width: 100%;
  gap: 1px;
  margin-bottom: 7px;
  padding: 6px 9px;
  border: 1px solid rgba(124, 58, 237, 0.22);
  border-radius: 10px;
  background: #fff;
  color: #334155;
  cursor: pointer;
  text-align: left;
  box-shadow: 0 3px 12px rgba(69, 48, 120, 0.08);
}

.next-planned-card:hover {
  border-color: rgba(124, 58, 237, 0.42);
  background: rgba(124, 58, 237, 0.035);
}

.next-planned-card__label {
  color: #687483;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.045em;
  text-transform: uppercase;
}

.next-planned-card strong {
  color: #0b2d5c;
  font-size: 13px;
}

.next-planned-card > span:last-child {
  color: #687483;
  font-size: 11px;
}

.planned-month {
  margin-top: 11px;
}

.planned-month h3 {
  margin: 0 0 5px;
  padding: 0 2px 4px;
  border-bottom: 1px solid #ececf2;
  color: #475569;
  font-size: 11px;
  font-weight: 800;
}

.planned-flight-wrap {
  position: relative;
}

.planned-flight-meta {
  display: flex;
  justify-content: flex-end;
  margin: 0 3px -2px;
}

.planned-flight-meta span {
  color: #7c3aed;
  font-size: 10px;
  font-weight: 750;
}

</style>