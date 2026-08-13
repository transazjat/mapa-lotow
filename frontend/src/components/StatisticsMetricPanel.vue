<script setup lang="ts">
import {
  computed,
  ref,
} from 'vue'

import type {
  Flight,
} from '../types/flight'


type ReportType =
  | 'flights'
  | 'distance'
  | 'duration'


type FlightsSortField =
  | 'year'
  | 'domestic'
  | 'continental'
  | 'intercontinental'
  | 'other'
  | 'totalFlights'


type DistanceSortField =
  | 'year'
  | 'distanceKm'
  | 'distanceMiles'
  | 'equatorLaps'


type DurationSortField =
  | 'year'
  | 'durationSeconds'
  | 'days'


type SortDirection =
  | 'asc'
  | 'desc'


const props = defineProps<{
  flights: Flight[]

  reportType:
    ReportType
}>()


const emit = defineEmits<{
  close: []
}>()


interface YearStatistic {
  year: string

  totalFlights: number

  domestic: number
  continental: number
  intercontinental: number
  other: number

  distanceKm: number
  distanceMiles: number
  equatorLaps: number

  durationSeconds: number
  days: number
}


/*
|--------------------------------------------------------------------------
| Sortowanie
|--------------------------------------------------------------------------
*/

const flightsSortField =
  ref<FlightsSortField>(
    'year',
  )


const flightsSortDirection =
  ref<SortDirection>(
    'desc',
  )


const distanceSortField =
  ref<DistanceSortField>(
    'year',
  )


const distanceSortDirection =
  ref<SortDirection>(
    'desc',
  )


const durationSortField =
  ref<DurationSortField>(
    'year',
  )


const durationSortDirection =
  ref<SortDirection>(
    'desc',
  )


/*
|--------------------------------------------------------------------------
| Podstawowe sumy
|--------------------------------------------------------------------------
*/

const totalFlights =
  computed(
    () =>
      props.flights.length,
  )


const domesticFlights =
  computed(
    () =>
      props.flights.filter(
        (flight) =>
          flight.flight_type ===
          'domestic',
      ).length,
  )


const continentalFlights =
  computed(
    () =>
      props.flights.filter(
        (flight) =>
          flight.flight_type ===
          'continental',
      ).length,
  )


const intercontinentalFlights =
  computed(
    () =>
      props.flights.filter(
        (flight) =>
          flight.flight_type ===
          'intercontinental',
      ).length,
  )


const otherFlights =
  computed(
    () =>
      props.flights.filter(
        (flight) =>
          flight.flight_type ===
          'other',
      ).length,
  )


const totalDistanceKm =
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


const totalDurationSeconds =
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


/*
|--------------------------------------------------------------------------
| Dystans
|--------------------------------------------------------------------------
*/

const totalDistanceMiles =
  computed(
    () =>
      totalDistanceKm.value *
      0.621371192,
  )


const equatorLaps =
  computed(
    () =>
      totalDistanceKm.value /
      40075,
  )


const moonDistances =
  computed(
    () =>
      totalDistanceKm.value /
      384400,
  )


const sunDistances =
  computed(
    () =>
      totalDistanceKm.value /
      149597870.7,
  )


/*
|--------------------------------------------------------------------------
| Czas
|--------------------------------------------------------------------------
*/

const totalHours =
  computed(
    () =>
      totalDurationSeconds.value /
      3600,
  )


const totalDays =
  computed(
    () =>
      totalHours.value /
      24,
  )


const totalWeeks =
  computed(
    () =>
      totalDays.value /
      7,
  )


const totalMonths =
  computed(
    () =>
      totalDays.value /
      30,
  )


const totalYears =
  computed(
    () =>
      totalDays.value /
      365,
  )


/*
|--------------------------------------------------------------------------
| Rozbicie roczne
|--------------------------------------------------------------------------
*/

const years =
  computed<YearStatistic[]>(
    () => {
      const result =
        new Map<
          string,
          YearStatistic
        >()


      for (
        const flight
        of props.flights
      ) {
        const year =
          flight.departure_date
            ?.slice(
              0,
              4,
            ) ??
          'Brak daty'


        let row =
          result.get(
            year,
          )


        if (!row) {
          row = {
            year,

            totalFlights:
              0,

            domestic:
              0,

            continental:
              0,

            intercontinental:
              0,

            other:
              0,

            distanceKm:
              0,

            distanceMiles:
              0,

            equatorLaps:
              0,

            durationSeconds:
              0,

            days:
              0,
          }


          result.set(
            year,
            row,
          )
        }


        row.totalFlights++


        switch (
          flight.flight_type
        ) {
          case 'domestic':

            row.domestic++

            break


          case 'continental':

            row.continental++

            break


          case 'intercontinental':

            row.intercontinental++

            break


          case 'other':

            row.other++

            break
        }


        row.distanceKm +=
          flight.distance_km ??
          0


        row.durationSeconds +=
          flight.duration_seconds ??
          0
      }


      for (
        const row
        of result.values()
      ) {
        row.distanceMiles =
          row.distanceKm *
          0.621371192

        row.equatorLaps =
          row.distanceKm /
          40075

        row.days =
          row.durationSeconds /
          86400
      }


      return [...result.values()]
    },
  )


/*
|--------------------------------------------------------------------------
| Posortowane dane - Loty
|--------------------------------------------------------------------------
*/

const sortedFlightYears =
  computed(
    () => {
      const result =
        [...years.value]


      result.sort(
        (a, b) => {
          let comparison =
            0


          switch (
            flightsSortField.value
          ) {
            case 'year':

              comparison =
                a.year.localeCompare(
                  b.year,
                )

              break


            case 'domestic':

              comparison =
                a.domestic -
                b.domestic

              break


            case 'continental':

              comparison =
                a.continental -
                b.continental

              break


            case 'intercontinental':

              comparison =
                a.intercontinental -
                b.intercontinental

              break


            case 'other':

              comparison =
                a.other -
                b.other

              break


            case 'totalFlights':

              comparison =
                a.totalFlights -
                b.totalFlights

              break
          }


          return (
            flightsSortDirection.value ===
              'asc'
              ? comparison
              : -comparison
          )
        },
      )


      return result
    },
  )


/*
|--------------------------------------------------------------------------
| Posortowane dane - Dystans
|--------------------------------------------------------------------------
*/

const sortedDistanceYears =
  computed(
    () => {
      const result =
        [...years.value]


      result.sort(
        (a, b) => {
          let comparison =
            0


          switch (
            distanceSortField.value
          ) {
            case 'year':

              comparison =
                a.year.localeCompare(
                  b.year,
                )

              break


            case 'distanceKm':

              comparison =
                a.distanceKm -
                b.distanceKm

              break


            case 'distanceMiles':

              comparison =
                a.distanceMiles -
                b.distanceMiles

              break


            case 'equatorLaps':

              comparison =
                a.equatorLaps -
                b.equatorLaps

              break
          }


          return (
            distanceSortDirection.value ===
              'asc'
              ? comparison
              : -comparison
          )
        },
      )


      return result
    },
  )


/*
|--------------------------------------------------------------------------
| Posortowane dane - Czas
|--------------------------------------------------------------------------
*/

const sortedDurationYears =
  computed(
    () => {
      const result =
        [...years.value]


      result.sort(
        (a, b) => {
          let comparison =
            0


          switch (
            durationSortField.value
          ) {
            case 'year':

              comparison =
                a.year.localeCompare(
                  b.year,
                )

              break


            case 'durationSeconds':

              comparison =
                a.durationSeconds -
                b.durationSeconds

              break


            case 'days':

              comparison =
                a.days -
                b.days

              break
          }


          return (
            durationSortDirection.value ===
              'asc'
              ? comparison
              : -comparison
          )
        },
      )


      return result
    },
  )


/*
|--------------------------------------------------------------------------
| Sterowanie sortowaniem
|--------------------------------------------------------------------------
*/

function changeFlightsSort(
  field:
    FlightsSortField,
): void {
  if (
    flightsSortField.value ===
    field
  ) {
    flightsSortDirection.value =
      flightsSortDirection.value ===
        'asc'
        ? 'desc'
        : 'asc'

    return
  }


  flightsSortField.value =
    field


  flightsSortDirection.value =
    field ===
      'year'
      ? 'desc'
      : 'desc'
}


function changeDistanceSort(
  field:
    DistanceSortField,
): void {
  if (
    distanceSortField.value ===
    field
  ) {
    distanceSortDirection.value =
      distanceSortDirection.value ===
        'asc'
        ? 'desc'
        : 'asc'

    return
  }


  distanceSortField.value =
    field


  distanceSortDirection.value =
    'desc'
}


function changeDurationSort(
  field:
    DurationSortField,
): void {
  if (
    durationSortField.value ===
    field
  ) {
    durationSortDirection.value =
      durationSortDirection.value ===
        'asc'
        ? 'desc'
        : 'asc'

    return
  }


  durationSortField.value =
    field


  durationSortDirection.value =
    'desc'
}


function flightsSortMark(
  field:
    FlightsSortField,
): string {
  if (
    flightsSortField.value !==
    field
  ) {
    return ''
  }


  return (
    flightsSortDirection.value ===
      'asc'
      ? '↑'
      : '↓'
  )
}


function distanceSortMark(
  field:
    DistanceSortField,
): string {
  if (
    distanceSortField.value !==
    field
  ) {
    return ''
  }


  return (
    distanceSortDirection.value ===
      'asc'
      ? '↑'
      : '↓'
  )
}


function durationSortMark(
  field:
    DurationSortField,
): string {
  if (
    durationSortField.value !==
    field
  ) {
    return ''
  }


  return (
    durationSortDirection.value ===
      'asc'
      ? '↑'
      : '↓'
  )
}


/*
|--------------------------------------------------------------------------
| Tytuł
|--------------------------------------------------------------------------
*/

const title =
  computed(
    () => {
      switch (
        props.reportType
      ) {
        case 'distance':

          return 'Pokonany dystans'


        case 'duration':

          return 'Czas w powietrzu'


        default:

          return 'Liczba lotów'
      }
    },
  )


const icon =
  computed(
    () => {
      switch (
        props.reportType
      ) {
        case 'distance':

          return '↔'


        case 'duration':

          return '◷'


        default:

          return '✈'
      }
    },
  )


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
  ).format(
    Math.round(
      value,
    ),
  )
}


function formatDecimal(
  value: number,
  digits: number,
): string {
  return new Intl.NumberFormat(
    undefined,
    {
      minimumFractionDigits:
        digits,

      maximumFractionDigits:
        digits,
    },
  ).format(value)
}


function formatDuration(
  seconds: number,
): string {
  const totalMinutes =
    Math.floor(
      seconds / 60,
    )

  const hours =
    Math.floor(
      totalMinutes / 60,
    )

  const minutes =
    totalMinutes % 60


  return (
    `${hours}:` +
    `${String(
      minutes,
    ).padStart(
      2,
      '0',
    )}`
  )
}


function formatHoursMinutes(
  seconds: number,
): string {
  const totalMinutes =
    Math.floor(
      seconds / 60,
    )

  const hours =
    Math.floor(
      totalMinutes / 60,
    )

  const minutes =
    totalMinutes % 60


  return (
    `${formatNumber(
      hours,
    )} h ` +
    `${minutes} min`
  )
}
</script>


<template>
  <aside class="metric-panel">

    <header class="panel-header">

      <div class="title-area">

        <div class="title-icon">
          {{ icon }}
        </div>


        <div>

          <div class="eyebrow">
            Statystyki
          </div>

          <h2>
            {{ title }}
          </h2>

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


    <!-- ============================================================= -->
    <!-- LICZBA LOTÓW                                                   -->
    <!-- ============================================================= -->

    <template
      v-if="
        reportType ===
        'flights'
      "
    >

      <section class="metric-cards metric-cards--five">

        <div class="metric-card metric-card--primary">

          <span>
            Wszystkie loty
          </span>

          <strong>
            {{ totalFlights }}
          </strong>

        </div>


        <div class="metric-card">

          <span>
            Krajowe
          </span>

          <strong>
            {{ domesticFlights }}
          </strong>

        </div>


        <div class="metric-card">

          <span>
            Kontynentalne
          </span>

          <strong>
            {{ continentalFlights }}
          </strong>

        </div>


        <div class="metric-card">

          <span>
            Międzykontynentalne
          </span>

          <strong>
            {{ intercontinentalFlights }}
          </strong>

        </div>


        <div class="metric-card">

          <span>
            Widokowe / inne
          </span>

          <strong>
            {{ otherFlights }}
          </strong>

        </div>

      </section>


      <section class="section-title">
        Loty według lat
      </section>


      <div class="table-container">

        <table>

          <thead>

            <tr>

              <th>

                <button
                  type="button"
                  class="sort-button"
                  @click="
                    changeFlightsSort(
                      'year',
                    )
                  "
                >
                  Rok

                  <span>
                    {{
                      flightsSortMark(
                        'year',
                      )
                    }}
                  </span>
                </button>

              </th>


              <th class="number">

                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="
                    changeFlightsSort(
                      'domestic',
                    )
                  "
                >
                  Krajowe

                  <span>
                    {{
                      flightsSortMark(
                        'domestic',
                      )
                    }}
                  </span>
                </button>

              </th>


              <th class="number">

                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="
                    changeFlightsSort(
                      'continental',
                    )
                  "
                >
                  Kontynentalne

                  <span>
                    {{
                      flightsSortMark(
                        'continental',
                      )
                    }}
                  </span>
                </button>

              </th>


              <th class="number">

                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="
                    changeFlightsSort(
                      'intercontinental',
                    )
                  "
                >
                  Międzykontynentalne

                  <span>
                    {{
                      flightsSortMark(
                        'intercontinental',
                      )
                    }}
                  </span>
                </button>

              </th>


              <th class="number">

                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="
                    changeFlightsSort(
                      'other',
                    )
                  "
                >
                  Widokowe

                  <span>
                    {{
                      flightsSortMark(
                        'other',
                      )
                    }}
                  </span>
                </button>

              </th>


              <th class="number">

                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="
                    changeFlightsSort(
                      'totalFlights',
                    )
                  "
                >
                  Razem

                  <span>
                    {{
                      flightsSortMark(
                        'totalFlights',
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
                row in sortedFlightYears
              "
              :key="
                row.year
              "
            >

              <td class="year">
                {{ row.year }}
              </td>

              <td class="number">
                {{ row.domestic }}
              </td>

              <td class="number">
                {{ row.continental }}
              </td>

              <td class="number">
                {{ row.intercontinental }}
              </td>

              <td class="number">
                {{ row.other }}
              </td>

              <td class="number total">
                {{ row.totalFlights }}
              </td>

            </tr>

          </tbody>

        </table>

      </div>

    </template>


    <!-- ============================================================= -->
    <!-- DYSTANS                                                        -->
    <!-- ============================================================= -->

    <template
      v-else-if="
        reportType ===
        'distance'
      "
    >

      <section class="metric-cards metric-cards--five">

        <div class="metric-card metric-card--primary">

          <span>
            Kilometry
          </span>

          <strong>
            {{
              formatNumber(
                totalDistanceKm,
              )
            }}
          </strong>

        </div>


        <div class="metric-card">

          <span>
            Mile
          </span>

          <strong>
            {{
              formatNumber(
                totalDistanceMiles,
              )
            }}
          </strong>

        </div>


        <div class="metric-card">

          <span>
            Okrążenia równika
          </span>

          <strong>
            {{
              formatDecimal(
                equatorLaps,
                2,
              )
            }}
          </strong>

        </div>


        <div class="metric-card">

          <span>
            Dystans do Księżyca
          </span>

          <strong>
            {{
              formatDecimal(
                moonDistances,
                3,
              )
            }}
          </strong>

        </div>


        <div class="metric-card">

          <span>
            Dystans do Słońca
          </span>

          <strong>
            {{
              formatDecimal(
                sunDistances,
                4,
              )
            }}
          </strong>

        </div>

      </section>


      <section class="section-title">
        Dystans według lat
      </section>


      <div class="table-container">

        <table>

          <thead>

            <tr>

              <th>

                <button
                  type="button"
                  class="sort-button"
                  @click="
                    changeDistanceSort(
                      'year',
                    )
                  "
                >
                  Rok

                  <span>
                    {{
                      distanceSortMark(
                        'year',
                      )
                    }}
                  </span>
                </button>

              </th>


              <th class="number">

                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="
                    changeDistanceSort(
                      'distanceKm',
                    )
                  "
                >
                  Kilometry

                  <span>
                    {{
                      distanceSortMark(
                        'distanceKm',
                      )
                    }}
                  </span>
                </button>

              </th>


              <th class="number">

                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="
                    changeDistanceSort(
                      'distanceMiles',
                    )
                  "
                >
                  Mile

                  <span>
                    {{
                      distanceSortMark(
                        'distanceMiles',
                      )
                    }}
                  </span>
                </button>

              </th>


              <th class="number">

                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="
                    changeDistanceSort(
                      'equatorLaps',
                    )
                  "
                >
                  Okrążenia równika

                  <span>
                    {{
                      distanceSortMark(
                        'equatorLaps',
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
                row in sortedDistanceYears
              "
              :key="
                row.year
              "
            >

              <td class="year">
                {{ row.year }}
              </td>


              <td class="number">
                {{
                  formatNumber(
                    row.distanceKm,
                  )
                }}
              </td>


              <td class="number">
                {{
                  formatNumber(
                    row.distanceMiles,
                  )
                }}
              </td>


              <td class="number">
                {{
                  formatDecimal(
                    row.equatorLaps,
                    2,
                  )
                }}
              </td>

            </tr>

          </tbody>

        </table>

      </div>

    </template>


    <!-- ============================================================= -->
    <!-- CZAS                                                           -->
    <!-- ============================================================= -->

    <template v-else>

      <section class="metric-cards metric-cards--five">

        <div class="metric-card metric-card--primary">

          <span>
            Godzin
          </span>

          <strong>
            {{
              formatDuration(
                totalDurationSeconds,
              )
            }}
          </strong>

        </div>


        <div class="metric-card">

          <span>
            Dni
          </span>

          <strong>
            {{
              formatDecimal(
                totalDays,
                1,
              )
            }}
          </strong>

        </div>


        <div class="metric-card">

          <span>
            Tygodni
          </span>

          <strong>
            {{
              formatDecimal(
                totalWeeks,
                1,
              )
            }}
          </strong>

        </div>


        <div class="metric-card">

          <span>
            Miesięcy
          </span>

          <strong>
            {{
              formatDecimal(
                totalMonths,
                2,
              )
            }}
          </strong>

        </div>


        <div class="metric-card">

          <span>
            Lat
          </span>

          <strong>
            {{
              formatDecimal(
                totalYears,
                3,
              )
            }}
          </strong>

        </div>

      </section>


      <section class="section-title">
        Czas w powietrzu według lat
      </section>


      <div class="table-container">

        <table>

          <thead>

            <tr>

              <th>

                <button
                  type="button"
                  class="sort-button"
                  @click="
                    changeDurationSort(
                      'year',
                    )
                  "
                >
                  Rok

                  <span>
                    {{
                      durationSortMark(
                        'year',
                      )
                    }}
                  </span>
                </button>

              </th>


              <th class="number">

                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="
                    changeDurationSort(
                      'durationSeconds',
                    )
                  "
                >
                  Czas

                  <span>
                    {{
                      durationSortMark(
                        'durationSeconds',
                      )
                    }}
                  </span>
                </button>

              </th>


              <th class="number">

                <button
                  type="button"
                  class="sort-button sort-button--right"
                  @click="
                    changeDurationSort(
                      'days',
                    )
                  "
                >
                  Dni

                  <span>
                    {{
                      durationSortMark(
                        'days',
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
                row in sortedDurationYears
              "
              :key="
                row.year
              "
            >

              <td class="year">
                {{ row.year }}
              </td>


              <td class="number">
                {{
                  formatHoursMinutes(
                    row.durationSeconds,
                  )
                }}
              </td>


              <td class="number">
                {{
                  formatDecimal(
                    row.days,
                    1,
                  )
                }}
              </td>

            </tr>

          </tbody>

        </table>

      </div>

    </template>

  </aside>
</template>


<style scoped>
.metric-panel {
  position: absolute;

  top: 18px;
  right: 18px;

  z-index: 40;

  display: flex;

  width:
    min(
      1000px,
      calc(
        100vw - 430px
      )
    );

  height:
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

  align-items: center;

  justify-content: space-between;
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


.eyebrow {
  color: #9ca3af;

  font-size: 11px;
  font-weight: 650;

  text-transform: uppercase;
}


h2 {
  margin:
    2px 0 0;

  color: #222;

  font-size: 21px;
  font-weight: 700;
}


.close-button {
  width: 36px;
  height: 36px;

  border: 0;

  border-radius: 8px;

  background: #f3f3f3;

  color: #444;

  cursor: pointer;

  font-size: 22px;
}


.metric-cards {
  display: grid;

  flex:
    0 0 auto;

  gap: 7px;

  margin-top: 16px;
}


.metric-cards--five {
  grid-template-columns:
    repeat(
      5,
      minmax(
        0,
        1fr
      )
    );
}


.metric-card {
  display: flex;

  min-height: 83px;

  flex-direction: column;

  align-items: center;

  justify-content: center;

  padding:
    9px 7px;

  border:
    1px solid #e6e6e6;

  border-radius: 9px;

  background: #fff;

  text-align: center;
}


.metric-card--primary {
  background: #f4f4f4;
}


.metric-card span {
  color: #777;

  font-size: 11px;
}


.metric-card strong {
  margin-top: 6px;

  color: #9ca3af;

  font-size: 19px;
  font-weight: 700;

  line-height: 1.1;
}


.section-title {
  flex:
    0 0 auto;

  margin:
    18px 0 8px;

  color: #444;

  font-size: 12px;
  font-weight: 700;
}


.table-container {
  flex:
    1 1 auto;

  min-height: 0;

  overflow: auto;

  border:
    1px solid #e6e6e6;

  border-radius: 10px;
}


table {
  width: 100%;

  border-collapse:
    separate;

  border-spacing: 0;

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
    8px 11px;

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
    7px 11px;

  border-bottom:
    1px solid #eee;

  color: #444;
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

  font-weight: 800;
}


.sort-button--right {
  justify-content: flex-end;

  width: 100%;
}


.year {
  color: #0b2d5c;

  font-weight: 700;
}


.number {
  text-align: right;
}


.total {
  font-weight: 750;
}


@media (
  max-width: 1000px
) {
  .metric-cards--five {
    grid-template-columns:
      repeat(
        2,
        1fr
      );
  }
}
</style>