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
}>()


const emit = defineEmits<{
  flight: [flight: Flight]
  filtered: [flights: Flight[]]
}>()


const search =
  ref('')


const selectedYear =
  ref('all')


const selectedFlightType =
  ref('all')


const selectedAirline =
  ref('all')


const sortOrder =
  ref<'newest' | 'oldest'>(
    'newest',
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


const filtersActive =
  computed(
    () =>
      search.value.trim() !== '' ||
      selectedYear.value !== 'all' ||
      selectedFlightType.value !== 'all' ||
      selectedAirline.value !== 'all' ||
      sortOrder.value !== 'newest',
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

  sortOrder.value =
    'newest'
}


function formatNumber(
  value: number,
): string {
  return new Intl.NumberFormat(
    undefined,
  ).format(value)
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

    </div>


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


    <FlightCard
      v-for="
        flight in filteredFlights
      "
      :key="flight.id"

      :departure-code="
        flight.departure_iata
      "

      :departure-city="
        flight.departure_city
      "

      :departure-airport-name="
        flight.departure_airport_name
      "

      :departure-country-code="
        flight.departure_country_code
      "

      :arrival-code="
        flight.arrival_iata
      "

      :arrival-city="
        flight.arrival_city
      "

      :arrival-airport-name="
        flight.arrival_airport_name
      "

      :arrival-country-code="
        flight.arrival_country_code
      "

      :departure-date="
        flight.departure_date
      "

      :departure-time="
        flight.departure_time
      "

      :arrival-time="
        flight.arrival_time
      "

      :flight-number="
        flight.flight_number
      "

      :airline-name="
        flight.airline_name
      "

      :aircraft-name="
        flight.aircraft_name
      "

      :distance-km="
        flight.distance_km
      "

      :duration-seconds="
        flight.duration_seconds
      "

      :planned="
        isPlannedFlight(
          flight,
        )
      "

      :active="
        activeFlightId ===
        flight.id
      "

      @click="
        emit(
          'flight',
          flight,
        )
      "
    />

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

  margin-top: 6px;
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

  font-size: 10px;
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


.result-count {
  display: flex;

  align-items: baseline;

  gap: 8px;

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
</style>