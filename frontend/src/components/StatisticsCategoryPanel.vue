<script setup lang="ts">
import {
  computed,
  ref,
} from 'vue'

import 'flag-icons/css/flag-icons.min.css'

import type {
  Flight,
} from '../types/flight'

type SectionType =
  | 'airlines'
  | 'aircraft'
  | 'routes'
  | 'countries'

type SortDirection =
  | 'asc'
  | 'desc'

type AirlineSort =
  | 'name'
  | 'flights'
  | 'share'

type AircraftSort =
  | 'name'
  | 'flights'
  | 'share'

type RouteSort =
  | 'route'
  | 'cities'
  | 'flights'
  | 'distance'
  | 'totalDistance'

type CountrySort =
  | 'name'
  | 'operations'
  | 'share'

type AircraftView =
  | 'types'
  | 'families'

interface AirlineRow {
  key: string
  name: string
  flights: number
  share: number
}

interface AircraftRow {
  key: string
  name: string
  flights: number
  share: number
}

interface RouteRow {
  key: string
  departureCode: string | null
  departureName: string
  departureCity: string
  departureCountryCode: string | null
  arrivalCode: string | null
  arrivalName: string
  arrivalCity: string
  arrivalCountryCode: string | null
  flights: number
  distanceKm: number
  totalDistanceKm: number
}

interface CountryRow {
  key: string
  code: string | null
  name: string
  operations: number
  share: number
}

const props = defineProps<{
  flights: Flight[]
  section: SectionType
}>()

const emit = defineEmits<{
  close: []
  route: [
    departureCode: string | null,
    arrivalCode: string | null,
  ]
  aircraft: [
    key: string,
    name: string,
  ]
}>()

const totalDistance =
  computed(
    () =>
      props.flights.reduce(
        (sum, flight) =>
          sum +
          (
            flight.distance_km ??
            0
          ),
        0,
      ),
  )

const totalDuration =
  computed(
    () =>
      props.flights.reduce(
        (sum, flight) =>
          sum +
          (
            flight.duration_seconds ??
            0
          ),
        0,
      ),
  )

const title =
  computed(
    () => {
      switch (
        props.section
      ) {
        case 'aircraft':
          return 'Typy samolotów'

        case 'routes':
          return 'Trasy'

        case 'countries':
          return 'Państwa'

        default:
          return 'Linie lotnicze'
      }
    },
  )

const subtitle =
  computed(
    () => {
      switch (
        props.section
      ) {
        case 'aircraft':
          return 'Typy i rodziny samolotów wykorzystane w Twoich lotach.'

        case 'routes':
          return 'Kierunkowe połączenia pomiędzy odwiedzonymi lotniskami.'

        case 'countries':
          return 'Liczba operacji lotniczych wykonanych w poszczególnych państwach.'

        default:
          return 'Linie lotnicze wykorzystane w Twoich podróżach.'
      }
    },
  )

/*
|--------------------------------------------------------------------------
| Linie lotnicze
|--------------------------------------------------------------------------
*/

const airlineSort =
  ref<AirlineSort>(
    'flights',
  )

const airlineDirection =
  ref<SortDirection>(
    'desc',
  )

const airlineRows =
  computed<AirlineRow[]>(
    () => {
      const result =
        new Map<
          string,
          AirlineRow
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
          flight.airline_id !==
          null
            ? `id:${flight.airline_id}`
            : `name:${flight.airline_name}`

        const existing =
          result.get(
            key,
          )

        if (existing) {
          existing.flights++

          continue
        }

        result.set(
          key,
          {
            key,
            name:
              flight.airline_name,
            flights:
              1,
            share:
              0,
          },
        )
      }

      return [...result.values()]
        .map(
          (row) => ({
            ...row,
            share:
              props.flights.length >
              0
                ? (
                    row.flights /
                    props.flights.length
                  ) * 100
                : 0,
          }),
        )
    },
  )

const sortedAirlines =
  computed(
    () => {
      const result =
        [...airlineRows.value]

      result.sort(
        (a, b) => {
          let comparison =
            0

          switch (
            airlineSort.value
          ) {
            case 'name':
              comparison =
                a.name.localeCompare(
                  b.name,
                  undefined,
                  {
                    sensitivity:
                      'base',
                  },
                )
              break

            case 'share':
              comparison =
                a.share -
                b.share
              break

            default:
              comparison =
                a.flights -
                b.flights
          }

          return (
            airlineDirection.value ===
              'asc'
              ? comparison
              : -comparison
          )
        },
      )

      return result
    },
  )

function changeAirlineSort(
  field:
    AirlineSort,
): void {
  if (
    airlineSort.value ===
    field
  ) {
    airlineDirection.value =
      airlineDirection.value ===
        'asc'
        ? 'desc'
        : 'asc'

    return
  }

  airlineSort.value =
    field

  airlineDirection.value =
    field ===
      'name'
      ? 'asc'
      : 'desc'
}

/*
|--------------------------------------------------------------------------
| Typy / rodziny samolotów
|--------------------------------------------------------------------------
*/

const aircraftView =
  ref<AircraftView>(
    'types',
  )

const aircraftSort =
  ref<AircraftSort>(
    'flights',
  )

const aircraftDirection =
  ref<SortDirection>(
    'desc',
  )

function detectAircraftFamily(
  name: string,
): string {
  const normalized =
    name
      .trim()
      .toLowerCase()

  const rules:
    Array<
      [
        string,
        string[],
      ]
    > = [
      ['Airbus', ['airbus']],
      ['Boeing', ['boeing']],
      ['ATR', ['atr ']],
      ['Embraer', ['embraer']],
      ['Bombardier', ['bombardier', 'canadair', 'crj']],
      ['De Havilland', ['de havilland', 'dash 8', 'dhc-']],
      ['McDonnell Douglas', ['mcdonnell douglas', 'md-', 'dc-']],
      ['Fokker', ['fokker']],
      ['Saab', ['saab']],
      ['Cessna', ['cessna']],
      ['Beechcraft', ['beechcraft', 'beech ']],
      ['Pilatus', ['pilatus']],
      ['Antonov', ['antonov']],
      ['Ilyushin', ['ilyushin', 'il-']],
      ['Tupolev', ['tupolev', 'tu-']],
      ['Sukhoi', ['sukhoi']],
      ['COMAC', ['comac']],
      ['Lockheed', ['lockheed']],
      ['Dornier', ['dornier']],
    ]

  for (
    const [
      family,
      keywords,
    ]
    of rules
  ) {
    if (
      keywords.some(
        (keyword) =>
          normalized.includes(
            keyword,
          ),
      )
    ) {
      return family
    }
  }

  const firstWord =
    name
      .trim()
      .split(
        /\s+/,
      )[0]

  return (
    firstWord ||
    'Inne'
  )
}

const aircraftTypeRows =
  computed<AircraftRow[]>(
    () => {
      const result =
        new Map<
          string,
          AircraftRow
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
          flight.aircraft_type_id !==
          null
            ? `id:${flight.aircraft_type_id}`
            : `name:${flight.aircraft_name}`

        const existing =
          result.get(
            key,
          )

        if (existing) {
          existing.flights++

          continue
        }

        result.set(
          key,
          {
            key,
            name:
              flight.aircraft_name,
            flights:
              1,
            share:
              0,
          },
        )
      }

      return [...result.values()]
        .map(
          (row) => ({
            ...row,
            share:
              props.flights.length >
              0
                ? (
                    row.flights /
                    props.flights.length
                  ) * 100
                : 0,
          }),
        )
    },
  )

const aircraftFamilyRows =
  computed<AircraftRow[]>(
    () => {
      const result =
        new Map<
          string,
          AircraftRow
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

        const family =
          detectAircraftFamily(
            flight.aircraft_name,
          )

        const key =
          family.toLowerCase()

        const existing =
          result.get(
            key,
          )

        if (existing) {
          existing.flights++

          continue
        }

        result.set(
          key,
          {
            key,
            name:
              family,
            flights:
              1,
            share:
              0,
          },
        )
      }

      return [...result.values()]
        .map(
          (row) => ({
            ...row,
            share:
              props.flights.length >
              0
                ? (
                    row.flights /
                    props.flights.length
                  ) * 100
                : 0,
          }),
        )
    },
  )

const currentAircraftRows =
  computed(
    () =>
      aircraftView.value ===
        'types'
        ? aircraftTypeRows.value
        : aircraftFamilyRows.value,
  )

const sortedAircraft =
  computed(
    () => {
      const result =
        [...currentAircraftRows.value]

      result.sort(
        (a, b) => {
          let comparison =
            0

          switch (
            aircraftSort.value
          ) {
            case 'name':
              comparison =
                a.name.localeCompare(
                  b.name,
                  undefined,
                  {
                    sensitivity:
                      'base',
                  },
                )
              break

            case 'share':
              comparison =
                a.share -
                b.share
              break

            default:
              comparison =
                a.flights -
                b.flights
          }

          return (
            aircraftDirection.value ===
              'asc'
              ? comparison
              : -comparison
          )
        },
      )

      return result
    },
  )

function changeAircraftSort(
  field:
    AircraftSort,
): void {
  if (
    aircraftSort.value ===
    field
  ) {
    aircraftDirection.value =
      aircraftDirection.value ===
        'asc'
        ? 'desc'
        : 'asc'

    return
  }

  aircraftSort.value =
    field

  aircraftDirection.value =
    field ===
      'name'
      ? 'asc'
      : 'desc'
}

/*
|--------------------------------------------------------------------------
| Trasy
|--------------------------------------------------------------------------
*/

const routeSort =
  ref<RouteSort>(
    'flights',
  )

const routeDirection =
  ref<SortDirection>(
    'desc',
  )

const routeRows =
  computed<RouteRow[]>(
    () => {
      const result =
        new Map<
          string,
          RouteRow
        >()

      for (
        const flight
        of props.flights
      ) {
        const key =
          `${flight.departure_airport_id}>${flight.arrival_airport_id}`

        const existing =
          result.get(
            key,
          )

        if (existing) {
          existing.flights++

          existing.totalDistanceKm +=
            flight.distance_km ??
            0

          continue
        }

        result.set(
          key,
          {
            key,
            departureCode:
              flight.departure_iata,
            departureName:
              flight.departure_airport_name,
            departureCity:
              flight.departure_city,
            departureCountryCode:
              flight.departure_country_code,
            arrivalCode:
              flight.arrival_iata,
            arrivalName:
              flight.arrival_airport_name,
            arrivalCity:
              flight.arrival_city,
            arrivalCountryCode:
              flight.arrival_country_code,
            flights:
              1,
            distanceKm:
              flight.distance_km ??
              0,
            totalDistanceKm:
              flight.distance_km ??
              0,
          },
        )
      }

      return [...result.values()]
    },
  )

const sortedRoutes =
  computed(
    () => {
      const result =
        [...routeRows.value]

      result.sort(
        (a, b) => {
          let comparison =
            0

          switch (
            routeSort.value
          ) {
            case 'route':
              comparison =
                `${a.departureCode ?? ''}-${a.arrivalCode ?? ''}`
                  .localeCompare(
                    `${b.departureCode ?? ''}-${b.arrivalCode ?? ''}`,
                  )
              break

            case 'cities':
              comparison =
                `${a.departureCity}-${a.arrivalCity}`
                  .localeCompare(
                    `${b.departureCity}-${b.arrivalCity}`,
                    undefined,
                    {
                      sensitivity:
                        'base',
                    },
                  )
              break

            case 'distance':
              comparison =
                a.distanceKm -
                b.distanceKm
              break

            case 'totalDistance':
              comparison =
                a.totalDistanceKm -
                b.totalDistanceKm
              break

            default:
              comparison =
                a.flights -
                b.flights
          }

          return (
            routeDirection.value ===
              'asc'
              ? comparison
              : -comparison
          )
        },
      )

      return result
    },
  )

function changeRouteSort(
  field:
    RouteSort,
): void {
  if (
    routeSort.value ===
    field
  ) {
    routeDirection.value =
      routeDirection.value ===
        'asc'
        ? 'desc'
        : 'asc'

    return
  }

  routeSort.value =
    field

  routeDirection.value =
    (
      field ===
        'route' ||
      field ===
        'cities'
    )
      ? 'asc'
      : 'desc'
}

/*
|--------------------------------------------------------------------------
| Państwa
|--------------------------------------------------------------------------
*/

const countrySort =
  ref<CountrySort>(
    'operations',
  )

const countryDirection =
  ref<SortDirection>(
    'desc',
  )

function localizedCountryName(
  code:
    string | null,
  fallback:
    string,
): string {
  if (!code) {
    return fallback
  }

  const browserLanguage =
    navigator.language
      .toLowerCase()

  const locale =
    browserLanguage.startsWith(
      'en',
    )
      ? 'en'
      : 'pl'

  try {
    const displayNames =
      new Intl.DisplayNames(
        [locale],
        {
          type:
            'region',
        },
      )

    return (
      displayNames.of(
        code.toUpperCase(),
      ) ??
      fallback
    )
  } catch {
    return fallback
  }
}

const countryRows =
  computed<CountryRow[]>(
    () => {
      const result =
        new Map<
          string,
          CountryRow
        >()

    function addOperation(
    code:
        string | null,
    fallbackName:
        string | null,
    ): void {
        const normalizedCode =
          code
            ?.trim()
            .toUpperCase() ??
          null

        const key =
        normalizedCode ??
        fallbackName ??
        ''

        if (!key) {
          return
        }

        const existing =
          result.get(
            key,
          )

        if (existing) {
          existing.operations++

          return
        }

        result.set(
          key,
          {
            key,
            code:
              normalizedCode,
            name:
            localizedCountryName(
            normalizedCode,
            fallbackName ?? '',
            ),
            operations:
              1,
            share:
              0,
          },
        )
      }

      for (
        const flight
        of props.flights
      ) {
        addOperation(
          flight.departure_country_code,
          flight.departure_country,
        )

        addOperation(
          flight.arrival_country_code,
          flight.arrival_country,
        )
      }

      const totalOperations =
        props.flights.length *
        2

      return [...result.values()]
        .map(
          (row) => ({
            ...row,
            share:
              totalOperations >
              0
                ? (
                    row.operations /
                    totalOperations
                  ) * 100
                : 0,
          }),
        )
    },
  )

const sortedCountries =
  computed(
    () => {
      const result =
        [...countryRows.value]

      result.sort(
        (a, b) => {
          let comparison =
            0

          switch (
            countrySort.value
          ) {
            case 'name':
              comparison =
                a.name.localeCompare(
                  b.name,
                  undefined,
                  {
                    sensitivity:
                      'base',
                  },
                )
              break

            case 'share':
              comparison =
                a.share -
                b.share
              break

            default:
              comparison =
                a.operations -
                b.operations
          }

          return (
            countryDirection.value ===
              'asc'
              ? comparison
              : -comparison
          )
        },
      )

      return result
    },
  )

function changeCountrySort(
  field:
    CountrySort,
): void {
  if (
    countrySort.value ===
    field
  ) {
    countryDirection.value =
      countryDirection.value ===
        'asc'
        ? 'desc'
        : 'asc'

    return
  }

  countrySort.value =
    field

  countryDirection.value =
    field ===
      'name'
      ? 'asc'
      : 'desc'
}

/*
|--------------------------------------------------------------------------
| Wspólne
|--------------------------------------------------------------------------
*/

function sortMark(
  active:
    string,
  field:
    string,
  direction:
    SortDirection,
): string {
  if (
    active !==
    field
  ) {
    return ''
  }

  return (
    direction ===
      'asc'
      ? '↑'
      : '↓'
  )
}

function formatNumber(
  value: number,
): string {
  return new Intl.NumberFormat(
    undefined,
  ).format(
    Math.round(
      value,
    ),
  )
}

function formatShare(
  value: number,
): string {
  return new Intl.NumberFormat(
    undefined,
    {
      minimumFractionDigits:
        1,
      maximumFractionDigits:
        1,
    },
  ).format(
    value,
  )
}

function formatDuration(
  seconds: number,
): string {
  const totalMinutes =
    Math.floor(
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

function flagClass(
  code:
    string | null,
): string | null {
  if (!code) {
    return null
  }

  const normalized =
    code
      .trim()
      .toLowerCase()

  if (
    normalized.length !==
    2
  ) {
    return null
  }

  return `fi fi-${normalized}`
}
</script>

<template>
  <aside class="category-panel">
    <header class="panel-header">
      <div class="title-area">
        <div class="title-icon">
          <svg
            v-if="
              section ===
                'airlines' ||
              section ===
                'aircraft'
            "
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path d="M22 16.5 13.5 12V5.5a1.5 1.5 0 0 0-3 0V12L2 16.5v2l8.5-2.5v4.5L8 22v1.5l4-1 4 1V22l-2.5-1.5V16l8.5 2.5z" />
          </svg>

          <svg
            v-else-if="
              section ===
              'countries'
            "
            viewBox="0 0 24 24"
            aria-hidden="true"
            class="stroke-icon"
          >
            <circle cx="12" cy="12" r="9" />
            <path d="M3 12h18M12 3c3 3 4 6 4 9s-1 6-4 9M12 3c-3 3-4 6-4 9s1 6 4 9" />
          </svg>

          <svg
            v-else
            viewBox="0 0 24 24"
            aria-hidden="true"
            class="stroke-icon"
          >
            <circle cx="6" cy="17" r="2" />
            <circle cx="18" cy="7" r="2" />
            <path d="M8 16c3-1 4-6 8-8" />
          </svg>
        </div>

        <div>
          <div class="eyebrow">
            Statystyki
          </div>

          <h2>
            {{ title }}
          </h2>

          <p>
            {{ subtitle }}
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

    <section class="summary">
      <div>
        <strong>
          {{
            formatNumber(
              section ===
                  'airlines'
                ? airlineRows.length
                : section ===
                    'aircraft'
                  ? aircraftTypeRows.length
                  : section ===
                      'countries'
                    ? countryRows.length
                    : routeRows.length,
            )
          }}
        </strong>

        <span>
          {{
            section ===
                'airlines'
              ? 'linii lotniczych'
              : section ===
                  'aircraft'
                ? 'typów samolotów'
                : section ===
                    'countries'
                  ? 'państw'
                  : 'tras'
          }}
        </span>
      </div>

      <div>
        <strong>
          {{
            formatNumber(
              section ===
                  'countries'
                ? flights.length * 2
                : flights.length,
            )
          }}
        </strong>

        <span>
          {{
            section ===
                'countries'
              ? 'operacji'
              : 'lotów'
          }}
        </span>
      </div>

      <div>
        <strong>
          {{ formatNumber(totalDistance) }}
        </strong>

        <span>
          km
        </span>
      </div>

      <div>
        <strong>
          {{ formatDuration(totalDuration) }}
        </strong>

        <span>
          w powietrzu
        </span>
      </div>
    </section>

    <template
      v-if="
        section ===
        'airlines'
      "
    >
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th class="position-column">#</th>

              <th class="text-column">
                <button
                  type="button"
                  class="sort-button sort-button--left"
                  @click="changeAirlineSort('name')"
                >
                  Linia lotnicza

                  <span>
                    {{
                      sortMark(
                        airlineSort,
                        'name',
                        airlineDirection,
                      )
                    }}
                  </span>
                </button>
              </th>

              <th class="number-column">
                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="changeAirlineSort('flights')"
                >
                  Loty

                  <span>
                    {{
                      sortMark(
                        airlineSort,
                        'flights',
                        airlineDirection,
                      )
                    }}
                  </span>
                </button>
              </th>

              <th class="share-column">
                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="changeAirlineSort('share')"
                >
                  Udział

                  <span>
                    {{
                      sortMark(
                        airlineSort,
                        'share',
                        airlineDirection,
                      )
                    }}
                  </span>
                </button>
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="
                (
                  row,
                  index
                )
                in sortedAirlines
              "
              :key="row.key"
            >
              <td class="position">
                {{ index + 1 }}
              </td>

              <td class="name-cell">
                {{ row.name }}
              </td>

              <td class="number-cell">
                {{ row.flights }}
              </td>

              <td class="share-cell">
                <div class="share-value">
                  {{ formatShare(row.share) }}%
                </div>

                <div class="share-bar">
                  <div
                    class="share-bar__value"
                    :style="{
                      width:
                        `${Math.min(
                          row.share,
                          100,
                        )}%`,
                    }"
                  ></div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <template
      v-else-if="
        section ===
        'aircraft'
      "
    >
      <div class="view-tabs">
        <button
          type="button"
          :class="{
            active:
              aircraftView ===
              'types',
          }"
          @click="
            aircraftView =
              'types'
          "
        >
          Typy samolotów
        </button>

        <button
          type="button"
          :class="{
            active:
              aircraftView ===
              'families',
          }"
          @click="
            aircraftView =
              'families'
          "
        >
          Rodziny samolotów
        </button>
      </div>

      <div class="table-container table-container--with-tabs">
        <table>
          <thead>
            <tr>
              <th class="position-column">#</th>

              <th class="text-column">
                <button
                  type="button"
                  class="sort-button sort-button--left"
                  @click="changeAircraftSort('name')"
                >
                  {{
                    aircraftView ===
                      'types'
                      ? 'Typ samolotu'
                      : 'Rodzina samolotów'
                  }}

                  <span>
                    {{
                      sortMark(
                        aircraftSort,
                        'name',
                        aircraftDirection,
                      )
                    }}
                  </span>
                </button>
              </th>

              <th class="number-column">
                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="changeAircraftSort('flights')"
                >
                  Loty

                  <span>
                    {{
                      sortMark(
                        aircraftSort,
                        'flights',
                        aircraftDirection,
                      )
                    }}
                  </span>
                </button>
              </th>

              <th class="share-column">
                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="changeAircraftSort('share')"
                >
                  Udział

                  <span>
                    {{
                      sortMark(
                        aircraftSort,
                        'share',
                        aircraftDirection,
                      )
                    }}
                  </span>
                </button>
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="
                (
                  row,
                  index
                )
                in sortedAircraft
              "
              :key="
                `${aircraftView}:${row.key}`
              "
            >
              <td class="position">
                {{ index + 1 }}
              </td>

              <td class="name-cell">
                <button
                  v-if="
                    aircraftView ===
                    'types'
                  "
                  type="button"
                  class="aircraft-link"
                  title="Pokaż loty tym typem samolotu na mapie"
                  @click="
                    emit(
                      'aircraft',
                      row.key,
                      row.name,
                    )
                  "
                >
                  {{ row.name }}
                </button>

                <span v-else>
                  {{ row.name }}
                </span>
              </td>

              <td class="number-cell">
                {{ row.flights }}
              </td>

              <td class="share-cell">
                <div class="share-value">
                  {{ formatShare(row.share) }}%
                </div>

                <div class="share-bar">
                  <div
                    class="share-bar__value"
                    :style="{
                      width:
                        `${Math.min(
                          row.share,
                          100,
                        )}%`,
                    }"
                  ></div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <template
      v-else-if="
        section ===
        'countries'
      "
    >
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th class="position-column">#</th>

              <th class="flag-column"></th>

              <th class="text-column">
                <button
                  type="button"
                  class="sort-button sort-button--left"
                  @click="changeCountrySort('name')"
                >
                  Państwo

                  <span>
                    {{
                      sortMark(
                        countrySort,
                        'name',
                        countryDirection,
                      )
                    }}
                  </span>
                </button>
              </th>

              <th class="number-column">
                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="changeCountrySort('operations')"
                >
                  Operacje

                  <span>
                    {{
                      sortMark(
                        countrySort,
                        'operations',
                        countryDirection,
                      )
                    }}
                  </span>
                </button>
              </th>

              <th class="share-column">
                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="changeCountrySort('share')"
                >
                  Udział operacji

                  <span>
                    {{
                      sortMark(
                        countrySort,
                        'share',
                        countryDirection,
                      )
                    }}
                  </span>
                </button>
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="
                (
                  row,
                  index
                )
                in sortedCountries
              "
              :key="row.key"
            >
              <td class="position">
                {{ index + 1 }}
              </td>

              <td class="flag-cell">
                <span
                  v-if="flagClass(row.code)"
                  :class="flagClass(row.code)!"
                  class="country-flag"
                ></span>
              </td>

              <td class="name-cell">
                {{ row.name }}
              </td>

              <td class="number-cell">
                {{ row.operations }}
              </td>

              <td class="share-cell">
                <div class="share-value">
                  {{ formatShare(row.share) }}%
                </div>

                <div class="share-bar">
                  <div
                    class="share-bar__value"
                    :style="{
                      width:
                        `${Math.min(
                          row.share,
                          100,
                        )}%`,
                    }"
                  ></div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <template v-else>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th class="position-column">#</th>

              <th>
                <button
                  type="button"
                  class="sort-button sort-button--left"
                  @click="changeRouteSort('route')"
                >
                  Trasa

                  <span>
                    {{
                      sortMark(
                        routeSort,
                        'route',
                        routeDirection,
                      )
                    }}
                  </span>
                </button>
              </th>

              <th class="cities-column">
                <button
                  type="button"
                  class="sort-button sort-button--left"
                  @click="changeRouteSort('cities')"
                >
                  Miasta / lotniska

                  <span>
                    {{
                      sortMark(
                        routeSort,
                        'cities',
                        routeDirection,
                      )
                    }}
                  </span>
                </button>
              </th>

              <th class="number-column">
                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="changeRouteSort('flights')"
                >
                  Loty

                  <span>
                    {{
                      sortMark(
                        routeSort,
                        'flights',
                        routeDirection,
                      )
                    }}
                  </span>
                </button>
              </th>

              <th class="distance-column">
                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="changeRouteSort('distance')"
                >
                  Dystans

                  <span>
                    {{
                      sortMark(
                        routeSort,
                        'distance',
                        routeDirection,
                      )
                    }}
                  </span>
                </button>
              </th>

              <th class="distance-column">
                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="changeRouteSort('totalDistance')"
                >
                  Łączny dystans

                  <span>
                    {{
                      sortMark(
                        routeSort,
                        'totalDistance',
                        routeDirection,
                      )
                    }}
                  </span>
                </button>
              </th>

              <th class="details-column"></th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="
                (
                  row,
                  index
                )
                in sortedRoutes
              "
              :key="row.key"
            >
              <td class="position">
                {{ index + 1 }}
              </td>

              <td>
                <div class="route-cell">
                  <span
                    v-if="flagClass(row.departureCountryCode)"
                    :class="flagClass(row.departureCountryCode)!"
                    class="route-flag"
                  ></span>

                  <button
                    type="button"
                    class="route-code"
                    @click="
                      emit(
                        'route',
                        row.departureCode,
                        row.arrivalCode,
                      )
                    "
                  >
                    <span class="airport-code">
                      {{ row.departureCode ?? '---' }}
                    </span>

                    <span class="route-arrow">
                      →
                    </span>

                    <span class="airport-code">
                      {{ row.arrivalCode ?? '---' }}
                    </span>
                  </button>

                  <span
                    v-if="flagClass(row.arrivalCountryCode)"
                    :class="flagClass(row.arrivalCountryCode)!"
                    class="route-flag"
                  ></span>
                </div>
              </td>

              <td class="cities-cell">
                <div class="cities-main">
                  <span>
                    {{ row.departureCity }}
                  </span>

                  <span class="cities-arrow">
                    →
                  </span>

                  <span>
                    {{ row.arrivalCity }}
                  </span>
                </div>

                <div class="airports-subline">
                  <span>
                    {{ row.departureName }}
                  </span>

                  <span class="cities-arrow">
                    →
                  </span>

                  <span>
                    {{ row.arrivalName }}
                  </span>
                </div>
              </td>

              <td class="number-cell">
                {{ row.flights }}
              </td>

              <td class="number-cell">
                {{ formatNumber(row.distanceKm) }} km
              </td>

              <td class="number-cell">
                {{ formatNumber(row.totalDistanceKm) }} km
              </td>

              <td>
                <button
                  type="button"
                  class="route-details-button"
                  title="Pokaż szczegóły trasy"
                  @click="
                    emit(
                      'route',
                      row.departureCode,
                      row.arrivalCode,
                    )
                  "
                >
                  <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path d="M9 5l7 7-7 7" />
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </aside>
</template>

<style scoped>
.category-panel {
  position: absolute;
  top: 18px;
  right: 18px;
  z-index: 40;
  display: flex;
  width: min(1120px, calc(100vw - 430px));
  height: calc(100vh - 36px);
  flex-direction: column;
  overflow: hidden;
  padding: 18px;
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 16px;
  box-shadow: 0 14px 40px rgba(0, 0, 0, 0.18);
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
  background: rgba(11, 45, 92, 0.06);
  color: #0b2d5c;
}

.title-icon svg {
  width: 19px;
  height: 19px;
  fill: currentColor;
}

.title-icon .stroke-icon {
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
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

.summary {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  flex: 0 0 auto;
  gap: 7px;
  margin-top: 16px;
}

.summary > div {
  display: flex;
  min-height: 54px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  padding: 8px 10px;
  border-radius: 8px;
  background: #f4f4f4;
  text-align: center;
}

.summary strong {
  color: #9ca3af;
  font-size: 18px;
  line-height: 1.1;
}

.summary span {
  color: #666;
  font-size: 11px;
}

.view-tabs {
  display: inline-grid;
  grid-template-columns: 1fr 1fr;
  align-self: flex-start;
  gap: 3px;
  margin-top: 12px;
  padding: 3px;
  border-radius: 8px;
  background: #f1f2f4;
}

.view-tabs button {
  min-width: 150px;
  padding: 7px 12px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: #666;
  cursor: pointer;
  font-size: 11px;
}

.view-tabs button.active {
  background: #fff;
  color: #0b2d5c;
  font-weight: 700;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.table-container {
  flex: 1 1 auto;
  min-height: 0;
  margin-top: 12px;
  overflow: auto;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
}

.table-container--with-tabs {
  margin-top: 8px;
}

table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  background: #fff;
  font-size: 12px;
}

thead {
  position: sticky;
  top: 0;
  z-index: 2;
  background: #f6f6f6;
}

th {
  padding: 8px 10px;
  border-bottom: 1px solid #ddd;
  color: #666;
  font-size: 11px;
  font-weight: 650;
  text-align: left;
  white-space: nowrap;
}

td {
  padding: 7px 10px;
  border-bottom: 1px solid #eeeeee;
  color: #444;
  vertical-align: middle;
}

tbody tr:last-child td {
  border-bottom: 0;
}

tbody tr:hover {
  background: rgba(11, 45, 92, 0.03);
}

.position-column {
  width: 38px;
}

.position {
  color: #9ca3af;
  font-size: 11px;
}

.flag-column {
  width: 34px;
}

.flag-cell {
  text-align: center;
}

.country-flag {
  width: 18px;
  height: 12px;
  border-radius: 2px;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
}

.text-column {
  text-align: left;
}

.name-cell {
  color: #333;
  font-weight: 600;
  text-align: left;
}

.aircraft-link {
  padding: 0;
  border: 0;
  border-bottom: 1px solid rgba(11, 45, 92, 0.24);
  background: transparent;
  color: #0b2d5c;
  cursor: pointer;
  font-weight: 700;
  text-align: left;
}

.aircraft-link:hover {
  border-bottom-color: #0b2d5c;
}

.cities-column {
  min-width: 260px;
}

.number-column {
  width: 90px;
  text-align: right;
}

.distance-column {
  width: 125px;
  text-align: right;
}

.details-column {
  width: 48px;
}

.number-cell {
  text-align: right;
  white-space: nowrap;
}

.share-column {
  width: 150px;
  text-align: right;
}

.share-cell {
  min-width: 120px;
}

.share-value {
  color: #444;
  font-weight: 650;
  text-align: right;
}

.share-bar {
  height: 3px;
  margin-top: 4px;
  overflow: hidden;
  border-radius: 999px;
  background: #ececec;
}

.share-bar__value {
  height: 100%;
  border-radius: 999px;
  background: #9ca3af;
}

.sort-button {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  cursor: pointer;
  font-size: inherit;
  font-weight: inherit;
}

.sort-button:hover {
  color: #0b2d5c;
}

.sort-button span {
  min-width: 8px;
  color: #0b2d5c;
  font-weight: 800;
}

.sort-button--left {
  justify-content: flex-start;
  width: 100%;
  text-align: left;
}

.sort-button--right {
  justify-content: flex-end;
  width: 100%;
}

.route-cell {
  display: flex;
  align-items: center;
  gap: 7px;
  white-space: nowrap;
}

.route-flag {
  width: 16px;
  height: 11px;
  flex: 0 0 auto;
  border-radius: 2px;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
}

.route-code {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  padding: 0;
  border: 0;
  border-bottom: 1px solid rgba(11, 45, 92, 0.22);
  background: transparent;
  color: #0b2d5c;
  cursor: pointer;
  font-size: 12px;
  font-weight: 750;
}

.route-code:hover {
  border-bottom-color: #0b2d5c;
}

.airport-code {
  color: #0b2d5c;
}

.route-arrow,
.cities-arrow {
  color: #9ca3af;
}

.cities-cell {
  text-align: left;
}

.cities-main {
  display: flex;
  align-items: center;
  gap: 7px;
  color: #333;
  font-weight: 600;
}

.airports-subline {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-top: 3px;
  color: #8a9099;
  font-size: 10px;
  font-weight: 400;
}

.route-details-button {
  display: flex;
  width: 28px;
  height: 28px;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 1px solid #dfe3e8;
  border-radius: 7px;
  background: #f8f9fa;
  color: #0b2d5c;
  cursor: pointer;
}

.route-details-button svg {
  width: 12px;
  height: 12px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.route-details-button:hover {
  background: rgba(11, 45, 92, 0.05);
}

:deep(.fi) {
  display: inline-block;
}

@media (max-width: 900px) {
  .category-panel {
    top: 10px;
    right: 10px;
    left: 10px;
    width: auto;
    height: calc(100vh - 20px);
  }

  .summary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
