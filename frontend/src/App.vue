<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
  watch,
} from 'vue'

import {
  Map,
  setWorkerUrl,
} from 'maplibre-gl'

import 'maplibre-gl/dist/maplibre-gl.css'

import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'


import AppSidebar from './components/AppSidebar.vue'
import AirportDetailsPanel from './components/AirportDetailsPanel.vue'
import RouteDetailsPanel from './components/RouteDetailsPanel.vue'
import FlightDetailsPanel from './components/FlightDetailsPanel.vue'


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
  Map | null =
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


/*
|--------------------------------------------------------------------------
| Loty po globalnym scope
|--------------------------------------------------------------------------
*/

const visibleFlights =
  computed(
    () =>
      filterFlightsByScope(
        allFlights.value,
        scope.value,
      ),
  )


/*
|--------------------------------------------------------------------------
| Aktualny wynik zakładki Loty
|--------------------------------------------------------------------------
*/

const filteredFlights =
  ref<Flight[]>(
    [],
  )


/*
|--------------------------------------------------------------------------
| Dane aktualnie pokazywane na mapie
|--------------------------------------------------------------------------
*/

const mapFlights =
  computed(
    () => {
      if (
        activeTab.value ===
        'flights'
      ) {
        return (
          filteredFlights.value
        )
      }


      return visibleFlights.value
    },
  )


function toggleSidebar(): void {
  sidebarCollapsed.value =
    !sidebarCollapsed.value
}


function changeTab(
  tab: SidebarTab,
): void {
  activeTab.value =
    tab


  /*
   * Po wyjściu z zakładki Loty
   * mapa wraca do globalnego scope.
   */

  if (
    tab !==
    'flights'
  ) {
    filteredFlights.value =
      visibleFlights.value
  }
}


function changeScope(
  value: FlightScope,
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
  flights: Flight[],
): void {
  filteredFlights.value =
    flights
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


function selectRoute(
  route:
    SelectedRoute,
): void {
  const matchingFlight =
    mapFlights.value.find(
      (flight) => {
        const departureMatches =
          route.departureCode
            ? flight.departure_iata ===
              route.departureCode
            : flight.departure_airport_name ===
              route.departureName

        const arrivalMatches =
          route.arrivalCode
            ? flight.arrival_iata ===
              route.arrivalCode
            : flight.arrival_airport_name ===
              route.arrivalName

        return (
          departureMatches &&
          arrivalMatches
        )
      },
    )


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
  id: number,
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


  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )
  }
}


function closeRoute(): void {
  clearSelection()


  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )
  }
}


function closeFlight(): void {
  clearSelection()


  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )
  }
}


/*
|--------------------------------------------------------------------------
| Każda zmiana filtrów przebudowuje mapę
|--------------------------------------------------------------------------
*/

watch(
  mapFlights,

  (flights) => {
    if (!mapInstance) {
      return
    }


    clearSelection()


    updateFlightMapData(
      mapInstance,
      flights,
    )
  },
)


onMounted(
  async () => {
    if (!mapContainer.value) {
      return
    }


    const map =
      new Map({
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
              selectRoute(
                route,
              )
            },
          )


          await addAirportsToMap(
            map,

            mapFlights.value,

            (airport) => {
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

      @toggle="toggleSidebar"

      @tab="changeTab"

      @scope="changeScope"

      @flight="selectFlightFromList"

      @filtered-flights="
        receiveFilteredFlights
      "
    />


    <AirportDetailsPanel
      v-if="selectedAirport"

      :airport="selectedAirport"

      @close="closeAirport"

      @destination="
        selectDestination
      "

      @origin="
        selectOrigin
      "

      @destination-details="
        openDestinationRoute
      "

      @origin-details="
        openOriginRoute
      "
    />


    <RouteDetailsPanel
      v-if="
        selectedRoute &&
        selectedFlightId ===
          null
      "

      :route="selectedRoute"

      @close="closeRoute"

      @flight="
        selectRouteFlight
      "
    />


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


button {
  -webkit-tap-highlight-color:
    transparent;
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
}


::-webkit-scrollbar-track {
  background: transparent;
}


::-webkit-scrollbar-thumb {
  background:
    rgba(
      0,
      0,
      0,
      0.18
    );

  border-radius: 20px;
}


::-webkit-scrollbar-thumb:hover {
  background:
    rgba(
      0,
      0,
      0,
      0.28
    );
}
</style>