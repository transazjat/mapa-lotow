<script setup lang="ts">
import 'flag-icons/css/flag-icons.min.css'

import {
  computed,
} from 'vue'

import FlightCard from './FlightCard.vue'

import type {
  RouteFlight,
  SelectedRoute,
} from '../types/flight'

import {
  isPlannedDate,
} from '../utils/flightScope'


const props = defineProps<{
  route: SelectedRoute
}>()


const emit = defineEmits<{
  close: []
  flight: [flight: RouteFlight]
}>()


const completedFlightsCount =
  computed(
    () =>
      props.route.flightList.filter(
        (flight) =>
          !isPlannedDate(
            flight.departureDate,
          ),
      ).length,
  )


const singleRouteDistance =
  computed<number | null>(
    () => {
      const flight =
        props.route.flightList.find(
          (item) =>
            item.distanceKm !== null,
        )

      return (
        flight?.distanceKm ??
        null
      )
    },
  )


function formatNumber(
  value: number,
): string {
  return new Intl.NumberFormat(
    undefined,
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

  return `${formatNumber(hours)} h ${minutes} min`
}


function formatDate(
  value: string | null,
): string {
  if (!value) {
    return 'brak danych'
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
    undefined,
    {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    },
  ).format(date)
}


function flagClass(
  code:
    string | null |
    undefined,
): string | null {
  if (!code) {
    return null
  }

  const normalized =
    code
      .trim()
      .toLowerCase()

  if (
    normalized.length !== 2
  ) {
    return null
  }

  return `fi fi-${normalized}`
}
</script>


<template>
  <aside class="route-panel">

    <div class="panel-actions">

      <div class="panel-title">
        Trasa
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

    </div>


    <section class="route-hero">

      <div class="route-side">

        <div class="route-code-row">

          <span
            v-if="
              flagClass(
                route.departureCountryCode,
              )
            "
            :class="
              flagClass(
                route.departureCountryCode,
              )!
            "
            class="route-flag"
          ></span>


          <strong>
            {{ route.departureCode ?? '---' }}
          </strong>

        </div>


        <div class="route-city">
          {{ route.departureCity }}
        </div>


        <div class="route-airport">
          {{ route.departureName }}
        </div>

      </div>


      <div class="route-middle">

        <div class="route-label">
          dystans trasy
        </div>


        <div class="route-line">

          <span class="route-plane">
            ✈
          </span>

        </div>


        <div class="route-distance">

          <template
            v-if="
              singleRouteDistance !==
              null
            "
          >
            {{
              formatNumber(
                singleRouteDistance,
              )
            }}
            km
          </template>

          <template v-else>
            brak danych
          </template>

        </div>

      </div>


      <div class="route-side route-side--right">

        <div class="route-code-row route-code-row--right">

          <strong>
            {{ route.arrivalCode ?? '---' }}
          </strong>


          <span
            v-if="
              flagClass(
                route.arrivalCountryCode,
              )
            "
            :class="
              flagClass(
                route.arrivalCountryCode,
              )!
            "
            class="route-flag"
          ></span>

        </div>


        <div class="route-city">
          {{ route.arrivalCity }}
        </div>


        <div class="route-airport">
          {{ route.arrivalName }}
        </div>

      </div>

    </section>


    <section class="flight-count">

      <strong>
        {{
          formatNumber(
            completedFlightsCount,
          )
        }}
      </strong>

      <span>
        zrealizowanych lotów
      </span>

    </section>


    <section class="route-summary">

      <div class="summary-card">

        <span>
          Pierwszy lot
        </span>

        <strong>
          {{
            formatDate(
              route.firstFlightDate,
            )
          }}
        </strong>

      </div>


      <div class="summary-card">

        <span>
          Ostatni lot
        </span>

        <strong>
          {{
            formatDate(
              route.lastFlightDate,
            )
          }}
        </strong>

      </div>


      <div class="summary-card">

        <span>
          Łączny dystans
        </span>

        <strong>
          {{
            formatNumber(
              route.totalDistanceKm,
            )
          }}
          km
        </strong>

      </div>


      <div class="summary-card">

        <span>
          Łączny czas
        </span>

        <strong>
          {{
            formatDuration(
              route.totalDurationSeconds,
            )
          }}
        </strong>

      </div>


      <div class="summary-card">

        <span>
          Najczęstsza linia
        </span>

        <strong>
          {{
            route.topAirline ??
            'brak danych'
          }}
        </strong>

      </div>


      <div class="summary-card">

        <span>
          Najczęstszy samolot
        </span>

        <strong>
          {{
            route.topAircraft ??
            'brak danych'
          }}
        </strong>

      </div>

    </section>


    <section class="history">

      <h3>
        Historia lotów
      </h3>


      <FlightCard
        v-for="
          flight in route.flightList
        "
        :key="flight.id"

        :departure-code="
          route.departureCode
        "

        :departure-city="
          route.departureCity
        "

        :departure-airport-name="
          route.departureName
        "

        :departure-country-code="
          route.departureCountryCode
        "

        :arrival-code="
          route.arrivalCode
        "

        :arrival-city="
          route.arrivalCity
        "

        :arrival-airport-name="
          route.arrivalName
        "

        :arrival-country-code="
          route.arrivalCountryCode
        "

        :departure-date="
          flight.departureDate
        "

        :departure-time="
          flight.departureTime
        "

        :arrival-time="
          flight.arrivalTime
        "

        :flight-number="
          flight.flightNumber
        "

        :airline-name="
          flight.airlineName
        "

        :aircraft-name="
          flight.aircraftName
        "

        :distance-km="
          flight.distanceKm
        "

        :duration-seconds="
          flight.durationSeconds
        "

        :planned="
          isPlannedDate(
            flight.departureDate,
          )
        "

        @click="
          emit(
            'flight',
            flight,
          )
        "
      />

    </section>

  </aside>
</template>


<style scoped>
.route-panel {
  position: absolute;

  top: 18px;
  right: 18px;

  z-index: 25;

  width: 390px;

  max-height:
    calc(100vh - 36px);

  overflow-y: auto;

  padding: 16px;

  background:
    rgba(
      255,
      255,
      255,
      0.97
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
    0 12px 35px
    rgba(
      0,
      0,
      0,
      0.17
    );
}


.panel-actions {
  display: flex;

  align-items: center;

  justify-content: space-between;

  margin-bottom: 12px;
}


.panel-title {
  color: #666;

  font-size: 11px;
  font-weight: 700;

  text-transform:
    uppercase;

  letter-spacing:
    0.04em;
}


.close-button {
  width: 34px;
  height: 34px;

  border: 0;

  border-radius: 8px;

  background: #f3f3f3;

  color: #444;

  cursor: pointer;

  font-size: 21px;

  line-height: 1;
}


.close-button:hover {
  background: #e9e9e9;
}


/*
|--------------------------------------------------------------------------
| Nagłówek trasy
|--------------------------------------------------------------------------
*/

.route-hero {
  display: grid;

  grid-template-columns:
    minmax(0, 1fr)
    100px
    minmax(0, 1fr);

  align-items: center;

  gap: 9px;

  padding:
    14px 12px;

  background: #f7f7f7;

  border-radius: 12px;
}


.route-side {
  min-width: 0;
}


.route-side--right {
  text-align: right;
}


.route-code-row {
  display: flex;

  align-items: center;

  gap: 6px;
}


.route-code-row--right {
  justify-content: flex-end;
}


.route-code-row strong {
  color: #0b2d5c;

  font-size: 21px;
  font-weight: 800;

  line-height: 1;
}


.route-flag {
  width: 18px;
  height: 13px;

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


.route-city {
  margin-top: 6px;

  overflow: hidden;

  color: #333;

  font-size: 11px;
  font-weight: 650;

  text-overflow: ellipsis;

  white-space: nowrap;
}


.route-airport {
  margin-top: 2px;

  overflow: hidden;

  color: #777;

  font-size: 10px;

  text-overflow: ellipsis;

  white-space: nowrap;
}


.route-middle {
  min-width: 0;

  text-align: center;
}


.route-label {
  color: #777;

  font-size: 8px;

  text-transform:
    uppercase;

  letter-spacing:
    0.03em;
}


.route-line {
  position: relative;

  height: 2px;

  margin:
    8px 0;

  background: #0b2d5c;

  border-radius: 999px;
}


.route-line::before,
.route-line::after {
  position: absolute;

  top: 50%;

  width: 7px;
  height: 7px;

  border-radius: 50%;

  background: #0b2d5c;

  content: '';

  transform:
    translateY(-50%);
}


.route-line::before {
  left: 0;
}


.route-line::after {
  right: 0;
}


.route-plane {
  position: absolute;

  top: 50%;
  left: 50%;

  padding:
    0 4px;

  background: #f7f7f7;

  color: #0b2d5c;

  font-size: 12px;

  transform:
    translate(
      -50%,
      -50%
    );
}


.route-distance {
  color: #555;

  font-size: 10px;
  font-weight: 600;
}


/*
|--------------------------------------------------------------------------
| Liczba zrealizowanych lotów
|--------------------------------------------------------------------------
*/

.flight-count {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-top: 10px;
  padding: 10px 12px;
  background: #f4f4f4;
  border-radius: 9px;
  color: #9ca3af;
}


.flight-count strong {
  color: #222;
  font-size: 30px;
  line-height: 1;
}


.flight-count span {
  color: #666;
  font-size: 12px;
}

.flight-count strong,
.flight-count b,
.flight-count .value,
.flight-count span,
.flight-count .label {
  color: #9ca3af;
}


/*
|--------------------------------------------------------------------------
| Statystyki trasy
|--------------------------------------------------------------------------
*/

.route-summary {
  display: grid;

  grid-template-columns:
    1fr 1fr;

  gap: 6px;

  margin-top: 8px;
}


.summary-card {
  display: flex;

  min-height: 58px;

  flex-direction: column;

  align-items: center;

  justify-content: center;

  padding: 8px;

  border:
    1px solid #e7e7e7;

  border-radius: 9px;

  background:
    rgba(
      255,
      255,
      255,
      0.74
    );

  text-align: center;
}


.summary-card span {
  color: #777;

  font-size: 9px;
}


.summary-card strong {
  margin-top: 4px;

  color: #222;

  font-size: 11px;
  font-weight: 650;

  line-height: 1.25;
}


/*
|--------------------------------------------------------------------------
| Historia
|--------------------------------------------------------------------------
*/

.history {
  margin-top: 18px;
}


.history h3 {
  margin:
    0 0 8px;

  color: #444;

  font-size: 12px;
}


:deep(.fi) {
  display: inline-block;
}


@media (
  max-width: 900px
) {
  .route-panel {
    top: auto;

    right: 10px;
    bottom: 10px;
    left: 10px;

    width: auto;

    max-height: 65vh;
  }
}
</style>