<script setup lang="ts">
import {
  computed,
} from 'vue'

import FlightsPanel from './FlightsPanel.vue'
import StatisticsPanel from './StatisticsPanel.vue'

import type {
  Flight,
  FlightScope,
  SidebarTab,
} from '../types/flight'


const props = defineProps<{
  flights: Flight[]

  activeTab:
    SidebarTab

  scope:
    FlightScope

  collapsed:
    boolean

  activeFlightId:
    number | null
}>()


const emit = defineEmits<{
  toggle: []

  tab:
    [tab: SidebarTab]

  scope:
    [scope: FlightScope]

  flight:
    [flight: Flight]

  filteredFlights:
    [flights: Flight[]]

  statisticsAirports: []

  statisticsReport: [
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
  const minutes =
    Math.floor(
      seconds / 60,
    )

  const hours =
    Math.floor(
      minutes / 60,
    )

  const rest =
    minutes % 60

  return `${formatNumber(hours)} h ${rest} min`
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
      @click="
        emit(
          'toggle',
        )
      "
    >
      {{ collapsed ? '›' : '‹' }}
    </button>


    <div
      v-if="!collapsed"
      class="sidebar-content"
    >

      <header class="sidebar-header">

        <div class="app-name">
          Mapa lotów
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
              activeTab ===
              'map',
          }"
          @click="
            emit(
              'tab',
              'map',
            )
          "
        >
          Mapa
        </button>


        <button
          type="button"
          :class="{
            active:
              activeTab ===
              'flights',
          }"
          @click="
            emit(
              'tab',
              'flights',
            )
          "
        >
          Loty
        </button>


        <button
          type="button"
          :class="{
            active:
              activeTab ===
              'statistics',
          }"
          @click="
            emit(
              'tab',
              'statistics',
            )
          "
        >
          Statystyki
        </button>


        <button
          type="button"
          :class="{
            active:
              activeTab ===
              'account',
          }"
          @click="
            emit(
              'tab',
              'account',
            )
          "
        >
          Konto
        </button>

      </nav>


      <section class="scope-section">

        <div class="scope-title">
          Zakres lotów
        </div>


        <div class="scope-switch">

          <button
            type="button"
            :class="{
              active:
                scope ===
                'completed',
            }"
            @click="
              emit(
                'scope',
                'completed',
              )
            "
          >
            Odbyte
          </button>


          <button
            type="button"
            :class="{
              active:
                scope ===
                'all',
            }"
            @click="
              emit(
                'scope',
                'all',
              )
            "
          >
            Wszystkie
          </button>


          <button
            type="button"
            :class="{
              active:
                scope ===
                'planned',
            }"
            @click="
              emit(
                'scope',
                'planned',
              )
            "
          >
            Zaplanowane
          </button>

        </div>

      </section>


      <section
        v-if="
          activeTab ===
          'map'
        "
        class="tab-content"
      >

        <div class="main-stat">

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


        <div class="stats-grid">

          <div>

            <strong>
              {{
                formatNumber(
                  totalDistance,
                )
              }}
            </strong>

            <span>
              km
            </span>

          </div>


          <div>

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


          <div>

            <strong>
              {{ airports }}
            </strong>

            <span>
              lotnisk
            </span>

          </div>


          <div>

            <strong>
              {{ airlines }}
            </strong>

            <span>
              linii
            </span>

          </div>


          <div>

            <strong>
              {{ aircraft }}
            </strong>

            <span>
              typów samolotów
            </span>

          </div>


          <div>

            <strong>
              {{ routes }}
            </strong>

            <span>
              tras
            </span>

          </div>

        </div>

      </section>


      <FlightsPanel
        v-else-if="
          activeTab ===
          'flights'
        "

        :flights="flights"

        :active-flight-id="
          activeFlightId
        "

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
      />


      <StatisticsPanel
        v-else-if="
          activeTab ===
          'statistics'
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
      />


      <section
        v-else
        class="account-placeholder"
      >

        <strong>
          Konto
        </strong>

        <p>
          Funkcje konta dodamy później.
        </p>

      </section>

    </div>

  </aside>
</template>


<style scoped>
.sidebar {
  position: absolute;

  top: 18px;
  left: 18px;

  z-index: 20;

  width: 370px;

  max-height:
    calc(
      100vh - 36px
    );

  background:
    rgba(
      255,
      255,
      255,
      0.96
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
      0.18
    );
}


.sidebar--collapsed {
  width: 52px;
  height: 52px;
}


.sidebar-toggle {
  position: absolute;

  top: 10px;
  right: 10px;

  z-index: 2;

  width: 32px;
  height: 32px;

  border: 0;

  border-radius: 8px;

  background:
    rgba(
      0,
      0,
      0,
      0.06
    );

  cursor: pointer;

  font-size: 24px;
}


.sidebar-content {
  max-height:
    calc(
      100vh - 36px
    );

  overflow-y: auto;

  padding: 20px;
}


.sidebar-header {
  padding-right: 42px;
}


.app-name {
  font-size: 21px;
  font-weight: 800;
}


.user-name {
  margin-top: 2px;

  color: #666;

  font-size: 12px;
}


.main-nav {
  display: grid;

  grid-template-columns:
    repeat(
      4,
      1fr
    );

  gap: 3px;

  margin-top: 17px;

  padding: 3px;

  background: #f2f2f2;

  border-radius: 9px;
}


.main-nav button {
  padding:
    7px 3px;

  border: 0;

  border-radius: 6px;

  background: transparent;

  color: #555;

  cursor: pointer;

  font-size: 11px;
}


.main-nav button.active {
  background: white;

  color: #111;

  font-weight: 700;

  box-shadow:
    0 1px 4px
    rgba(
      0,
      0,
      0,
      0.12
    );
}


.scope-section {
  margin-top: 12px;
}


.scope-title {
  margin-bottom: 5px;

  color: #777;

  font-size: 10px;
  font-weight: 700;

  text-transform:
    uppercase;

  letter-spacing:
    0.04em;
}


.scope-switch {
  display: grid;

  grid-template-columns:
    repeat(
      3,
      1fr
    );

  gap: 3px;

  padding: 3px;

  background: #f3f3f3;

  border-radius: 8px;
}


.scope-switch button {
  padding:
    6px 2px;

  border: 0;

  border-radius: 6px;

  background: transparent;

  color: #666;

  cursor: pointer;

  font-size: 10px;
}


.scope-switch button.active {
  background: white;

  color: #0b2d5c;

  font-weight: 700;

  box-shadow:
    0 1px 4px
    rgba(
      0,
      0,
      0,
      0.1
    );
}


.tab-content,
.account-placeholder {
  margin-top: 14px;
}


.main-stat {
  padding: 14px;

  background: #f4f4f4;

  border-radius: 10px;
}


.main-stat strong {
  display: block;

  font-size: 30px;
}


.main-stat span {
  color: #666;

  font-size: 11px;
}


.stats-grid {
  display: grid;

  grid-template-columns:
    repeat(
      2,
      1fr
    );

  gap: 6px;

  margin-top: 7px;
}


.stats-grid div {
  padding: 10px;

  border:
    1px solid #e7e7e7;

  border-radius: 8px;
}


.stats-grid strong {
  display: block;

  font-size: 13px;
}


.stats-grid span {
  display: block;

  margin-top: 2px;

  color: #777;

  font-size: 10px;
}


.account-placeholder {
  padding:
    20px 5px;

  color: #666;

  font-size: 11px;
}


@media (
  max-width: 700px
) {
  .sidebar {
    top: 10px;
    left: 10px;

    width:
      calc(
        100% - 20px
      );

    max-height: 55vh;
  }


  .sidebar--collapsed {
    width: 52px;
  }
}
</style>