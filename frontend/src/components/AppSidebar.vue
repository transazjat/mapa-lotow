<script setup lang="ts">
import {
  computed,
} from 'vue'

import FlightsPanel from './FlightsPanel.vue'
import StatisticsPanel from './StatisticsPanel.vue'

import flightSignUrl from '../assets/branding/mapa-lotow-symbol.png'
import transAzjaLogoUrl from '../assets/branding/transazja-logo.png'

import type {
  Flight,
  FlightScope,
  SidebarTab,
} from '../types/flight'


const props = defineProps<{
  flights: Flight[]
  activeTab: SidebarTab
  scope: FlightScope
  collapsed: boolean
  activeFlightId: number | null
  initialAircraftFilterKey?: string | null
}>()


const emit = defineEmits<{
  toggle: []
  tab: [tab: SidebarTab]
  scope: [scope: FlightScope]
  flight: [flight: Flight]
  filteredFlights: [flights: Flight[]]
  statisticsAirports: []
  statisticsReport: [type: 'flights' | 'distance' | 'duration']
  statisticsSection: [type: 'airlines' | 'aircraft' | 'routes' | 'countries']
  aircraftFilterChanged: [key: string | null]
  addFlight: []
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


const transAzjaDestinations = [
  {
    label: 'NEPAL',
    href: 'https://wyprawy.transazja.pl/nepal-wycieczka',
  },
  {
    label: 'INDIE',
    href: 'https://wyprawy.transazja.pl/indie-wycieczka',
  },
  {
    label: 'LAOS',
    href: 'https://wyprawy.transazja.pl/laos-wycieczka',
  },
  {
    label: 'TYBET',
    href: 'https://wyprawy.transazja.pl/tybet-wycieczka',
  },
  {
    label: 'KAMBODŻA',
    href: 'https://wyprawy.transazja.pl/kambodza-wycieczka',
  },
  {
    label: 'SRI LANKA',
    href: 'https://wyprawy.transazja.pl/sri-lanka-wycieczka',
  },
  {
    label: 'OMAN',
    href: 'https://wyprawy.transazja.pl/oman-wycieczka',
  },
  {
    label: 'BIRMA',
    href: 'https://wyprawy.transazja.pl/birma-wycieczka',
  },
  {
    label: 'INDONEZJA',
    href: 'https://wyprawy.transazja.pl/bali-wycieczka',
  },
  {
    label: 'CHINY',
    href: 'https://wyprawy.transazja.pl/chiny-wycieczka',
  },
  {
    label: 'NAMIBIA',
    href: 'https://wyprawy.transazja.pl/namibia-wycieczka',
  },
] as const


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


function openStatisticsSection(
  type:
    | 'distance'
    | 'duration'
    | 'airports'
    | 'airlines'
    | 'aircraft'
    | 'routes',
): void {
  emit('tab', 'statistics')

  if (
    type === 'distance' ||
    type === 'duration'
  ) {
    emit(
      'statisticsReport',
      type,
    )

    return
  }

  if (type === 'airports') {
    emit('statisticsAirports')
    return
  }

  emit('statisticsSection', type)
}
</script>


<template>
  <aside
    class="sidebar"
    :class="{
      'sidebar--collapsed':
        collapsed,
    }"
  >
    <button
      type="button"
      class="sidebar-toggle"
      :title="
        collapsed
          ? 'Rozwiń panel'
          : 'Zwiń panel'
      "
      @click="emit('toggle')"
    >
      <svg
        viewBox="0 0 24 24"
        aria-hidden="true"
        :class="{
          'sidebar-toggle__icon--collapsed':
            collapsed,
        }"
      >
        <path d="M14 6l-6 6 6 6" />
      </svg>
    </button>

    <div
      v-if="!collapsed"
      class="sidebar-content"
    >
      <header class="sidebar-header">
        <div class="brand-lockup">
          <img
            :src="flightSignUrl"
            class="brand-symbol"
            alt=""
            aria-hidden="true"
          >

          <div
            class="brand-board"
            aria-label="Mapa lotów"
          >
            <div class="brand-board__row">
              <span>M</span>
              <span>A</span>
              <span>P</span>
              <span>A</span>
              <span class="brand-board__empty" />
            </div>

            <div class="brand-board__row">
              <span>L</span>
              <span>O</span>
              <span>T</span>
              <span>Ó</span>
              <span>W</span>
            </div>
          </div>
        </div>

        <div class="user-name">
          Krzysztof
        </div>
      </header>

      <nav class="main-nav">
        <button
          type="button"
          :class="{
            active:
              activeTab === 'map',
          }"
          @click="emit('tab', 'map')"
        >
          Mapa
        </button>

        <button
          type="button"
          :class="{
            active:
              activeTab === 'flights',
          }"
          @click="emit('tab', 'flights')"
        >
          Loty
        </button>

        <button
          type="button"
          :class="{
            active:
              activeTab === 'statistics',
          }"
          @click="emit('tab', 'statistics')"
        >
          Statystyki
        </button>

        <button
          type="button"
          :class="{
            active:
              activeTab === 'account',
          }"
          @click="emit('tab', 'account')"
        >
          Konto
        </button>
      </nav>

      <button
        type="button"
        class="add-flight-button"
        @click="emit('addFlight')"
      >
        <span class="add-flight-button__plus">
          +
        </span>

        <span>
          Dodaj lot
        </span>
      </button>

      <section class="scope-section">
        <div class="scope-title">
          Zakres lotów
        </div>

        <div class="scope-switch">
          <button
            type="button"
            :class="{
              active:
                scope === 'completed',
            }"
            @click="emit('scope', 'completed')"
          >
            Odbyte
          </button>

          <button
            type="button"
            :class="{
              active:
                scope === 'all',
            }"
            @click="emit('scope', 'all')"
          >
            Wszystkie
          </button>

          <button
            type="button"
            :class="{
              active:
                scope === 'planned',
            }"
            @click="emit('scope', 'planned')"
          >
            Zaplanowane
          </button>
        </div>
      </section>

      <section
        v-if="activeTab === 'map'"
        class="tab-content"
      >
        <div class="main-stat">
          <strong>
            {{ formatNumber(flights.length) }}
          </strong>

          <span>
            lotów
          </span>
        </div>

        <div class="stats-grid">
          <button
            type="button"
            class="stats-card stats-card--link"
            @click="openStatisticsSection('distance')"
          >
            <strong>
              {{ formatNumber(totalDistance) }}
            </strong>

            <span>
              kilometrów
            </span>
          </button>

          <button
            type="button"
            class="stats-card stats-card--link"
            @click="openStatisticsSection('duration')"
          >
            <strong>
              {{ formatDuration(totalDuration) }}
            </strong>

            <span>
              w powietrzu
            </span>
          </button>

          <button
            type="button"
            class="stats-card stats-card--link"
            @click="openStatisticsSection('airports')"
          >
            <strong>
              {{ airports }}
            </strong>

            <span>
              lotnisk
            </span>
          </button>

          <button
            type="button"
            class="stats-card stats-card--link"
            @click="openStatisticsSection('airlines')"
          >
            <strong>
              {{ airlines }}
            </strong>

            <span>
              linii lotniczych
            </span>
          </button>

          <button
            type="button"
            class="stats-card stats-card--link"
            @click="openStatisticsSection('aircraft')"
          >
            <strong>
              {{ aircraft }}
            </strong>

            <span>
              typów samolotów
            </span>
          </button>

          <button
            type="button"
            class="stats-card stats-card--link"
            @click="openStatisticsSection('routes')"
          >
            <strong>
              {{ routes }}
            </strong>

            <span>
              tras
            </span>
          </button>
        </div>
      </section>

      <FlightsPanel
        v-else-if="
          activeTab === 'flights'
        "
        :flights="flights"
        :active-flight-id="activeFlightId"
        :initial-aircraft-filter-key="initialAircraftFilterKey"
        @flight="
          emit(
            'flight',
            $event,
          )
        "
        @filtered="
          emit(
            'filteredFlights',
            $event,
          )
        "
        @aircraft-filter-changed="
          emit(
            'aircraftFilterChanged',
            $event,
          )
        "
      />

      <StatisticsPanel
        v-else-if="
          activeTab === 'statistics'
        "
        :flights="flights"
        @airports="
          emit(
            'statisticsAirports',
          )
        "
        @report="
          emit(
            'statisticsReport',
            $event,
          )
        "
        @section="
          emit(
            'statisticsSection',
            $event,
          )
        "
      />

      <section
        v-else
        class="account-placeholder"
      >
        <strong>Konto</strong>

        <p>
          Funkcje konta dodamy później.
        </p>
      </section>
    </div>

    <footer
      v-if="!collapsed"
      class="owner-brand"
    >
      <div class="owner-brand__logo-row">
        <a
          class="owner-brand__logo-link"
          href="https://wyprawy.transazja.pl/"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="TransAzja - wyprawy do Azji"
        >
          <img
            :src="transAzjaLogoUrl"
            class="owner-brand__logo"
            alt="TransAzja"
          >
        </a>
      </div>

      <div class="owner-brand__tagline">
        Najlepsze wyprawy do
      </div>

      <nav
        class="owner-brand__destinations"
        aria-label="Wyprawy TransAzja"
      >
        <a
          v-for="destination in transAzjaDestinations"
          :key="destination.label"
          :href="destination.href"
          target="_blank"
          rel="noopener noreferrer"
        >
          {{ destination.label }}
        </a>
      </nav>
    </footer>
  </aside>
</template>

<style scoped>
.sidebar {
  position: absolute;
  top: 18px;
  left: 18px;
  z-index: 20;
  width: 370px;
  max-height: calc(100vh - 36px);
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 16px;
  box-shadow: 0 12px 35px rgba(0, 0, 0, 0.18);
}

.sidebar--collapsed {
  width: 52px;
  height: 60px;
}

.sidebar-toggle {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 5;
  display: flex;
  width: 34px;
  height: 34px;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 1px solid #d9dde2;
  border-radius: 9px;
  background: #eef0f2;
  color: #8a929d;
  cursor: pointer;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.055);
  transition:
    background 0.15s ease,
    border-color 0.15s ease,
    color 0.15s ease,
    box-shadow 0.15s ease;
}

.sidebar-toggle:hover {
  border-color: #cbd1d8;
  background: #e5e8ec;
  color: #697482;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.075);
}

.sidebar-toggle svg {
  display: block;
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: transform 0.2s ease;
}

.sidebar-toggle__icon--collapsed {
  transform: rotate(180deg);
}

.sidebar-content {
  max-height: calc(100vh - 36px);
  overflow-y: auto;
  padding: 20px 20px 132px;
}

.sidebar-header {
  padding-right: 42px;
  text-align: left;
}

.brand-lockup {
  display: flex;
  align-items: flex-start;
  justify-content: flex-start;
  gap: 10px;
}

.brand-symbol {
  display: block;
  width: 50px;
  height: 49px;
  flex: 0 0 auto;
  object-fit: contain;
}

.brand-board {
  display: grid;
  gap: 2px;
  text-align: left;
}

.brand-board__row {
  display: flex;
  gap: 2px;
}

.brand-board__row span {
  display: inline-flex;
  width: 21px;
  height: 22px;
  align-items: center;
  justify-content: center;
  border: 1px solid #111820;
  border-radius: 2px;
  background:
    linear-gradient(
      to bottom,
      #30343a 0%,
      #30343a 48%,
      #1f2227 49%,
      #1f2227 100%
    );
  box-shadow:
    inset 0 1px 0
    rgba(255, 255, 255, 0.08);
  color: #f3f4f6;
  font-family:
    "Courier New",
    ui-monospace,
    monospace;
  font-size: 15px;
  font-weight: 700;
  line-height: 1;
  text-align: center;
  text-shadow:
    0 1px 1px
    rgba(0, 0, 0, 0.55);
}

.brand-board__empty {
  color: transparent;
}

.user-name {
  margin-top: 8px;
  padding: 0;
  color: #64748b;
  font-size: 13px;
  font-weight: 600;
  text-align: center;
}

.main-nav {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 3px;
  margin-top: 18px;
  padding: 3px;
  background: #f2f2f2;
  border-radius: 10px;
}

.main-nav button {
  min-height: 42px;
  padding: 7px 3px;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: #555;
  cursor: pointer;
  font-size: 11px;
  transition:
    background 0.15s ease,
    color 0.15s ease,
    box-shadow 0.15s ease;
}

.main-nav button:hover {
  background: rgba(255, 255, 255, 0.55);
}

.main-nav button.active {
  background: white;
  color: #111;
  font-weight: 700;
  box-shadow: 0 1px 5px rgba(0, 0, 0, 0.12);
}

.add-flight-button {
  display: flex;
  width: 100%;
  min-height: 54px;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 11px;
  padding: 0 16px;
  border: 1px solid #a9bacd;
  border-radius: 10px;
  background:
    linear-gradient(
      to bottom,
      #dbe6f1,
      #cfdeeb
    );
  color: #0b2d5c;
  cursor: pointer;
  box-shadow:
    0 2px 6px
    rgba(11, 45, 92, 0.10);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.055em;
  text-transform: uppercase;
  transition:
    background 0.15s ease,
    border-color 0.15s ease,
    transform 0.15s ease,
    box-shadow 0.15s ease;
}

.add-flight-button:hover {
  border-color: #8ea6bf;
  background:
    linear-gradient(
      to bottom,
      #d2e0ed,
      #c3d5e5
    );
  box-shadow:
    0 4px 10px
    rgba(11, 45, 92, 0.13);
  transform: translateY(-1px);
}

.add-flight-button__plus {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 16px;
  transform: translateY(-1px);
  font-size: 18px;
  font-weight: 700;
  line-height: 16px;
}


.scope-section {
  margin-top: 12px;
}

.scope-title {
  margin-bottom: 9px;
  color: #777;
  font-size: 10px;
  font-weight: 700;
  text-align: center;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.scope-switch {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 3px;
  padding: 3px;
  background: #f3f3f3;
  border-radius: 9px;
}

.scope-switch button {
  min-height: 39px;
  padding: 6px 2px;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: #666;
  cursor: pointer;
  font-size: 10px;
  transition:
    background 0.15s ease,
    color 0.15s ease,
    box-shadow 0.15s ease;
}

.scope-switch button:hover {
  background: rgba(255, 255, 255, 0.5);
}

.scope-switch button.active {
  background: white;
  color: #0b2d5c;
  font-weight: 700;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
}

.tab-content,
.account-placeholder {
  margin-top: 14px;
}



.main-stat {
  padding: 14px 14px 13px;
  background: #f4f4f5;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  text-align: center;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.main-stat strong {
  display: block;
  color: #9ca3af;
  font-size: 30px;
  font-weight: 700;
  line-height: 1;
}

.main-stat span {
  display: block;
  margin-top: 6px;
  color: #666f7a;
  font-size: 12px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
  margin-top: 8px;
}

.stats-card {
  display: flex;
  min-height: 84px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 11px 9px;
  border: 1px solid #d9e0e8;
  border-radius: 12px;
  background: #fbfbfc;
  text-align: center;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.035);
}

.stats-card strong {
  display: block;
  color: #9ca3af;
  font-size: 15px;
  font-weight: 700;
  line-height: 1.15;
}

.stats-card span {
  display: block;
  margin-top: 7px;
  color: #66707c;
  font-size: 11px;
  font-weight: 400;
  line-height: 1.2;
}

.stats-card--link {
  cursor: pointer;
  transition:
    transform 0.15s ease,
    border-color 0.15s ease,
    box-shadow 0.15s ease,
    background 0.15s ease;
}

.stats-card--link:hover {
  border-color: #cbd5e1;
  background: #ffffff;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.07);
  transform: translateY(-1px);
}

.stats-card--link:focus-visible {
  outline: 2px solid rgba(11, 45, 92, 0.18);
  outline-offset: 2px;
}

.account-placeholder {
  padding: 20px 5px;
  color: #666;
  font-size: 11px;
  text-align: center;
}


.owner-brand {
  position: absolute;
  right: 20px;
  bottom: 2px;
  left: 20px;
  z-index: 7;
  display: flex;
  min-height: 122px;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  padding: 11px 8px 2px;
  border-top: 1px solid rgba(0, 0, 0, 0.07);
  background:
    linear-gradient(
      to bottom,
      rgba(255, 255, 255, 0.78),
      rgba(255, 255, 255, 0.99)
    );
  backdrop-filter: blur(8px);
}

.owner-brand__logo-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}


.owner-brand__logo-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
}

.owner-brand__logo-link:focus-visible {
  outline: 2px solid rgba(11, 45, 92, 0.18);
  outline-offset: 3px;
  border-radius: 6px;
}

.owner-brand__logo {
  display: block;
  width: auto;
  height: 50px;
  object-fit: contain;
}

.owner-brand__tagline {
  margin-top: 13px;
  color: #64748b;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.02em;
  line-height: 1.2;
  text-align: center;
  text-transform: none;
}

.owner-brand__destinations {
  display: flex;
  max-width: 310px;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 2px 8px;
  margin-top: 4px;
  margin-bottom: 0;
  line-height: 1.12;
}

.owner-brand__destinations a {
  color: #8d95a1;
  font-size: 9px;
  font-weight: 600;
  letter-spacing: 0.045em;
  text-decoration: none;
  text-transform: uppercase;
  transition: color 0.15s ease;
}

.owner-brand__destinations a:hover {
  color: #0b2d5c;
  text-decoration: underline;
}

@media (max-width: 700px) {
  .sidebar {
    top: 10px;
    left: 10px;
    width: calc(100% - 20px);
    max-height: 55vh;
  }

  .brand-symbol {
    width: 44px;
    height: 43px;
  }

  .brand-board__row span {
    width: 19px;
    height: 20px;
    font-size: 14px;
  }

  .user-name {
    padding-left: 54px;
    font-size: 13px;
  }

  .owner-brand {
    right: 14px;
    left: 14px;
    min-height: 116px;
  }

  .owner-brand__logo {
    height: 46px;
  }

  .owner-brand__destinations {
    max-width: 280px;
  }

  .sidebar--collapsed {
    width: 52px;
    height: 52px;
  }
}
</style>
