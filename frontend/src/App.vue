<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
  watch,
} from 'vue'

import {
  LngLatBounds,
  Map as MapLibreMap,
  setWorkerUrl,
} from 'maplibre-gl'

import 'maplibre-gl/dist/maplibre-gl.css'

import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'

import AppSidebar from './components/AppSidebar.vue'
import AirportDetailsPanel from './components/AirportDetailsPanel.vue'
import RouteDetailsPanel from './components/RouteDetailsPanel.vue'
import FlightDetailsPanel from './components/FlightDetailsPanel.vue'
import AirportStatisticsPanel from './components/AirportStatisticsPanel.vue'
import StatisticsMetricPanel from './components/StatisticsMetricPanel.vue'
import StatisticsCategoryPanel from './components/StatisticsCategoryPanel.vue'

import {
  getFlight,
  getUserFlights,
} from './services/api'

import {
  addAirportsToMap,
  addFlightsToMap,
  clearHighlightedRoute,
  highlightRoute,
  updateFlightMapData,
} from './map/flightMap'

import {
  buildSelectedRoute,
} from './utils/routeUtils'

import {
  filterFlightsByScope,
} from './utils/flightScope'

import type {
  AirportDirectionStat,
  Flight,
  FlightDetails,
  FlightScope,
  RouteFlight,
  SelectedAirport,
  SelectedRoute,
  SidebarTab,
} from './types/flight'

setWorkerUrl(
  workerUrl,
)

const USER_ID =
  75

const mapTilerKey =
  import.meta.env.VITE_MAPTILER_KEY

if (!mapTilerKey) {
  throw new Error(
    'Brak VITE_MAPTILER_KEY',
  )
}

const mapContainer =
  ref<HTMLDivElement | null>(
    null,
  )

let mapInstance:
  MapLibreMap | null =
  null

const allFlights =
  ref<Flight[]>(
    [],
  )

const scope =
  ref<FlightScope>(
    'completed',
  )

const activeTab =
  ref<SidebarTab>(
    'map',
  )

const sidebarCollapsed =
  ref(false)

const selectedAirport =
  ref<SelectedAirport | null>(
    null,
  )

const selectedRoute =
  ref<SelectedRoute | null>(
    null,
  )

const selectedFlightId =
  ref<number | null>(
    null,
  )

const selectedFlight =
  ref<FlightDetails | null>(
    null,
  )

const flightLoading =
  ref(false)

const flightError =
  ref<string | null>(
    null,
  )

const airportStatisticsOpen =
  ref(false)

const statisticsReport =
  ref<
    | 'flights'
    | 'distance'
    | 'duration'
    | null
  >(
    null,
  )

const statisticsSection =
  ref<
    | 'airlines'
    | 'aircraft'
    | 'routes'
    | 'countries'
    | null
  >(
    null,
  )

const routeReturnSection =
  ref<
    | 'routes'
    | null
  >(
    null,
  )

/*
|--------------------------------------------------------------------------
| Filtr typu samolotu
|--------------------------------------------------------------------------
|
| Może zostać ustawiony zarówno w zakładce Loty, jak i kliknięciem
| konkretnego typu samolotu w raporcie Statystyki -> Typy samolotów.
|
*/

const aircraftFilterKey =
  ref<string | null>(
    null,
  )

const aircraftReturnToStatistics =
  ref(
    false,
  )

const visibleFlights =
  computed(
    () =>
      filterFlightsByScope(
        allFlights.value,
        scope.value,
      ),
  )

const filteredFlights =
  ref<Flight[]>(
    [],
  )

const mapFlights =
  computed(
    () =>
      activeTab.value ===
        'flights'
        ? filteredFlights.value
        : visibleFlights.value,
  )

function fitMapToFlights(
  map:
    MapLibreMap,

  flights:
    Flight[],
): void {
  if (
    flights.length ===
    0
  ) {
    return
  }

  const bounds =
    new LngLatBounds()

  for (
    const flight
    of flights
  ) {
    const departureLongitude =
      Number(
        flight.departure_longitude,
      )

    const departureLatitude =
      Number(
        flight.departure_latitude,
      )

    const arrivalLongitude =
      Number(
        flight.arrival_longitude,
      )

    const arrivalLatitude =
      Number(
        flight.arrival_latitude,
      )

    if (
      Number.isFinite(
        departureLongitude,
      ) &&
      Number.isFinite(
        departureLatitude,
      )
    ) {
      bounds.extend([
        departureLongitude,
        departureLatitude,
      ])
    }

    if (
      Number.isFinite(
        arrivalLongitude,
      ) &&
      Number.isFinite(
        arrivalLatitude,
      )
    ) {
      bounds.extend([
        arrivalLongitude,
        arrivalLatitude,
      ])
    }
  }

  if (
    bounds.isEmpty()
  ) {
    return
  }

  map.fitBounds(
    bounds,
    {
      padding: {
        top:
          80,
        right:
          80,
        bottom:
          80,
        left:
          sidebarCollapsed.value
            ? 80
            : 420,
      },
      maxZoom:
        7,
      duration:
        800,
    },
  )
}

function toggleSidebar(): void {
  sidebarCollapsed.value =
    !sidebarCollapsed.value
}

function changeTab(
  tab:
    SidebarTab,
): void {
  activeTab.value =
    tab

  if (
    tab !==
      'flights' &&
    aircraftReturnToStatistics.value
  ) {
    aircraftReturnToStatistics.value =
      false
  }

  if (
    tab !==
    'statistics'
  ) {
    airportStatisticsOpen.value =
      false

    statisticsReport.value =
      null

    statisticsSection.value =
      null
  }

  if (
    tab !==
    'flights'
  ) {
    filteredFlights.value =
      visibleFlights.value
  }
}

function changeScope(
  value:
    FlightScope,
): void {
  scope.value =
    value

  filteredFlights.value =
    filterFlightsByScope(
      allFlights.value,
      value,
    )
}

function receiveFilteredFlights(
  flights:
    Flight[],
): void {
  filteredFlights.value =
    flights
}

function receiveAircraftFilterChanged(
  key:
    string | null,
): void {
  aircraftFilterKey.value =
    key
}

function closeStatisticsPanels(): void {
  airportStatisticsOpen.value =
    false

  statisticsReport.value =
    null

  statisticsSection.value =
    null
}

function openAirportStatistics(): void {
  clearSelection()
  closeStatisticsPanels()

  routeReturnSection.value =
    null

  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )
  }

  airportStatisticsOpen.value =
    true
}

function closeAirportStatistics(): void {
  airportStatisticsOpen.value =
    false
}

function openStatisticsReport(
  report:
    | 'flights'
    | 'distance'
    | 'duration',
): void {
  clearSelection()
  closeStatisticsPanels()

  routeReturnSection.value =
    null

  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )
  }

  statisticsReport.value =
    report
}

function closeStatisticsReport(): void {
  statisticsReport.value =
    null
}

function openStatisticsSection(
  section:
    | 'airlines'
    | 'aircraft'
    | 'routes'
    | 'countries',
): void {
  clearSelection()
  closeStatisticsPanels()

  routeReturnSection.value =
    null

  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )
  }

  statisticsSection.value =
    section
}

function closeStatisticsSection(): void {
  statisticsSection.value =
    null
}

/*
|--------------------------------------------------------------------------
| Typ samolotu wybrany ze statystyk
|--------------------------------------------------------------------------
*/

function openAircraftFlightsFromStatistics(
  key:
    string,

  _name:
    string,
): void {
  clearSelection()
  closeStatisticsPanels()

  routeReturnSection.value =
    null

  aircraftFilterKey.value =
    key

  aircraftReturnToStatistics.value =
    true

  activeTab.value =
    'flights'

  const matchingFlights =
    visibleFlights.value.filter(
      (flight) => {
        if (
          key.startsWith(
            'id:',
          )
        ) {
          return (
            flight.aircraft_type_id !==
              null &&
            `id:${flight.aircraft_type_id}` ===
              key
          )
        }

        return (
          `name:${flight.aircraft_name ?? ''}` ===
          key
        )
      },
    )

  filteredFlights.value =
    matchingFlights

  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )

    updateFlightMapData(
      mapInstance,
      matchingFlights,
    )

    fitMapToFlights(
      mapInstance,
      matchingFlights,
    )
  }
}

function backToAircraftStatistics(): void {
  aircraftReturnToStatistics.value =
    false

  aircraftFilterKey.value =
    null

  filteredFlights.value =
    visibleFlights.value

  activeTab.value =
    'statistics'

  statisticsSection.value =
    'aircraft'

  clearSelection()

  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )

    updateFlightMapData(
      mapInstance,
      visibleFlights.value,
    )
  }
}

function buildAirportFromCode(
  code:
    string,
): SelectedAirport | null {
  const flights =
    mapFlights.value

  const matchingFlight =
    flights.find(
      (flight) =>
        flight.departure_iata ===
          code ||
        flight.arrival_iata ===
          code,
    )

  if (!matchingFlight) {
    return null
  }

  const isDeparture =
    matchingFlight.departure_iata ===
    code

  const airportId =
    isDeparture
      ? matchingFlight.departure_airport_id
      : matchingFlight.arrival_airport_id

  const airportName =
    isDeparture
      ? matchingFlight.departure_airport_name
      : matchingFlight.arrival_airport_name

  const airportCity =
    isDeparture
      ? matchingFlight.departure_city
      : matchingFlight.arrival_city

  const longitude =
    Number(
      isDeparture
        ? matchingFlight.departure_longitude
        : matchingFlight.arrival_longitude,
    )

  const latitude =
    Number(
      isDeparture
        ? matchingFlight.departure_latitude
        : matchingFlight.arrival_latitude,
    )

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
    of flights
  ) {
    if (
      flight.departure_airport_id ===
      airportId
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
      airportId
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

  return {
    code,
    name:
      airportName,
    city:
      airportCity,
    longitude,
    latitude,
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
  }
}

function openAirportByCode(
  code:
    string,
): void {
  const airport =
    buildAirportFromCode(
      code,
    )

  if (!airport) {
    return
  }

  closeStatisticsPanels()

  routeReturnSection.value =
    null

  selectedRoute.value =
    null

  selectedFlightId.value =
    null

  selectedFlight.value =
    null

  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )

    mapInstance.flyTo({
      center: [
        airport.longitude,
        airport.latitude,
      ],
      zoom:
        Math.max(
          mapInstance.getZoom(),
          4.5,
        ),
      duration:
        650,
    })
  }

  selectedAirport.value =
    airport
}

function openAirportFromStatistics(
  airport:
    SelectedAirport,
): void {
  closeStatisticsPanels()

  routeReturnSection.value =
    null

  selectedRoute.value =
    null

  selectedFlightId.value =
    null

  selectedFlight.value =
    null

  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )

    mapInstance.flyTo({
      center: [
        airport.longitude,
        airport.latitude,
      ],
      zoom:
        Math.max(
          mapInstance.getZoom(),
          5,
        ),
      duration:
        700,
    })
  }

  selectedAirport.value =
    airport
}

function clearSelection(): void {
  selectedAirport.value =
    null

  selectedRoute.value =
    null

  selectedFlightId.value =
    null

  selectedFlight.value =
    null

  flightError.value =
    null

  flightLoading.value =
    false
}

function selectDestination(
  destination:
    AirportDirectionStat,
): void {
  if (
    !mapInstance ||
    !selectedAirport.value
  ) {
    return
  }

  highlightRoute(
    mapInstance,
    selectedAirport.value.code,
    destination.code,
    selectedAirport.value.longitude,
    selectedAirport.value.latitude,
    destination.longitude,
    destination.latitude,
  )
}

function selectOrigin(
  origin:
    AirportDirectionStat,
): void {
  if (
    !mapInstance ||
    !selectedAirport.value
  ) {
    return
  }

  highlightRoute(
    mapInstance,
    origin.code,
    selectedAirport.value.code,
    origin.longitude,
    origin.latitude,
    selectedAirport.value.longitude,
    selectedAirport.value.latitude,
  )
}

function openDestinationRoute(
  destination:
    AirportDirectionStat,
): void {
  if (
    !selectedAirport.value
  ) {
    return
  }

  routeReturnSection.value =
    null

  const route =
    buildSelectedRoute(
      mapFlights.value,
      selectedAirport.value.code,
      selectedAirport.value.name,
      destination.code,
      destination.name,
    )

  if (!route) {
    return
  }

  if (mapInstance) {
    highlightRoute(
      mapInstance,
      route.departureCode,
      route.arrivalCode,
      route.departureLongitude,
      route.departureLatitude,
      route.arrivalLongitude,
      route.arrivalLatitude,
    )
  }

  selectRoute(
    route,
  )
}

function openOriginRoute(
  origin:
    AirportDirectionStat,
): void {
  if (
    !selectedAirport.value
  ) {
    return
  }

  routeReturnSection.value =
    null

  const route =
    buildSelectedRoute(
      mapFlights.value,
      origin.code,
      origin.name,
      selectedAirport.value.code,
      selectedAirport.value.name,
    )

  if (!route) {
    return
  }

  if (mapInstance) {
    highlightRoute(
      mapInstance,
      route.departureCode,
      route.arrivalCode,
      route.departureLongitude,
      route.departureLatitude,
      route.arrivalLongitude,
      route.arrivalLatitude,
    )
  }

  selectRoute(
    route,
  )
}

function openRouteFromStatistics(
  departureCode:
    string | null,

  arrivalCode:
    string | null,
): void {
  if (
    !departureCode ||
    !arrivalCode
  ) {
    return
  }

  const matchingFlight =
    visibleFlights.value.find(
      (flight) =>
        flight.departure_iata ===
          departureCode &&
        flight.arrival_iata ===
          arrivalCode,
    )

  if (!matchingFlight) {
    return
  }

  const route =
    buildSelectedRoute(
      visibleFlights.value,
      departureCode,
      matchingFlight.departure_airport_name,
      arrivalCode,
      matchingFlight.arrival_airport_name,
    )

  if (!route) {
    return
  }

  closeStatisticsPanels()

  routeReturnSection.value =
    'routes'

  if (mapInstance) {
    highlightRoute(
      mapInstance,
      route.departureCode,
      route.arrivalCode,
      route.departureLongitude,
      route.departureLatitude,
      route.arrivalLongitude,
      route.arrivalLatitude,
    )
  }

  selectRoute(
    route,
  )
}

function backToRoutesStatistics(): void {
  if (
    routeReturnSection.value !==
    'routes'
  ) {
    return
  }

  selectedAirport.value =
    null

  selectedRoute.value =
    null

  selectedFlightId.value =
    null

  selectedFlight.value =
    null

  flightError.value =
    null

  flightLoading.value =
    false

  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )
  }

  routeReturnSection.value =
    null

  statisticsSection.value =
    'routes'
}

function selectRoute(
  route:
    SelectedRoute,
): void {
  const matchingFlight =
    mapFlights.value.find(
      (flight) => {
        const departureMatches =
          route.departureCode
            ? (
                flight.departure_iata ===
                route.departureCode
              )
            : (
                flight.departure_airport_name ===
                route.departureName
              )

        const arrivalMatches =
          route.arrivalCode
            ? (
                flight.arrival_iata ===
                route.arrivalCode
              )
            : (
                flight.arrival_airport_name ===
                route.arrivalName
              )

        return (
          departureMatches &&
          arrivalMatches
        )
      },
    )

  closeStatisticsPanels()

  selectedAirport.value =
    null

  selectedFlightId.value =
    null

  selectedFlight.value =
    null

  selectedRoute.value = {
    ...route,
    departureCountryCode:
      matchingFlight
        ?.departure_country_code ??
      route.departureCountryCode ??
      null,
    arrivalCountryCode:
      matchingFlight
        ?.arrival_country_code ??
      route.arrivalCountryCode ??
      null,
  }
}

async function loadFlight(
  id:
    number,
): Promise<void> {
  selectedFlightId.value =
    id

  selectedFlight.value =
    null

  flightError.value =
    null

  flightLoading.value =
    true

  try {
    const response =
      await getFlight(
        id,
      )

    selectedFlight.value =
      response.flight
  } catch (err) {
    console.error(
      err,
    )

    flightError.value =
      'Nie udało się pobrać szczegółów lotu.'
  } finally {
    flightLoading.value =
      false
  }
}

async function selectRouteFlight(
  flight:
    RouteFlight,
): Promise<void> {
  await loadFlight(
    flight.id,
  )
}

async function selectFlightFromList(
  flight:
    Flight,
): Promise<void> {
  closeStatisticsPanels()

  routeReturnSection.value =
    null

  selectedAirport.value =
    null

  selectedRoute.value =
    null

  if (mapInstance) {
    highlightRoute(
      mapInstance,
      flight.departure_iata,
      flight.arrival_iata,
      Number(
        flight.departure_longitude,
      ),
      Number(
        flight.departure_latitude,
      ),
      Number(
        flight.arrival_longitude,
      ),
      Number(
        flight.arrival_latitude,
      ),
    )
  }

  await loadFlight(
    flight.id,
  )
}

function backFromFlight(): void {
  selectedFlightId.value =
    null

  selectedFlight.value =
    null

  flightError.value =
    null

  flightLoading.value =
    false

  if (
    !selectedRoute.value &&
    mapInstance
  ) {
    clearHighlightedRoute(
      mapInstance,
    )
  }
}

function closeAirport(): void {
  selectedAirport.value =
    null

  routeReturnSection.value =
    null

  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )
  }
}

function closeRoute(): void {
  clearSelection()

  routeReturnSection.value =
    null

  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )
  }
}

function closeFlight(): void {
  clearSelection()

  routeReturnSection.value =
    null

  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )
  }
}

watch(
  mapFlights,

  (flights) => {
    if (!mapInstance) {
      return
    }

    clearSelection()

    routeReturnSection.value =
      null

    updateFlightMapData(
      mapInstance,
      flights,
    )

    if (
      activeTab.value ===
      'flights'
    ) {
      fitMapToFlights(
        mapInstance,
        flights,
      )
    }
  },
)

onMounted(
  async () => {
    if (
      !mapContainer.value
    ) {
      return
    }

    const map =
      new MapLibreMap({
        container:
          mapContainer.value,
        style:
          `https://api.maptiler.com/maps/topo-v2/style.json?key=${mapTilerKey}`,
        center: [
          20,
          30,
        ],
        zoom:
          1.5,
      })

    mapInstance =
      map

    try {
      const response =
        await getUserFlights(
          USER_ID,
        )

      allFlights.value =
        response.flights

      filteredFlights.value =
        filterFlightsByScope(
          response.flights,
          scope.value,
        )

      map.on(
        'load',

        async () => {
          addFlightsToMap(
            map,
            mapFlights.value,

            (route) => {
              routeReturnSection.value =
                null

              selectRoute(
                route,
              )
            },
          )

          await addAirportsToMap(
            map,
            mapFlights.value,

            (airport) => {
              closeStatisticsPanels()

              routeReturnSection.value =
                null

              selectedRoute.value =
                null

              selectedFlightId.value =
                null

              selectedFlight.value =
                null

              clearHighlightedRoute(
                map,
              )

              selectedAirport.value =
                airport
            },
          )
        },
      )
    } catch (err) {
      console.error(
        err,
      )
    }
  },
)
</script>

<template>
  <main class="app-shell">
    <div
      ref="mapContainer"
      class="map"
    ></div>

    <AppSidebar
      :flights="visibleFlights"
      :active-tab="activeTab"
      :scope="scope"
      :collapsed="sidebarCollapsed"
      :active-flight-id="selectedFlightId"
      :initial-aircraft-filter-key="aircraftFilterKey"
      @toggle="toggleSidebar"
      @tab="changeTab"
      @scope="changeScope"
      @flight="selectFlightFromList"
      @filtered-flights="receiveFilteredFlights"
      @aircraft-filter-changed="receiveAircraftFilterChanged"
      @statistics-airports="openAirportStatistics"
      @statistics-report="openStatisticsReport"
      @statistics-section="openStatisticsSection"
    />

    <button
      v-if="
        aircraftReturnToStatistics &&
        activeTab === 'flights' &&
        selectedFlightId === null
      "
      type="button"
      class="aircraft-return-button"
      title="Wróć do statystyk typów samolotów"
      @click="backToAircraftStatistics"
    >
      <svg
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path d="M15 5l-7 7 7 7" />
      </svg>

      <span>
        Wróć do typów samolotów
      </span>
    </button>

    <AirportDetailsPanel
      v-if="selectedAirport"
      :airport="selectedAirport"
      :flights="mapFlights"
      @close="closeAirport"
      @airport="openAirportByCode"
      @destination="selectDestination"
      @origin="selectOrigin"
      @destination-details="openDestinationRoute"
      @origin-details="openOriginRoute"
    />

    <div
      v-if="
        selectedRoute &&
        selectedFlightId ===
          null
      "
      class="route-panel-wrapper"
    >
      <button
        v-if="
          routeReturnSection ===
          'routes'
        "
        type="button"
        class="route-back-button"
        title="Wróć do statystyk tras"
        @click="backToRoutesStatistics"
      >
        <svg
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path d="M15 5l-7 7 7 7" />
        </svg>

        <span>
          Wróć do tras
        </span>
      </button>

      <RouteDetailsPanel
        :route="selectedRoute"
        @close="closeRoute"
        @flight="selectRouteFlight"
      />
    </div>

    <FlightDetailsPanel
      v-if="
        selectedFlightId !==
        null
      "
      :flight="selectedFlight"
      :loading="flightLoading"
      :error="flightError"
      @back="backFromFlight"
      @close="closeFlight"
    />

    <AirportStatisticsPanel
      v-if="airportStatisticsOpen"
      :flights="visibleFlights"
      @airport="openAirportFromStatistics"
      @close="closeAirportStatistics"
    />

    <StatisticsMetricPanel
      v-if="statisticsReport"
      :flights="visibleFlights"
      :report-type="statisticsReport"
      @close="closeStatisticsReport"
    />

    <StatisticsCategoryPanel
      v-if="statisticsSection"
      :flights="visibleFlights"
      :section="statisticsSection"
      @aircraft="openAircraftFlightsFromStatistics"
      @route="openRouteFromStatistics"
      @close="closeStatisticsSection"
    />
  </main>
</template>

<style>
html,
body,
#app {
  width: 100%;
  height: 100%;
  margin: 0;
  padding: 0;
}

body {
  overflow: hidden;
  font-family:
    Inter,
    system-ui,
    -apple-system,
    BlinkMacSystemFont,
    "Segoe UI",
    sans-serif;
  background: #f4f4f4;
}

* {
  box-sizing: border-box;
}

button,
input,
select,
textarea {
  font: inherit;
}

.app-shell {
  position: relative;
  width: 100%;
  height: 100%;
}

.map {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

.route-panel-wrapper {
  position: static;
}

.aircraft-return-button {
  position: absolute;
  top: 18px;
  right: 18px;
  z-index: 65;
  display: inline-flex;
  height: 34px;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 0 11px;
  border: 1px solid #d9dde3;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.96);
  color: #0b2d5c;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  font-size: 11px;
  font-weight: 650;
  line-height: 1;
  white-space: nowrap;
  backdrop-filter: blur(8px);
}

.aircraft-return-button:hover {
  border-color: #cbd2db;
  background: #fff;
}

.aircraft-return-button svg {
  width: 12px;
  height: 12px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

/*
|--------------------------------------------------------------------------
| Powrót z panelu trasy do raportu Tras
|--------------------------------------------------------------------------
|
| Wysokość 36 px oraz top: 24 px są dopasowane do przycisku X
| w panelu trasy. Prawa krawędź zostawia wyraźny odstęp pomiędzy
| przyciskami.
|
*/

.route-back-button {
  position: absolute;
  top: 30px;
  right: 76px;
  z-index: 70;
  display: inline-flex;
  height: 36px;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 0 12px;
  border: 1px solid #d9dde3;
  border-radius: 9px;
  background: rgba(255, 255, 255, 0.96);
  color: #0b2d5c;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  font-size: 12px;
  font-weight: 650;
  line-height: 1;
  white-space: nowrap;
  backdrop-filter: blur(8px);
}

.route-back-button:hover {
  border-color: #cbd2db;
  background: #fff;
}

.route-back-button svg {
  width: 13px;
  height: 13px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.maplibregl-map {
  font-family:
    Inter,
    system-ui,
    -apple-system,
    BlinkMacSystemFont,
    "Segoe UI",
    sans-serif;
}

::-webkit-scrollbar {
  width: 7px;
  height: 7px;
}

::-webkit-scrollbar-track {
  background: transparent;
}

::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.18);
  border-radius: 20px;
}

::-webkit-scrollbar-thumb:hover {
  background: rgba(0, 0, 0, 0.28);
}
</style>
