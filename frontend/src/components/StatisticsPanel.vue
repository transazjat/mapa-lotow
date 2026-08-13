<script setup lang="ts">
import {
  computed,
} from 'vue'

import type {
  Flight,
} from '../types/flight'


const props = defineProps<{
  flights: Flight[]
}>()


const emit = defineEmits<{
  airports: []

  report: [
    type:
      | 'flights'
      | 'distance'
      | 'duration',
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


const airports =
  computed(
    () => {
      const ids =
        new Set<number>()

      for (
        const flight
        of props.flights
      ) {
        ids.add(
          flight.departure_airport_id,
        )

        ids.add(
          flight.arrival_airport_id,
        )
      }

      return ids.size
    },
  )


const airlines =
  computed(
    () =>
      new Set(
        props.flights
          .map(
            (flight) =>
              flight.airline_id,
          )
          .filter(
            (
              value,
            ): value is number =>
              value !== null,
          ),
      ).size,
  )


const aircraft =
  computed(
    () =>
      new Set(
        props.flights
          .map(
            (flight) =>
              flight.aircraft_type_id,
          )
          .filter(
            (
              value,
            ): value is number =>
              value !== null,
          ),
      ).size,
  )


const routes =
  computed(
    () =>
      new Set(
        props.flights.map(
          (flight) =>
            `${flight.departure_airport_id}>${flight.arrival_airport_id}`,
        ),
      ).size,
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
</script>


<template>
  <section class="statistics-panel">

    <section class="primary-summary">

      <button
        type="button"
        class="primary-card"
        @click="
          emit(
            'report',
            'flights',
          )
        "
      >

        <div class="primary-card__content">

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


        <div class="details-button">

          <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              d="M9 5l7 7-7 7"
            />
          </svg>

        </div>

      </button>


      <button
        type="button"
        class="primary-card"
        @click="
          emit(
            'report',
            'distance',
          )
        "
      >

        <div class="primary-card__content">

          <strong>
            {{
              formatNumber(
                totalDistance,
              )
            }}
          </strong>

          <span>
            kilometrów
          </span>

        </div>


        <div class="details-button">

          <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              d="M9 5l7 7-7 7"
            />
          </svg>

        </div>

      </button>


      <button
        type="button"
        class="primary-card"
        @click="
          emit(
            'report',
            'duration',
          )
        "
      >

        <div class="primary-card__content">

          <strong>
            {{
              formatDuration(
                totalDuration,
              )
            }}
          </strong>

          <span>
            w powietrzu
          </span>

        </div>


        <div class="details-button">

          <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              d="M9 5l7 7-7 7"
            />
          </svg>

        </div>

      </button>

    </section>


    <section class="statistics-menu">

      <button
        type="button"
        class="statistics-menu-card statistics-menu-card--active"
        @click="
          emit(
            'airports',
          )
        "
      >

        <div class="statistics-menu-card__value">
          {{ airports }}
        </div>

        <div class="statistics-menu-card__label">
          lotnisk
        </div>

        <div class="statistics-menu-card__more">
          Szczegóły →
        </div>

      </button>


      <div class="statistics-menu-card">

        <div class="statistics-menu-card__value">
          {{ airlines }}
        </div>

        <div class="statistics-menu-card__label">
          linii lotniczych
        </div>

        <div class="statistics-menu-card__more statistics-menu-card__more--muted">
          wkrótce
        </div>

      </div>


      <div class="statistics-menu-card">

        <div class="statistics-menu-card__value">
          {{ aircraft }}
        </div>

        <div class="statistics-menu-card__label">
          typów samolotów
        </div>

        <div class="statistics-menu-card__more statistics-menu-card__more--muted">
          wkrótce
        </div>

      </div>


      <div class="statistics-menu-card">

        <div class="statistics-menu-card__value">
          {{ routes }}
        </div>

        <div class="statistics-menu-card__label">
          tras
        </div>

        <div class="statistics-menu-card__more statistics-menu-card__more--muted">
          wkrótce
        </div>

      </div>

    </section>

  </section>
</template>


<style scoped>
.statistics-panel {
  margin-top: 14px;
}


.primary-summary {
  display: grid;

  gap: 7px;
}


.primary-card {
  display: grid;

  width: 100%;
  min-height: 88px;

  grid-template-columns:
    1fr 42px;

  align-items: stretch;

  padding:
    8px;

  border: 0;

  border-radius: 10px;

  background: #f4f4f4;

  cursor: pointer;

  text-align: center;

  transition:
    background 0.15s ease,
    transform 0.15s ease;
}


.primary-card:hover {
  background: #eeeeee;

  transform:
    translateY(-1px);
}


.primary-card__content {
  display: flex;

  flex-direction: column;

  align-items: center;

  justify-content: center;

  padding-left: 8px;
}


.primary-card strong {
  color: #9ca3af;

  font-size: 20px;
  font-weight: 700;

  line-height: 1.1;
}


.primary-card span {
  margin-top: 8px;

  color: #777;

  font-size: 11px;
}


/*
|--------------------------------------------------------------------------
| Prawy przycisk
|--------------------------------------------------------------------------
*/

.details-button {
  display: flex;

  align-items: center;

  justify-content: center;

  width: 100%;
  height: 100%;

  min-height: 72px;

  border:
    1px solid
    rgba(
      156,
      163,
      175,
      0.26
    );

  border-radius: 8px;

  background:
    rgba(
      255,
      255,
      255,
      0.8
    );

  color: #9ca3af;

  transition:
    color 0.15s ease,
    background 0.15s ease,
    border-color 0.15s ease;
}


.details-button svg {
  width: 18px;
  height: 18px;

  display: block;

  fill: none;

  stroke: currentColor;

  stroke-width: 2;

  stroke-linecap: round;

  stroke-linejoin: round;
}


.primary-card:hover
.details-button {
  border-color:
    rgba(
      11,
      45,
      92,
      0.18
    );

  background: #fff;

  color: #0b2d5c;
}


/*
|--------------------------------------------------------------------------
| Spis treści
|--------------------------------------------------------------------------
*/

.statistics-menu {
  display: grid;

  grid-template-columns:
    1fr 1fr;

  gap: 7px;

  margin-top: 9px;
}


.statistics-menu-card {
  display: flex;

  min-height: 96px;

  flex-direction: column;

  align-items: center;

  justify-content: center;

  padding:
    10px 8px;

  border:
    1px solid #e4e4e4;

  border-radius: 9px;

  background:
    rgba(
      255,
      255,
      255,
      0.78
    );

  text-align: center;
}


button.statistics-menu-card {
  cursor: pointer;

  transition:
    background 0.15s ease,
    border-color 0.15s ease,
    transform 0.15s ease;
}


button.statistics-menu-card:hover {
  border-color:
    rgba(
      11,
      45,
      92,
      0.18
    );

  background:
    rgba(
      11,
      45,
      92,
      0.035
    );

  transform:
    translateY(-1px);
}


.statistics-menu-card--active {
  border-left:
    3px solid #9ca3af;
}


.statistics-menu-card__value {
  color: #9ca3af;

  font-size: 20px;
  font-weight: 700;
}


.statistics-menu-card__label {
  margin-top: 6px;

  color: #666;

  font-size: 11px;
}


.statistics-menu-card__more {
  margin-top: 10px;

  color: #6b7280;

  font-size: 11px;
  font-weight: 650;
}


.statistics-menu-card__more--muted {
  color: #aaa;

  font-weight: 500;
}
</style>