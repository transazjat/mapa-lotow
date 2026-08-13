<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
  watch,
} from 'vue'

import {
  LngLatBounds,
  Map,
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


/*
|--------------------------------------------------------------------------
| Konfiguracja
|--------------------------------------------------------------------------
*/

const USER_ID =
  75


const mapTilerKey =
  import.meta.env.VITE_MAPTILER_KEY


if (!mapTilerKey) {
  throw new Error(
    'Brak VITE_MAPTILER_KEY',
  )
}


/*
|--------------------------------------------------------------------------
| Mapa
|--------------------------------------------------------------------------
*/

const mapContainer =
  ref<HTMLDivElement | null>(
    null,
  )


let mapInstance:
  Map | null =
  null


/*
|--------------------------------------------------------------------------
| Dane lotów
|--------------------------------------------------------------------------
*/

const allFlights =
  ref<Flight[]>(
    [],
  )


/*
|--------------------------------------------------------------------------
| Globalny zakres lotów
|--------------------------------------------------------------------------
*/

const scope =
  ref<FlightScope>(
    'completed',
  )


/*
|--------------------------------------------------------------------------
| Nawigacja lewego panelu
|--------------------------------------------------------------------------
*/

const activeTab =
  ref<SidebarTab>(
    'map',
  )


const sidebarCollapsed =
  ref(false)


/*
|--------------------------------------------------------------------------
| Zaznaczenia na mapie
|--------------------------------------------------------------------------
*/

const selectedAirport =
  ref<SelectedAirport | null>(
    null,
  )


const selectedRoute =
  ref<SelectedRoute | null>(
    null,
  )


/*
|--------------------------------------------------------------------------
| Konkretny lot
|--------------------------------------------------------------------------
*/

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
| Statystyki - szerokie panele
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Loty po globalnym scope
|--------------------------------------------------------------------------
|
| completed  -> tylko odbyte
| all        -> odbyte + zaplanowane
| planned    -> tylko zaplanowane
|
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
| Aktualny wynik wyszukiwarki w zakładce Loty
|--------------------------------------------------------------------------
*/

const filteredFlights =
  ref<Flight[]>(
    [],
  )


/*
|--------------------------------------------------------------------------
| Loty aktualnie prezentowane na mapie
|--------------------------------------------------------------------------
|
| W zakładce Loty mapa respektuje wszystkie filtry.
| W pozostałych zakładkach respektuje globalny scope.
|
*/

const mapFlights =
  computed(
    () => {
      if (
        activeTab.value ===
        'flights'
      ) {
        return filteredFlights.value
      }

      return visibleFlights.value
    },
  )


/*
|--------------------------------------------------------------------------
| Dopasowanie widoku mapy
|--------------------------------------------------------------------------
*/

function fitMapToFlights(
  map: Map,
  flights: Flight[],
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


/*
|--------------------------------------------------------------------------
| Sidebar
|--------------------------------------------------------------------------
*/

function toggleSidebar(): void {
  sidebarCollapsed.value =
    !sidebarCollapsed.value
}


/*
|--------------------------------------------------------------------------
| Zmiana głównej zakładki
|--------------------------------------------------------------------------
*/

function changeTab(
  tab: SidebarTab,
): void {
  activeTab.value =
    tab


  /*
   * Po wyjściu ze Statystyk
   * zamykamy szerokie raporty.
   */

  if (
    tab !==
    'statistics'
  ) {
    airportStatisticsOpen.value =
      false

    statisticsReport.value =
      null
  }


  /*
   * Po wyjściu z Lotów
   * mapa wraca do pełnego
   * zbioru danego scope.
   */

  if (
    tab !==
    'flights'
  ) {
    filteredFlights.value =
      visibleFlights.value
  }
}


/*
|--------------------------------------------------------------------------
| Zmiana globalnego scope
|--------------------------------------------------------------------------
*/

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


  /*
   * Szerokie raporty statystyczne
   * mogą pozostać otwarte.
   * Ich dane zmienią się reaktywnie.
   */
}


/*
|--------------------------------------------------------------------------
| Wynik filtrów zakładki Loty
|--------------------------------------------------------------------------
*/

function receiveFilteredFlights(
  flights: Flight[],
): void {
  filteredFlights.value =
    flights
}


/*
|--------------------------------------------------------------------------
| Statystyki - lotniska
|--------------------------------------------------------------------------
*/

function openAirportStatistics(): void {
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


  statisticsReport.value =
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


/*
|--------------------------------------------------------------------------
| Statystyki - raporty liczbowe
|--------------------------------------------------------------------------
*/

function openStatisticsReport(
  report:
    | 'flights'
    | 'distance'
    | 'duration',
): void {
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


  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )
  }


  airportStatisticsOpen.value =
    false

  statisticsReport.value =
    report
}


function closeStatisticsReport(): void {
  statisticsReport.value =
    null
}


/*
|--------------------------------------------------------------------------
| Przejście z tabeli statystycznej do panelu lotniska
|--------------------------------------------------------------------------
*/

function openAirportFromStatistics(
  airport:
    SelectedAirport,
): void {
  airportStatisticsOpen.value =
    false

  statisticsReport.value =
    null


  selectedRoute.value =
    null

  selectedFlightId.value =
    null

  selectedFlight.value =
    null

  flightError.value =
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


/*
|--------------------------------------------------------------------------
| Wspólne czyszczenie zaznaczeń
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Panel lotniska - kierunek
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Panel lotniska - port wylotu
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Panel lotniska -> szczegóły trasy
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Panel lotniska -> szczegóły trasy w przeciwnym kierunku
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Wybór trasy
|--------------------------------------------------------------------------
*/

function selectRoute(
  route:
    SelectedRoute,
): void {
  /*
   * flightMap.ts nie musi znać flag.
   * Uzupełniamy je tutaj na podstawie
   * pierwszego zgodnego lotu.
   */

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


  airportStatisticsOpen.value =
    false

  statisticsReport.value =
    null


  selectedAirport.value =
    null

  selectedFlightId.value =
    null

  selectedFlight.value =
    null

  flightError.value =
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


/*
|--------------------------------------------------------------------------
| Pobranie pełnego rekordu lotu
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Lot wybrany z panelu trasy
|--------------------------------------------------------------------------
*/

async function selectRouteFlight(
  flight:
    RouteFlight,
): Promise<void> {
  await loadFlight(
    flight.id,
  )
}


/*
|--------------------------------------------------------------------------
| Lot wybrany z lewej listy
|--------------------------------------------------------------------------
*/

async function selectFlightFromList(
  flight:
    Flight,
): Promise<void> {
  airportStatisticsOpen.value =
    false

  statisticsReport.value =
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


/*
|--------------------------------------------------------------------------
| Powrót ze szczegółów lotu
|--------------------------------------------------------------------------
*/

function backFromFlight(): void {
  selectedFlightId.value =
    null

  selectedFlight.value =
    null

  flightError.value =
    null

  flightLoading.value =
    false


  /*
   * Jeśli lot był otwarty z panelu trasy,
   * selectedRoute nadal istnieje,
   * więc wracamy do historii trasy.
   *
   * Jeśli z listy Loty, zdejmujemy
   * granatowe zaznaczenie.
   */

  if (
    !selectedRoute.value &&
    mapInstance
  ) {
    clearHighlightedRoute(
      mapInstance,
    )
  }
}


/*
|--------------------------------------------------------------------------
| Zamknięcia paneli
|--------------------------------------------------------------------------
*/

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
| Aktualizacja mapy przy scope / filtrach
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


    /*
     * W zakładce Loty mapa
     * automatycznie dopasowuje się
     * do wyniku wyszukiwania.
     */

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


/*
|--------------------------------------------------------------------------
| Start aplikacji
|--------------------------------------------------------------------------
*/

onMounted(
  async () => {
    if (
      !mapContainer.value
    ) {
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
          /*
           * Linie lotów
           */

          addFlightsToMap(
            map,

            mapFlights.value,

            (route) => {
              selectRoute(
                route,
              )
            },
          )


          /*
           * Lotniska
           */

          await addAirportsToMap(
            map,

            mapFlights.value,

            (airport) => {
              airportStatisticsOpen.value =
                false

              statisticsReport.value =
                null


              selectedRoute.value =
                null

              selectedFlightId.value =
                null

              selectedFlight.value =
                null

              flightError.value =
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

    <!-- Mapa -->

    <div
      ref="mapContainer"
      class="map"
    ></div>


    <!-- Lewy panel -->

    <AppSidebar
      :flights="
        visibleFlights
      "

      :active-tab="
        activeTab
      "

      :scope="
        scope
      "

      :collapsed="
        sidebarCollapsed
      "

      :active-flight-id="
        selectedFlightId
      "

      @toggle="
        toggleSidebar
      "

      @tab="
        changeTab
      "

      @scope="
        changeScope
      "

      @flight="
        selectFlightFromList
      "

      @filtered-flights="
        receiveFilteredFlights
      "

      @statistics-airports="
        openAirportStatistics
      "

      @statistics-report="
        openStatisticsReport
      "
    />


    <!-- Standardowy panel lotniska -->

    <AirportDetailsPanel
      v-if="
        selectedAirport
      "

      :airport="
        selectedAirport
      "

      @close="
        closeAirport
      "

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


    <!-- Panel trasy -->

    <RouteDetailsPanel
      v-if="
        selectedRoute &&
        selectedFlightId ===
          null
      "

      :route="
        selectedRoute
      "

      @close="
        closeRoute
      "

      @flight="
        selectRouteFlight
      "
    />


    <!-- Panel pojedynczego lotu -->

    <FlightDetailsPanel
      v-if="
        selectedFlightId !==
        null
      "

      :flight="
        selectedFlight
      "

      :loading="
        flightLoading
      "

      :error="
        flightError
      "

      @back="
        backFromFlight
      "

      @close="
        closeFlight
      "
    />


    <!-- Szeroka tabela statystyk lotnisk -->

    <AirportStatisticsPanel
      v-if="
        airportStatisticsOpen
      "

      :flights="
        visibleFlights
      "

      @airport="
        openAirportFromStatistics
      "

      @close="
        closeAirportStatistics
      "
    />


    <!-- Szerokie raporty: loty / dystans / czas -->

    <StatisticsMetricPanel
      v-if="
        statisticsReport
      "

      :flights="
        visibleFlights
      "

      :report-type="
        statisticsReport
      "

      @close="
        closeStatisticsReport
      "
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

  background:
    #f4f4f4;
}


* {
  box-sizing:
    border-box;
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
  height: 7px;
}


::-webkit-scrollbar-track {
  background:
    transparent;
}


::-webkit-scrollbar-thumb {
  background:
    rgba(
      0,
      0,
      0,
      0.18
    );

  border-radius:
    20px;
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