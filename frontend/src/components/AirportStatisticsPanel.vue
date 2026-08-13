<script setup lang="ts">
import {
  computed,
  ref,
} from 'vue'

import 'flag-icons/css/flag-icons.min.css'

import type {
  AirportDirectionStat,
  Flight,
  SelectedAirport,
} from '../types/flight'


interface AirportStatistic {
  id: number

  code: string | null
  name: string
  city: string

  country: string | null
  countryCode: string | null

  longitude: number
  latitude: number

  operations: number
  share: number
}


type SortField =
  | 'code'
  | 'name'
  | 'city'
  | 'country'
  | 'operations'
  | 'share'


type SortDirection =
  | 'asc'
  | 'desc'


const props = defineProps<{
  flights: Flight[]
}>()


const emit = defineEmits<{
  close: []

  airport: [
    airport: SelectedAirport,
  ]
}>()


const sortField =
  ref<SortField>(
    'operations',
  )


const sortDirection =
  ref<SortDirection>(
    'desc',
  )


/*
|--------------------------------------------------------------------------
| Locale nazw państw
|--------------------------------------------------------------------------
|
| Polski -> nazwy polskie
| Angielski -> nazwy angielskie
| Pozostałe -> polskie
|
*/

const countryLocale =
  computed(
    () => {
      const locale =
        navigator.language
          .toLowerCase()

      return locale.startsWith(
        'en',
      )
        ? 'en'
        : 'pl'
    },
  )


const countryNames =
  computed(
    () =>
      new Intl.DisplayNames(
        [
          countryLocale.value,
        ],
        {
          type:
            'region',
        },
      ),
  )


function displayCountry(
  airport:
    AirportStatistic,
): string {
  if (
    airport.countryCode
  ) {
    const name =
      countryNames.value.of(
        airport.countryCode
          .toUpperCase(),
      )

    if (name) {
      return name
    }
  }

  return (
    airport.country ??
    'brak danych'
  )
}


/*
|--------------------------------------------------------------------------
| Operacje
|--------------------------------------------------------------------------
*/

const totalOperations =
  computed(
    () =>
      props.flights.length *
      2,
  )


const airportStatistics =
  computed<AirportStatistic[]>(
    () => {
      interface Aggregate {
        id: number

        code: string | null
        name: string
        city: string

        country: string | null
        countryCode: string | null

        longitude: number
        latitude: number

        operations: number
      }


      const airports =
        new Map<
          number,
          Aggregate
        >()


      function addOperation(
        airport:
          Omit<
            Aggregate,
            'operations'
          >,
      ): void {
        const existing =
          airports.get(
            airport.id,
          )


        if (existing) {
          existing.operations++

          return
        }


        airports.set(
          airport.id,
          {
            ...airport,

            operations:
              1,
          },
        )
      }


      for (
        const flight
        of props.flights
      ) {
        /*
         * Start
         */

        addOperation({
          id:
            flight.departure_airport_id,

          code:
            flight.departure_iata,

          name:
            flight.departure_airport_name,

          city:
            flight.departure_city,

          country:
            flight.departure_country,

          countryCode:
            flight.departure_country_code,

          longitude:
            Number(
              flight.departure_longitude,
            ),

          latitude:
            Number(
              flight.departure_latitude,
            ),
        })


        /*
         * Lądowanie
         */

        addOperation({
          id:
            flight.arrival_airport_id,

          code:
            flight.arrival_iata,

          name:
            flight.arrival_airport_name,

          city:
            flight.arrival_city,

          country:
            flight.arrival_country,

          countryCode:
            flight.arrival_country_code,

          longitude:
            Number(
              flight.arrival_longitude,
            ),

          latitude:
            Number(
              flight.arrival_latitude,
            ),
        })
      }


      return [...airports.values()]
        .map(
          (airport) => ({
            ...airport,

            share:
              totalOperations.value >
              0
                ? (
                    airport.operations /
                    totalOperations.value
                  ) * 100
                : 0,
          }),
        )
    },
  )


/*
|--------------------------------------------------------------------------
| Sortowanie
|--------------------------------------------------------------------------
*/

const sortedAirports =
  computed(
    () => {
      const result =
        [...airportStatistics.value]


      result.sort(
        (a, b) => {
          let comparison =
            0


          switch (
            sortField.value
          ) {
            case 'operations':

              comparison =
                a.operations -
                b.operations

              break


            case 'share':

              comparison =
                a.share -
                b.share

              break


            case 'code':

              comparison =
                (
                  a.code ??
                  ''
                ).localeCompare(
                  b.code ??
                  '',
                  undefined,
                  {
                    sensitivity:
                      'base',
                  },
                )

              break


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


            case 'city':

              comparison =
                a.city.localeCompare(
                  b.city,
                  undefined,
                  {
                    sensitivity:
                      'base',
                  },
                )

              break


            case 'country':

              comparison =
                displayCountry(
                  a,
                ).localeCompare(
                  displayCountry(
                    b,
                  ),
                  countryLocale.value,
                  {
                    sensitivity:
                      'base',
                  },
                )

              break
          }


          return (
            sortDirection.value ===
              'asc'
              ? comparison
              : -comparison
          )
        },
      )


      return result
    },
  )


function changeSort(
  field: SortField,
): void {
  if (
    sortField.value ===
    field
  ) {
    sortDirection.value =
      sortDirection.value ===
        'asc'
        ? 'desc'
        : 'asc'

    return
  }


  sortField.value =
    field


  sortDirection.value =
    field ===
      'operations' ||
    field ===
      'share'
      ? 'desc'
      : 'asc'
}


function sortMark(
  field: SortField,
): string {
  if (
    sortField.value !==
    field
  ) {
    return ''
  }

  return (
    sortDirection.value ===
      'asc'
      ? '↑'
      : '↓'
  )
}


/*
|--------------------------------------------------------------------------
| Otwieranie panelu lotniska
|--------------------------------------------------------------------------
*/

function openAirport(
  airport:
    AirportStatistic,
): void {
  const destinations =
    new Map<
      number,
      AirportDirectionStat
    >()


  const origins =
    new Map<
      number,
      AirportDirectionStat
    >()


  let departures =
    0

  let arrivals =
    0


  for (
    const flight
    of props.flights
  ) {
    if (
      flight.departure_airport_id ===
      airport.id
    ) {
      departures++


      const existing =
        destinations.get(
          flight.arrival_airport_id,
        )


      if (existing) {
        existing.flights++
      } else {
        destinations.set(
          flight.arrival_airport_id,
          {
            code:
              flight.arrival_iata,

            name:
              flight.arrival_airport_name,

            city:
              flight.arrival_city,

            longitude:
              Number(
                flight.arrival_longitude,
              ),

            latitude:
              Number(
                flight.arrival_latitude,
              ),

            flights:
              1,
          },
        )
      }
    }


    if (
      flight.arrival_airport_id ===
      airport.id
    ) {
      arrivals++


      const existing =
        origins.get(
          flight.departure_airport_id,
        )


      if (existing) {
        existing.flights++
      } else {
        origins.set(
          flight.departure_airport_id,
          {
            code:
              flight.departure_iata,

            name:
              flight.departure_airport_name,

            city:
              flight.departure_city,

            longitude:
              Number(
                flight.departure_longitude,
              ),

            latitude:
              Number(
                flight.departure_latitude,
              ),

            flights:
              1,
          },
        )
      }
    }
  }


  emit(
    'airport',
    {
      code:
        airport.code,

      name:
        airport.name,

      city:
        airport.city,

      longitude:
        airport.longitude,

      latitude:
        airport.latitude,

      flights:
        departures +
        arrivals,

      departures,

      arrivals,

      topDestinations:
        [...destinations.values()]
          .sort(
            (a, b) =>
              b.flights -
              a.flights,
          )
          .slice(
            0,
            5,
          ),

      topOrigins:
        [...origins.values()]
          .sort(
            (a, b) =>
              b.flights -
              a.flights,
          )
          .slice(
            0,
            5,
          ),
    },
  )
}


/*
|--------------------------------------------------------------------------
| Formatowanie
|--------------------------------------------------------------------------
*/

function formatNumber(
  value: number,
): string {
  return new Intl.NumberFormat(
    undefined,
  ).format(value)
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
  ).format(value)
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
  <aside class="airport-statistics-panel">

    <header class="panel-header">

      <div class="title-area">

        <div class="title-icon">
          ✈
        </div>


        <div>

          <div class="panel-eyebrow">
            Statystyki
          </div>

          <h2>
            Lotniska
          </h2>

          <p>
            Lotniska związane z Twoimi podróżami.
          </p>

        </div>

      </div>


      <button
        type="button"
        class="close-button"
        title="Zamknij"
        aria-label="Zamknij"
        @click="
          emit(
            'close',
          )
        "
      >
        ×
      </button>

    </header>


    <section class="table-summary">

      <div>

        <strong>
          {{
            formatNumber(
              airportStatistics.length,
            )
          }}
        </strong>

        <span>
          lotnisk
        </span>

      </div>


      <div>

        <strong>
          {{
            formatNumber(
              flights.length,
            )
          }}
        </strong>

        <span>
          lotów
        </span>

      </div>


      <div>

        <strong>
          {{
            formatNumber(
              totalOperations,
            )
          }}
        </strong>

        <span>
          operacji
        </span>

      </div>

    </section>


    <div class="table-container">

      <table>

        <thead>

          <tr>

            <th class="column-position">
              #
            </th>


            <th>

              <button
                type="button"
                class="sort-button"
                @click="
                  changeSort(
                    'code',
                  )
                "
              >
                Kod

                <span>
                  {{
                    sortMark(
                      'code',
                    )
                  }}
                </span>
              </button>

            </th>


            <th>

              <button
                type="button"
                class="sort-button"
                @click="
                  changeSort(
                    'name',
                  )
                "
              >
                Lotnisko

                <span>
                  {{
                    sortMark(
                      'name',
                    )
                  }}
                </span>
              </button>

            </th>


            <th>

              <button
                type="button"
                class="sort-button"
                @click="
                  changeSort(
                    'city',
                  )
                "
              >
                Miasto

                <span>
                  {{
                    sortMark(
                      'city',
                    )
                  }}
                </span>
              </button>

            </th>


            <th>

              <button
                type="button"
                class="sort-button"
                @click="
                  changeSort(
                    'country',
                  )
                "
              >
                Kraj

                <span>
                  {{
                    sortMark(
                      'country',
                    )
                  }}
                </span>
              </button>

            </th>


            <th class="column-number">

              <button
                type="button"
                class="sort-button sort-button--right"
                @click="
                  changeSort(
                    'operations',
                  )
                "
              >
                Operacje

                <span>
                  {{
                    sortMark(
                      'operations',
                    )
                  }}
                </span>
              </button>

            </th>


            <th class="column-share">

              <button
                type="button"
                class="sort-button sort-button--right"
                @click="
                  changeSort(
                    'share',
                  )
                "
              >
                Udział operacji

                <span>
                  {{
                    sortMark(
                      'share',
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
                airport,
                index
              )
              in sortedAirports
            "
            :key="
              airport.id
            "
          >

            <td class="position">
              {{ index + 1 }}
            </td>


            <td>

              <button
                type="button"
                class="airport-link"
                title="Pokaż lotnisko"
                @click="
                  openAirport(
                    airport,
                  )
                "
              >
                {{ airport.code ?? '---' }}
              </button>

            </td>


            <td class="airport-name">
              {{ airport.name }}
            </td>


            <td class="city">
              {{ airport.city }}
            </td>


            <td>

              <div class="country-cell">

                <span
                  v-if="
                    flagClass(
                      airport.countryCode,
                    )
                  "
                  :class="
                    flagClass(
                      airport.countryCode,
                    )!
                  "
                  class="country-flag"
                ></span>


                <span>
                  {{
                    displayCountry(
                      airport,
                    )
                  }}
                </span>

              </div>

            </td>


            <td class="number-cell">

              <strong>
                {{
                  formatNumber(
                    airport.operations,
                  )
                }}
              </strong>

            </td>


            <td class="share-cell">

              <div class="share-value">
                {{
                  formatShare(
                    airport.share,
                  )
                }}%
              </div>


              <div class="share-bar">

                <div
                  class="share-bar__value"
                  :style="{
                    width:
                      `${Math.min(
                        airport.share,
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

  </aside>
</template>


<style scoped>
.airport-statistics-panel {
  position: absolute;

  top: 18px;
  right: 18px;

  z-index: 40;

  display: flex;

  width:
    min(
      1080px,
      calc(
        100vw - 430px
      )
    );

  height:
    calc(
      100vh - 36px
    );

  max-height:
    calc(
      100vh - 36px
    );

  flex-direction: column;

  overflow: hidden;

  padding: 18px;

  background:
    rgba(
      255,
      255,
      255,
      0.98
    );

  backdrop-filter:
    blur(12px);

  border:
    1px solid
    rgba(
      0,
      0,
      0,
      0.08
    );

  border-radius: 16px;

  box-shadow:
    0 14px 40px
    rgba(
      0,
      0,
      0,
      0.18
    );
}


.panel-header {
  display: flex;

  flex:
    0 0 auto;

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
      0.07
    );

  color: #0b2d5c;

  font-size: 20px;
}


.panel-eyebrow {
  color: #9ca3af;

  font-size: 11px;
  font-weight: 650;

  letter-spacing:
    0.04em;

  text-transform: uppercase;
}


.panel-header h2 {
  margin:
    2px 0 0;

  color: #222;

  font-size: 21px;
  font-weight: 700;
}


.panel-header p {
  margin:
    4px 0 0;

  color: #777;

  font-size: 12px;
}


.close-button {
  width: 36px;
  height: 36px;

  flex: 0 0 auto;

  border: 0;

  border-radius: 8px;

  background: #f3f3f3;

  color: #444;

  cursor: pointer;

  font-size: 22px;
}


.close-button:hover {
  background: #e8e8e8;
}


.table-summary {
  display: flex;

  flex:
    0 0 auto;

  flex-wrap: wrap;

  gap: 7px;

  margin-top: 14px;
}


.table-summary > div {
  display: flex;

  align-items: baseline;

  gap: 6px;

  padding:
    7px 10px;

  background: #f4f4f4;

  border-radius: 8px;
}


.table-summary strong {
  color: #9ca3af;

  font-size: 17px;
}


.table-summary span {
  color: #666;

  font-size: 11px;
}


.table-container {
  flex:
    1 1 auto;

  min-height: 0;

  margin-top: 10px;

  overflow:
    auto;

  border:
    1px solid #e6e6e6;

  border-radius: 10px;
}


table {
  width: 100%;

  border-collapse:
    separate;

  border-spacing: 0;

  background: white;

  font-size: 12px;
}


thead {
  position: sticky;

  top: 0;

  z-index: 2;

  background: #f6f6f6;
}


th {
  padding:
    7px 10px;

  border-bottom:
    1px solid #ddd;

  color: #666;

  font-size: 11px;
  font-weight: 650;

  text-align: left;

  white-space: nowrap;
}


td {
  padding:
    5px 10px;

  border-bottom:
    1px solid #eeeeee;

  color: #444;

  font-size: 12px;

  text-align: left;

  vertical-align: middle;
}


tbody tr:last-child td {
  border-bottom: 0;
}


tbody tr:hover {
  background:
    rgba(
      11,
      45,
      92,
      0.035
    );
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
}


.sort-button--right {
  justify-content: flex-end;

  width: 100%;
}


.column-position {
  width: 38px;
}


.column-number {
  width: 92px;

  text-align: right;
}


.column-share {
  width: 150px;

  text-align: right;
}


.position {
  color: #9ca3af;

  font-size: 11px;
}


.airport-link {
  padding: 0;

  border: 0;

  border-bottom:
    1px solid
    rgba(
      11,
      45,
      92,
      0.28
    );

  background: transparent;

  color: #0b2d5c;

  cursor: pointer;

  font-size: 13px;
  font-weight: 750;
}


.airport-link:hover {
  border-bottom-color:
    #0b2d5c;
}


.airport-name {
  min-width: 190px;

  font-weight: 600;

  text-align: left;
}


.city {
  min-width: 120px;
}


.country-cell {
  display: flex;

  align-items: center;

  gap: 7px;

  min-width: 135px;
}


.country-flag {
  width: 18px;
  height: 13px;

  flex: 0 0 auto;

  border-radius: 2px;

  box-shadow:
    0 0 0 1px
    rgba(
      0,
      0,
      0,
      0.08
    );
}


.number-cell {
  text-align: right;
}


.number-cell strong {
  color: #333;

  font-size: 12px;
}


.share-cell {
  min-width: 130px;
}


.share-value {
  color: #444;

  font-size: 12px;
  font-weight: 650;

  text-align: right;
}


.share-bar {
  height: 3px;

  margin-top: 3px;

  overflow: hidden;

  background: #ececec;

  border-radius: 999px;
}


.share-bar__value {
  height: 100%;

  background: #9ca3af;

  border-radius: 999px;
}


:deep(.fi) {
  display: inline-block;
}


@media (
  max-width: 900px
) {
  .airport-statistics-panel {
    top: 10px;
    right: 10px;
    left: 10px;

    width: auto;

    height:
      calc(
        100vh - 20px
      );

    max-height:
      calc(
        100vh - 20px
      );
  }
}
</style>