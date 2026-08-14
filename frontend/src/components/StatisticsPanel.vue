<script setup lang="ts">
import { computed } from 'vue'
import type { Flight } from '../types/flight'

const props = defineProps<{
  flights: Flight[]
}>()

const emit = defineEmits<{
  airports: []
  report: [type: 'flights' | 'distance' | 'duration']
  section: [type: 'airlines' | 'aircraft' | 'routes' | 'countries']
}>()

const totalDistance = computed(() =>
  props.flights.reduce((sum, flight) => sum + (flight.distance_km ?? 0), 0),
)

const totalDuration = computed(() =>
  props.flights.reduce((sum, flight) => sum + (flight.duration_seconds ?? 0), 0),
)

const airports = computed(() => {
  const ids = new Set<number>()

  for (const flight of props.flights) {
    ids.add(flight.departure_airport_id)
    ids.add(flight.arrival_airport_id)
  }

  return ids.size
})

const countries = computed(() => {
  const keys = new Set<string>()

  for (const flight of props.flights) {
    if (flight.departure_country_code) {
      keys.add(flight.departure_country_code.toUpperCase())
    } else if (flight.departure_country) {
      keys.add(flight.departure_country)
    }

    if (flight.arrival_country_code) {
      keys.add(flight.arrival_country_code.toUpperCase())
    } else if (flight.arrival_country) {
      keys.add(flight.arrival_country)
    }
  }

  return keys.size
})

const airlines = computed(
  () =>
    new Set(
      props.flights
        .map((flight) => flight.airline_id)
        .filter((value): value is number => value !== null),
    ).size,
)

const aircraft = computed(
  () =>
    new Set(
      props.flights
        .map((flight) => flight.aircraft_type_id)
        .filter((value): value is number => value !== null),
    ).size,
)

const routes = computed(
  () =>
    new Set(
      props.flights.map(
        (flight) =>
          `${flight.departure_airport_id}>${flight.arrival_airport_id}`,
      ),
    ).size,
)

function formatNumber(value: number): string {
  return new Intl.NumberFormat(undefined).format(value)
}

function formatDuration(seconds: number): string {
  const totalMinutes = Math.floor(seconds / 60)
  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60

  return `${formatNumber(hours)} h ${minutes} min`
}
</script>

<template>
  <section class="statistics-panel">
    <section class="primary-summary">
      <button
        type="button"
        class="primary-card"
        @click="emit('report', 'flights')"
      >
        <div class="primary-card__content">
          <strong>{{ formatNumber(flights.length) }}</strong>
          <span>lotów</span>
        </div>

        <div class="details-button">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M9 5l7 7-7 7" />
          </svg>
        </div>
      </button>

      <button
        type="button"
        class="primary-card"
        @click="emit('report', 'distance')"
      >
        <div class="primary-card__content">
          <strong>{{ formatNumber(totalDistance) }}</strong>
          <span>kilometrów</span>
        </div>

        <div class="details-button">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M9 5l7 7-7 7" />
          </svg>
        </div>
      </button>

      <button
        type="button"
        class="primary-card"
        @click="emit('report', 'duration')"
      >
        <div class="primary-card__content">
          <strong>{{ formatDuration(totalDuration) }}</strong>
          <span>w powietrzu</span>
        </div>

        <div class="details-button">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M9 5l7 7-7 7" />
          </svg>
        </div>
      </button>
    </section>

    <section class="statistics-menu">
      <button
        type="button"
        class="statistics-menu-card"
        @click="emit('airports')"
      >
        <div class="statistics-menu-card__value">{{ airports }}</div>
        <div class="statistics-menu-card__label">lotnisk</div>
        <div class="statistics-menu-card__more">Szczegóły →</div>
      </button>

      <button
        type="button"
        class="statistics-menu-card"
        @click="emit('section', 'countries')"
      >
        <div class="statistics-menu-card__value">{{ countries }}</div>
        <div class="statistics-menu-card__label">państw</div>
        <div class="statistics-menu-card__more">Szczegóły →</div>
      </button>

      <button
        type="button"
        class="statistics-menu-card"
        @click="emit('section', 'airlines')"
      >
        <div class="statistics-menu-card__value">{{ airlines }}</div>
        <div class="statistics-menu-card__label">linii lotniczych</div>
        <div class="statistics-menu-card__more">Szczegóły →</div>
      </button>

      <button
        type="button"
        class="statistics-menu-card"
        @click="emit('section', 'aircraft')"
      >
        <div class="statistics-menu-card__value">{{ aircraft }}</div>
        <div class="statistics-menu-card__label">typów samolotów</div>
        <div class="statistics-menu-card__more">Szczegóły →</div>
      </button>

      <button
        type="button"
        class="statistics-menu-card"
        @click="emit('section', 'routes')"
      >
        <div class="statistics-menu-card__value">{{ routes }}</div>
        <div class="statistics-menu-card__label">tras</div>
        <div class="statistics-menu-card__more">Szczegóły →</div>
      </button>
    </section>
  </section>
</template>

<style scoped>
.statistics-panel {
  margin-top: 10px;
}

.primary-summary {
  display: grid;
  gap: 6px;
}

.primary-card {
  display: grid;
  width: 100%;
  min-height: 82px;
  grid-template-columns: 1fr 46px;
  align-items: stretch;
  padding: 7px;
  border: 1px solid #e1e4e8;
  border-radius: 11px;
  background: #f3f4f6;
  cursor: pointer;
  text-align: center;
  transition:
    background 0.15s ease,
    border-color 0.15s ease,
    transform 0.15s ease,
    box-shadow 0.15s ease;
}

.primary-card:hover {
  border-color: #d6dae0;
  background: #eef0f3;
  transform: translateY(-1px);
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
}

.primary-card__content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding-left: 8px;
}

.primary-card strong {
  color: #969eaa;
  font-size: 22px;
  font-weight: 700;
  line-height: 1.05;
}

.primary-card span {
  margin-top: 5px;
  color: #666f7a;
  font-size: 12px;
}

.details-button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 66px;
  border: 1px solid #dde1e6;
  border-radius: 9px;
  background: #f7f8fa;
  color: #9ca3af;
  transition:
    color 0.15s ease,
    background 0.15s ease,
    border-color 0.15s ease;
}

.details-button svg {
  display: block;
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.primary-card:hover .details-button {
  border-color: #d4d9df;
  background: #fafbfc;
  color: #7f8894;
}

.statistics-menu {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 7px;
  margin-top: 8px;
}

.statistics-menu-card {
  display: flex;
  min-height: 108px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 9px 8px;
  border: 1px solid #d9dde3;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.9);
  text-align: center;
  cursor: pointer;
  transition:
    background 0.15s ease,
    border-color 0.15s ease,
    transform 0.15s ease,
    box-shadow 0.15s ease;
}

.statistics-menu-card:hover {
  border-color: #cfd5dc;
  background: #fafbfc;
  transform: translateY(-1px);
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
}



.statistics-menu-card__value {
  color: #969eaa;
  font-size: 24px;
  font-weight: 700;
  line-height: 1;
}

.statistics-menu-card__label {
  margin-top: 6px;
  color: #606771;
  font-size: 13px;
}

.statistics-menu-card__more {
  margin-top: 10px;
  color: #5f6671;
  font-size: 11px;
  font-weight: 650;
}
</style>
