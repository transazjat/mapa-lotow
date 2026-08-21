<script setup lang="ts">
import {
  computed,
  nextTick,
  onBeforeUnmount,
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
import RecordsStatisticsPanel from './components/RecordsStatisticsPanel.vue'
import AddFlightPanel from './components/AddFlightPanel.vue'
import TripAdCard from './components/TripAdCard.vue'
import AccountPanel from './components/AccountPanel.vue'
import AdminPanel from './components/AdminPanel.vue'

import {
  deleteFlight,
  getAuthState,
  getFlight,
  getPublicProfile,
  getSharedMap,
  getUserFlights,
  logoutAccount,
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
  FlightFormMode,
  FlightScope,
  RouteFlight,
  SelectedAirport,
  SelectedRoute,
  SidebarTab,
} from './types/flight'

import type {
  AccountUser,
  PublicMapProfile,
} from './types/account'

setWorkerUrl(
  workerUrl,
)


interface DraggableReportElement
  extends HTMLElement {
  __reportDragCleanup?:
    () => void
}


const vDraggableReport = {
  mounted(
    element:
      DraggableReportElement,
  ): void {
    const handle =
      (
        element.querySelector(
          '.panel-header',
        ) ??
        element.querySelector(
          'header',
        )
      ) as HTMLElement | null

    if (!handle) {
      return
    }

    handle.style.cursor =
      'grab'

    handle.style.userSelect =
      'none'

    handle.title =
      handle.title ||
      'Przeciągnij panel'

    let activePointerId:
      number | null =
      null

    let pointerOffsetX =
      0

    let pointerOffsetY =
      0

    const minimumVisibleHeader =
      54

    function clamp(
      value:
        number,

      minimum:
        number,

      maximum:
        number,
    ): number {
      return Math.min(
        Math.max(
          value,
          minimum,
        ),
        maximum,
      )
    }


    function fitPanelHeight(
      top:
        number,
    ): void {
      const available =
        Math.max(
          180,
          window.innerHeight -
            top -
            8,
        )

      element.style.height =
        `${available}px`

      element.style.maxHeight =
        `${available}px`
    }


    function handlePointerDown(
      event:
        PointerEvent,
    ): void {
      if (
        event.button !==
        0
      ) {
        return
      }

      const target =
        event.target as
          HTMLElement | null

      if (
        target?.closest(
          'button, a, input, select, textarea, [role="button"]',
        )
      ) {
        return
      }

      const rect =
        element.getBoundingClientRect()

      activePointerId =
        event.pointerId

      pointerOffsetX =
        event.clientX -
        rect.left

      pointerOffsetY =
        event.clientY -
        rect.top

      element.style.position =
        'fixed'

      element.style.left =
        `${rect.left}px`

      element.style.top =
        `${rect.top}px`

      element.style.right =
        'auto'

      element.style.bottom =
        'auto'

      element.style.margin =
        '0'

      element.style.zIndex =
        '60'

      handle.style.cursor =
        'grabbing'

      handle.setPointerCapture(
        event.pointerId,
      )

      event.preventDefault()
    }


    function handlePointerMove(
      event:
        PointerEvent,
    ): void {
      if (
        activePointerId !==
        event.pointerId
      ) {
        return
      }

      const rect =
        element.getBoundingClientRect()

      const maxLeft =
        Math.max(
          8,
          window.innerWidth -
            rect.width -
            8,
        )

      const maxTop =
        Math.max(
          8,
          window.innerHeight -
            minimumVisibleHeader,
        )

      const left =
        clamp(
          event.clientX -
            pointerOffsetX,
          8,
          maxLeft,
        )

      const top =
        clamp(
          event.clientY -
            pointerOffsetY,
          8,
          maxTop,
        )

      element.style.left =
        `${left}px`

      element.style.top =
        `${top}px`

      fitPanelHeight(
        top,
      )

      event.preventDefault()
    }


    function finishDrag(
      event:
        PointerEvent,
    ): void {
      if (
        activePointerId !==
        event.pointerId
      ) {
        return
      }

      activePointerId =
        null

      handle.style.cursor =
        'grab'

      if (
        handle.hasPointerCapture(
          event.pointerId,
        )
      ) {
        handle.releasePointerCapture(
          event.pointerId,
        )
      }
    }


    function keepInsideViewport(): void {
      if (
        element.style.position !==
        'fixed'
      ) {
        return
      }

      const rect =
        element.getBoundingClientRect()

      const maxLeft =
        Math.max(
          8,
          window.innerWidth -
            rect.width -
            8,
        )

      const maxTop =
        Math.max(
          8,
          window.innerHeight -
            minimumVisibleHeader,
        )

      const left =
        clamp(
          rect.left,
          8,
          maxLeft,
        )

      const top =
        clamp(
          rect.top,
          8,
          maxTop,
        )

      element.style.left =
        `${left}px`

      element.style.top =
        `${top}px`

      fitPanelHeight(
        top,
      )
    }


    handle.addEventListener(
      'pointerdown',
      handlePointerDown,
    )

    handle.addEventListener(
      'pointermove',
      handlePointerMove,
    )

    handle.addEventListener(
      'pointerup',
      finishDrag,
    )

    handle.addEventListener(
      'pointercancel',
      finishDrag,
    )

    window.addEventListener(
      'resize',
      keepInsideViewport,
    )

    element.__reportDragCleanup =
      () => {
        handle.removeEventListener(
          'pointerdown',
          handlePointerDown,
        )

        handle.removeEventListener(
          'pointermove',
          handlePointerMove,
        )

        handle.removeEventListener(
          'pointerup',
          finishDrag,
        )

        handle.removeEventListener(
          'pointercancel',
          finishDrag,
        )

        window.removeEventListener(
          'resize',
          keepInsideViewport,
        )
      }
  },


  unmounted(
    element:
      DraggableReportElement,
  ): void {
    element
      .__reportDragCleanup?.()
  },
}

type AccountPanelMode =
  | 'login'
  | 'register'
  | 'forgot'
  | 'reset'
  | 'activate'
  | 'account'
  | 'profile'
  | 'privacy'
  | 'password'
  | 'email'
  | 'email-confirm'
  | 'export'


const currentUser =
  ref<AccountUser | null>(
    null,
  )

const accountPanelMode =
  ref<AccountPanelMode | null>(
    null,
  )

const accountToken =
  ref<string | null>(
    null,
  )

const publicProfile =
  ref<PublicMapProfile | null>(
    null,
  )

const publicAccessMode =
  ref<'public' | 'link' | null>(
    null,
  )

const authenticated =
  computed(
    () => currentUser.value !== null,
  )

const adminRoute =
  computed(
    () =>
      /^\/admin\/?$/i.test(
        window.location.pathname,
      ),
  )


const logoutConfirmOpen =
  ref(false)


const MAIN_PAGE_TITLE =
  'Mapa Lotów - Stwórz własną mapę lotów i podróży'

const accountPageTitles:
Record<AccountPanelMode, string> = {
  login:
    'Zaloguj się - Mapa Lotów',
  register:
    'Załóż konto - Mapa Lotów',
  forgot:
    'Odzyskaj hasło - Mapa Lotów',
  reset:
    'Ustaw nowe hasło - Mapa Lotów',
  activate:
    'Aktywuj konto - Mapa Lotów',
  account:
    'Ustawienia konta - Mapa Lotów',
  profile:
    'Profil - Mapa Lotów',
  privacy:
    'Prywatność mapy - Mapa Lotów',
  password:
    'Zmień hasło - Mapa Lotów',
  email:
    'Zmień adres e-mail - Mapa Lotów',
  'email-confirm':
    'Potwierdź nowy adres e-mail - Mapa Lotów',
  export:
    'Eksport danych - Mapa Lotów',
}

const pageTitle =
  computed(
    () => {
      if (adminRoute.value) {
        return 'Administracja - Mapa Lotów'
      }

      if (publicProfile.value) {
        return publicAccessMode.value ===
          'link'
          ? `Mapa udostępniona przez ${publicProfile.value.nick} - Mapa Lotów`
          : `Profil ${publicProfile.value.nick} - Mapa Lotów`
      }

      if (accountPanelMode.value) {
        return accountPageTitles[
          accountPanelMode.value
        ]
      }

      return MAIN_PAGE_TITLE
    },
  )

watch(
  pageTitle,
  (title) => {
    document.title =
      title
  },
  {
    immediate: true,
  },
)


const appShell =
  ref<HTMLElement | null>(
    null,
  )


const fullscreenMapMode =
  ref(
    false,
  )


const authChoiceOpen =
  ref(
    false,
  )

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


const rightPanelCollapsed =
  ref(false)


const rightPanelCollapseButton =
  ref<HTMLButtonElement | null>(
    null,
  )


const transAzjaAdPanel =
  ref<HTMLDivElement | null>(
    null,
  )


const rightPanelKey =
  computed(
    (): string | null => {
      if (
        accountPanelMode.value
      ) {
        return `account:${accountPanelMode.value}`
      }

      if (
        authChoiceOpen.value
      ) {
        return 'auth-choice'
      }

      if (
        addFlightOpen.value
      ) {
        return 'add-flight'
      }

      if (
        selectedAirport.value
      ) {
        return `airport:${selectedAirport.value.code ?? selectedAirport.value.name}`
      }

      if (
        selectedFlightId.value !==
        null
      ) {
        return `flight:${selectedFlightId.value}`
      }

      if (
        selectedRoute.value
      ) {
        return [
          'route',
          selectedRoute.value.departureCode ?? '',
          selectedRoute.value.arrivalCode ?? '',
        ].join(':')
      }

      if (
        airportStatisticsOpen.value
      ) {
        return 'statistics-airports'
      }

      if (
        statisticsReport.value
      ) {
        return `statistics-report:${statisticsReport.value}`
      }

      if (
        statisticsSection.value
      ) {
        return `statistics-section:${statisticsSection.value}`
      }

      if (
        statisticsRecordsOpen.value
      ) {
        return 'statistics-records'
      }

      return null
    },
  )

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

const statisticsRecordsOpen =
  ref(false)

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


const addFlightOpen =
  ref(
    false,
  )


const flightFormMode =
  ref<FlightFormMode>(
    'create',
  )


const flightFormInitialFlight =
  ref<FlightDetails | null>(
    null,
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

function fitRightPanelAboveTransAzjaAd(
  closeButton:
    HTMLButtonElement,
): void {
  const adPanel =
    transAzjaAdPanel.value

  if (!adPanel) {
    return
  }

  const adRect =
    adPanel.getBoundingClientRect()

  if (
    adRect.width <= 0 ||
    adRect.height <= 0
  ) {
    return
  }

  /*
  |--------------------------------------------------------------------------
  | Znajdujemy faktyczny panel po prawej stronie
  |--------------------------------------------------------------------------
  |
  | Większość prawych paneli jest elementem <aside>. Fallback przechodzi
  | po rodzicach i wybiera pierwszy większy element pozycjonowany absolutnie
  | lub fixed. Dzięki temu rozwiązanie działa dla różnych typów paneli bez
  | uzależniania się od nazw ich klas.
  |
  */

  let panel =
    closeButton.closest<HTMLElement>(
      'aside',
    )

  if (!panel) {
    let candidate:
      HTMLElement | null =
      closeButton.parentElement

    while (candidate) {
      const rect =
        candidate.getBoundingClientRect()

      const position =
        window
          .getComputedStyle(
            candidate,
          )
          .position

      if (
        (
          position ===
            'absolute' ||
          position ===
            'fixed'
        ) &&
        rect.width >= 300 &&
        rect.height >= 160
      ) {
        panel =
          candidate

        break
      }

      candidate =
        candidate.parentElement
    }
  }

  if (!panel) {
    return
  }

  const panelRect =
    panel.getBoundingClientRect()

  const gap =
    12

  const maxHeight =
    Math.max(
      180,
      Math.floor(
        adRect.top -
        gap -
        panelRect.top,
      ),
    )

  panel.style.maxHeight =
    `${maxHeight}px`

  /*
   * Jeżeli panel sam przewija zawartość, zostawiamy jego dotychczasowy
   * overflow. Dla paneli z wewnętrznym scrollem zmniejszenie max-height
   * wystarczy, aby reklama pozostała widoczna.
   */
}


function normalizeRightPanelControls(): void {
  void nextTick(
    () => {
      if (
        rightPanelCollapsed.value
      ) {
        return
      }

      const collapseButton =
        rightPanelCollapseButton.value

      if (!collapseButton) {
        return
      }

      const closeButtons =
        Array.from(
          document.querySelectorAll<HTMLButtonElement>(
            "button[title='Zamknij'], button[aria-label='Zamknij']",
          ),
        )
          .filter(
            (button) => {
              const rect =
                button.getBoundingClientRect()

              return (
                rect.width >
                  0 &&
                rect.height >
                  0 &&
                rect.right >
                  window.innerWidth /
                    2
              )
            },
          )
          .sort(
            (a, b) =>
              b.getBoundingClientRect()
                .right -
              a.getBoundingClientRect()
                .right,
          )

      const closeButton =
        closeButtons[0]

      if (!closeButton) {
        return
      }

      /*
      |--------------------------------------------------------------------------
      | Ujednolicamy X
      |--------------------------------------------------------------------------
      |
      | Różne komponenty miały własne rozmiary i pozycje X. Zamiast tworzyć
      | kolejne wyjątki CSS, ustawiamy jeden wspólny wygląd bezpośrednio na
      | faktycznie widocznym przycisku zamknięcia.
      |
      */

      closeButton.style.width =
        '40px'

      closeButton.style.minWidth =
        '40px'

      closeButton.style.height =
        '40px'

      closeButton.style.padding =
        '0'

      closeButton.style.border =
        '0'

      closeButton.style.borderRadius =
        '8px'

      closeButton.style.background =
        '#f1f2f3'

      closeButton.style.color =
        '#505862'

      closeButton.style.boxShadow =
        'none'

      closeButton.style.fontSize =
        '21px'

      closeButton.style.lineHeight =
        '1'


      fitRightPanelAboveTransAzjaAd(
        closeButton,
      )


      const rect =
        closeButton.getBoundingClientRect()

      const buttonGap =
        14

      collapseButton.style.top =
        `${rect.top}px`

      collapseButton.style.right =
        `${
          window.innerWidth -
          rect.left +
          buttonGap
        }px`

      collapseButton.style.width =
        `${rect.width}px`

      collapseButton.style.minWidth =
        `${rect.width}px`

      collapseButton.style.height =
        `${rect.height}px`

      collapseButton.style.border =
        '0'

      collapseButton.style.borderRadius =
        '8px'

      collapseButton.style.background =
        '#f1f2f3'

      collapseButton.style.color =
        '#505862'

      collapseButton.style.boxShadow =
        'none'
    },
  )
}


function toggleRightPanelCollapsed(): void {
  rightPanelCollapsed.value =
    !rightPanelCollapsed.value

  if (
    !rightPanelCollapsed.value
  ) {
    normalizeRightPanelControls()
  }
}


async function resizeMapAfterLayoutChange(): Promise<void> {
  await nextTick()

  requestAnimationFrame(
    () => {
      mapInstance?.resize()
    },
  )
}


function handleFullscreenChange(): void {
  fullscreenMapMode.value =
    document.fullscreenElement ===
    appShell.value

  void resizeMapAfterLayoutChange()
}


async function toggleMapFullscreen(): Promise<void> {
  try {
    if (
      document.fullscreenElement
    ) {
      await document.exitFullscreen()
      return
    }

    if (
      !appShell.value
    ) {
      return
    }

    await appShell.value.requestFullscreen()

    fullscreenMapMode.value =
      true

    await resizeMapAfterLayoutChange()
  } catch (
    error
  ) {
    console.error(
      'Nie udało się włączyć trybu pełnoekranowego.',
      error,
    )
  }
}


function toggleSidebar(): void {
  sidebarCollapsed.value =
    !sidebarCollapsed.value
}

function changeTab(
  tab:
    SidebarTab,
): void {
  if (
    addFlightOpen.value
  ) {
    addFlightOpen.value =
      false

    flightFormMode.value =
      'create'

    flightFormInitialFlight.value =
      null
  }

  if (
    tab !== 'account'
  ) {
    accountPanelMode.value =
      null
  }

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

    statisticsRecordsOpen.value =
      false
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

  statisticsRecordsOpen.value =
    false
}

function openAirportStatistics(): void {
  addFlightOpen.value =
    false

  flightFormMode.value =
    'create'

  flightFormInitialFlight.value =
    null


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
  addFlightOpen.value =
    false

  flightFormMode.value =
    'create'

  flightFormInitialFlight.value =
    null


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
  addFlightOpen.value =
    false

  flightFormMode.value =
    'create'

  flightFormInitialFlight.value =
    null


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

function openStatisticsRecords(): void {
  addFlightOpen.value =
    false

  flightFormMode.value =
    'create'

  flightFormInitialFlight.value =
    null

  clearSelection()
  closeStatisticsPanels()

  routeReturnSection.value =
    null

  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )
  }

  statisticsRecordsOpen.value =
    true
}

function closeStatisticsRecords(): void {
  statisticsRecordsOpen.value =
    false
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

function closeOtherRightPanelsForAccount(): void {
  authChoiceOpen.value =
    false

  addFlightOpen.value =
    false

  closeStatisticsPanels()

  selectedAirport.value =
    null

  selectedRoute.value =
    null

  selectedFlightId.value =
    null

  selectedFlight.value =
    null

  rightPanelCollapsed.value =
    false
}


function openAccountPanel(
  mode:
    | 'login'
    | 'register'
    | 'account'
    | 'export',
): void {
  closeOtherRightPanelsForAccount()

  activeTab.value =
    'account'

  accountPanelMode.value =
    mode

  accountToken.value =
    null
}


function setAccountPanelMode(
  mode: AccountPanelMode,
): void {
  accountPanelMode.value =
    mode
}


function closeAccountPanel(): void {
  accountPanelMode.value =
    null
}


async function refreshAuthenticatedFlights(): Promise<void> {
  if (!currentUser.value) {
    allFlights.value = []
    filteredFlights.value = []

    if (mapInstance) {
      updateFlightMapData(
        mapInstance,
        [],
      )
    }

    return
  }

  const response =
    await getUserFlights()

  allFlights.value =
    response.flights

  filteredFlights.value =
    filterFlightsByScope(
      response.flights,
      scope.value,
    )

  if (mapInstance) {
    updateFlightMapData(
      mapInstance,
      mapFlights.value,
    )
  }
}


async function handleAuthenticatedUser(
  user: AccountUser,
): Promise<void> {
  const keepActivationPanel =
    accountPanelMode.value ===
      'activate'

  currentUser.value =
    user

  publicProfile.value =
    null

  publicAccessMode.value =
    null

  activeTab.value =
    'map'

  if (!keepActivationPanel) {
    accountPanelMode.value =
      null
  }

  await refreshAuthenticatedFlights()
}


function requestToolboxLogout(): void {
  logoutConfirmOpen.value =
    true
}


function cancelToolboxLogout(): void {
  logoutConfirmOpen.value =
    false
}


async function confirmToolboxLogout(): Promise<void> {
  try {
    await logoutAccount()
  } catch (error) {
    console.error(
      'Nie udało się wylogować.',
      error,
    )
    return
  }

  logoutConfirmOpen.value =
    false

  currentUser.value =
    null

  accountPanelMode.value =
    null

  authChoiceOpen.value =
    false

  activeTab.value =
    'map'

  allFlights.value = []
  filteredFlights.value = []

  clearSelection()
  closeStatisticsPanels()

  if (mapInstance) {
    updateFlightMapData(
      mapInstance,
      [],
    )
  }
}


function handleAccountUserUpdated(
  user: AccountUser,
): void {
  currentUser.value =
    user
}


async function handleLoggedOut(): Promise<void> {
  currentUser.value =
    null

  allFlights.value = []
  filteredFlights.value = []

  clearSelection()

  if (mapInstance) {
    updateFlightMapData(
      mapInstance,
      [],
    )
  }
}


function openAuthChoice(): void {
  accountPanelMode.value =
    null

  closeStatisticsPanels()

  addFlightOpen.value =
    false

  selectedAirport.value =
    null

  selectedRoute.value =
    null

  selectedFlightId.value =
    null

  selectedFlight.value =
    null

  authChoiceOpen.value =
    true

  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )
  }
}


function closeAuthChoice(): void {
  authChoiceOpen.value =
    false
}


function openAddFlight(): void {
  if (!currentUser.value) {
    openAuthChoice()
    return
  }

  accountPanelMode.value =
    null

  authChoiceOpen.value =
    false

  closeStatisticsPanels()

  aircraftReturnToStatistics.value =
    false

  routeReturnSection.value =
    null

  clearSelection()

  if (mapInstance) {
    clearHighlightedRoute(
      mapInstance,
    )
  }

  flightFormMode.value =
    'create'

  flightFormInitialFlight.value =
    null

  addFlightOpen.value =
    true
}


function openEditFlight(): void {
  if (
    !selectedFlight.value
  ) {
    return
  }

  flightFormMode.value =
    'edit'

  flightFormInitialFlight.value =
    selectedFlight.value

  selectedFlightId.value =
    null

  selectedFlight.value =
    null

  addFlightOpen.value =
    true
}


function openDuplicateFlight(): void {
  if (
    !selectedFlight.value
  ) {
    return
  }

  flightFormMode.value =
    'duplicate'

  flightFormInitialFlight.value =
    selectedFlight.value

  selectedFlightId.value =
    null

  selectedFlight.value =
    null

  addFlightOpen.value =
    true
}


async function closeAddFlight(): Promise<void> {
  const previousFlightId =
    flightFormInitialFlight.value
      ?.id ??
    null

  const shouldReturn =
    flightFormMode.value !==
      'create' &&
    previousFlightId !==
      null

  addFlightOpen.value =
    false

  flightFormInitialFlight.value =
    null

  flightFormMode.value =
    'create'

  if (shouldReturn) {
    await loadFlight(
      previousFlightId,
    )
  }
}


async function refreshFlightsAfterSave(
  flightId:
    number,
): Promise<void> {
  const response =
    await getUserFlights()

  allFlights.value =
    response.flights

  const newFlight =
    response.flights.find(
      (flight) =>
        flight.id ===
        flightId,
    )

  if (!newFlight) {
    addFlightOpen.value =
      false

    return
  }

  const today =
    new Date()
      .toISOString()
      .slice(
        0,
        10,
      )

  const plannedFlight =
    newFlight.departure_date !==
      null &&
    newFlight.departure_date >
      today

  if (
    plannedFlight &&
    scope.value ===
      'completed'
  ) {
    scope.value =
      'all'
  }

  filteredFlights.value =
    filterFlightsByScope(
      response.flights,
      scope.value,
    )

  addFlightOpen.value =
    false

  closeStatisticsPanels()

  selectedAirport.value =
    null

  selectedRoute.value =
    null

  selectedFlightId.value =
    null

  selectedFlight.value =
    null

  await nextTick()

  if (mapInstance) {
    const currentFlights =
      filterFlightsByScope(
        response.flights,
        scope.value,
      )

    updateFlightMapData(
      mapInstance,
      currentFlights,
    )

    highlightRoute(
      mapInstance,
      newFlight.departure_iata,
      newFlight.arrival_iata,
      Number(
        newFlight.departure_longitude,
      ),
      Number(
        newFlight.departure_latitude,
      ),
      Number(
        newFlight.arrival_longitude,
      ),
      Number(
        newFlight.arrival_latitude,
      ),
    )

    fitMapToFlights(
      mapInstance,
      [newFlight],
    )
  }

  await loadFlight(
    flightId,
  )
}


async function deleteSelectedFlight(): Promise<void> {
  const flightId =
    selectedFlight.value
      ?.id ??
    selectedFlightId.value

  if (!flightId) {
    return
  }

  try {
    await deleteFlight(
      flightId,
    )

    const response =
      await getUserFlights()

    allFlights.value =
      response.flights

    filteredFlights.value =
      filterFlightsByScope(
        response.flights,
        scope.value,
      )

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

      updateFlightMapData(
        mapInstance,
        mapFlights.value,
      )
    }
  } catch (
    error
  ) {
    flightError.value =
      error instanceof Error
        ? error.message
        : 'Nie udało się usunąć lotu.'
  }
}


async function openExistingFromAddFlight(
  flightId:
    number,
): Promise<void> {
  addFlightOpen.value =
    false

  closeStatisticsPanels()

  selectedAirport.value =
    null

  selectedRoute.value =
    null

  await loadFlight(
    flightId,
  )
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

function flightsForAirport(
  airport:
    SelectedAirport,

  flights:
    Flight[],
): Flight[] {
  return flights.filter(
    (flight) =>
      flight.departure_iata ===
        airport.code ||
      flight.arrival_iata ===
        airport.code,
  )
}


function refreshMapForCurrentAirport(): void {
  if (!mapInstance) {
    return
  }

  if (
    selectedAirport.value
  ) {
    updateFlightMapData(
      mapInstance,
      flightsForAirport(
        selectedAirport.value,
        mapFlights.value,
      ),
    )

    return
  }

  updateFlightMapData(
    mapInstance,
    mapFlights.value,
  )
}


async function openAirportByCode(
  code:
    string,
): Promise<void> {
  /*
  |--------------------------------------------------------------------------
  | Profil lotniska otwierany z panelu lotu / trasy
  |--------------------------------------------------------------------------
  |
  | Kod lotniska w szczegółach lotu albo trasy traktujemy jako przejście
  | do pełnego profilu portu. Dlatego niezależnie od bieżącego zakresu
  | (Odbyte / Zaplanowane) przełączamy aplikację na "Wszystkie".
  |
  | Ważne: zmiana scope uruchamia watcher mapFlights, który czyści bieżące
  | zaznaczenie. Czekamy więc na nextTick i dopiero potem budujemy oraz
  | otwieramy panel lotniska.
  |
  */

  if (
    scope.value !==
    'all'
  ) {
    changeScope(
      'all',
    )

    await nextTick()
  }

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

async function openDestinationRoute(
  destination:
    AirportDirectionStat,
): Promise<void> {
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

  /*
  |--------------------------------------------------------------------------
  | Najpierw otwieramy panel trasy, potem podświetlamy linię
  |--------------------------------------------------------------------------
  |
  | selectRoute() zeruje selectedAirport. Watcher selectedAirport przywraca
  | wtedy pełny zestaw tras przez updateFlightMapData(), a ta funkcja czyści
  | flight-highlight. Wcześniej highlightRoute() było wywoływane przed
  | selectRoute(), więc zaznaczenie znikało natychmiast po otwarciu panelu.
  |
  | Czekamy na wykonanie watchera i dopiero potem nakładamy zaznaczenie.
  |
  */

  selectRoute(
    route,
  )

  await nextTick()

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
}

async function openOriginRoute(
  origin:
    AirportDirectionStat,
): Promise<void> {
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

  selectRoute(
    route,
  )

  await nextTick()

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

function decorateFlightAirportLinks(): void {
  void nextTick(
    () => {
      const wrapper =
        document.querySelector<HTMLElement>(
          '.flight-panel-wrapper',
        )

      if (
        !wrapper ||
        !selectedFlight.value
      ) {
        return
      }

      wrapper
        .querySelectorAll<HTMLElement>(
          '[data-flight-airport-code]',
        )
        .forEach(
          (element) => {
            element.classList.remove(
              'flight-airport-code-link',
            )

            element.removeAttribute(
              'data-flight-airport-code',
            )

            element.removeAttribute(
              'role',
            )

            element.removeAttribute(
              'tabindex',
            )
          },
        )

      const codes = [
        selectedFlight.value
          .departure_iata,
        selectedFlight.value
          .arrival_iata,
      ].filter(
        (
          code,
        ): code is string =>
          Boolean(code),
      )

      for (
        const code
        of codes
      ) {
        const candidates =
          Array.from(
            wrapper.querySelectorAll<HTMLElement>(
              '*',
            ),
          )

        const target =
          candidates.find(
            (element) =>
              element.children.length ===
                0 &&
              element.textContent
                ?.trim() ===
                code,
          )

        if (!target) {
          continue
        }

        target.classList.add(
          'flight-airport-code-link',
        )

        target.dataset.flightAirportCode =
          code

        target.setAttribute(
          'role',
          'button',
        )

        target.setAttribute(
          'tabindex',
          '0',
        )
      }
    },
  )
}


function openFlightAirportFromElement(
  target:
    EventTarget | null,
): void {
  if (
    !(target instanceof HTMLElement)
  ) {
    return
  }

  const link =
    target.closest<HTMLElement>(
      '[data-flight-airport-code]',
    )

  const code =
    link?.dataset
      .flightAirportCode

  if (!code) {
    return
  }

  void openAirportByCode(
    code,
  )
}


function handleFlightPanelClick(
  event:
    MouseEvent,
): void {
  openFlightAirportFromElement(
    event.target,
  )
}


function handleFlightPanelKeydown(
  event:
    KeyboardEvent,
): void {
  if (
    event.key !==
      'Enter' &&
    event.key !==
      ' '
  ) {
    return
  }

  const target =
    event.target

  if (
    target instanceof
      HTMLElement &&
    target.dataset
      .flightAirportCode
  ) {
    event.preventDefault()

    openFlightAirportFromElement(
      target,
    )
  }
}


function decorateRouteAirportLinks(): void {
  void nextTick(
    () => {
      const wrapper =
        document.querySelector<HTMLElement>(
          '.route-panel-wrapper',
        )

      if (
        !wrapper ||
        !selectedRoute.value
      ) {
        return
      }

      wrapper
        .querySelectorAll<HTMLElement>(
          '[data-route-airport-code]',
        )
        .forEach(
          (element) => {
            element.classList.remove(
              'route-airport-code-link',
            )

            element.removeAttribute(
              'data-route-airport-code',
            )

            element.removeAttribute(
              'role',
            )

            element.removeAttribute(
              'tabindex',
            )
          },
        )

      const codes = [
        selectedRoute.value
          .departureCode,
        selectedRoute.value
          .arrivalCode,
      ].filter(
        (
          code,
        ): code is string =>
          Boolean(code),
      )

      for (
        const code
        of codes
      ) {
        const candidates =
          Array.from(
            wrapper.querySelectorAll<HTMLElement>(
              '*',
            ),
          )

        const target =
          candidates.find(
            (element) =>
              element.children.length ===
                0 &&
              element.textContent
                ?.trim() ===
                code,
          )

        if (!target) {
          continue
        }

        target.classList.add(
          'route-airport-code-link',
        )

        target.dataset.routeAirportCode =
          code

        target.setAttribute(
          'role',
          'button',
        )

        target.setAttribute(
          'tabindex',
          '0',
        )
      }
    },
  )
}


function openRouteAirportFromElement(
  target:
    EventTarget | null,
): void {
  if (
    !(target instanceof HTMLElement)
  ) {
    return
  }

  const link =
    target.closest<HTMLElement>(
      '[data-route-airport-code]',
    )

  const code =
    link?.dataset
      .routeAirportCode

  if (!code) {
    return
  }

  openAirportByCode(
    code,
  )
}


function handleRoutePanelClick(
  event:
    MouseEvent,
): void {
  openRouteAirportFromElement(
    event.target,
  )
}


function handleRoutePanelKeydown(
  event:
    KeyboardEvent,
): void {
  if (
    event.key !==
      'Enter' &&
    event.key !==
      ' '
  ) {
    return
  }

  const target =
    event.target

  if (
    target instanceof
      HTMLElement &&
    target.dataset
      .routeAirportCode
  ) {
    event.preventDefault()

    openRouteAirportFromElement(
      target,
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

    updateFlightMapData(
      mapInstance,
      mapFlights.value,
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
  rightPanelKey,

  (
    newKey,
    oldKey,
  ) => {
    if (
      newKey &&
      newKey !==
        oldKey
    ) {
      rightPanelCollapsed.value =
        false

      normalizeRightPanelControls()
    }

    if (!newKey) {
      rightPanelCollapsed.value =
        false
    }
  },
)


watch(
  selectedRoute,

  () => {
    decorateRouteAirportLinks()
  },
)


watch(
  selectedFlight,

  () => {
    decorateFlightAirportLinks()
  },
)


watch(
  selectedAirport,

  () => {
    refreshMapForCurrentAirport()
  },
)


watch(
  mapFlights,

  (flights) => {
    if (!mapInstance) {
      return
    }

    clearSelection()

    routeReturnSection.value =
      null

    if (
      selectedAirport.value
    ) {
      updateFlightMapData(
        mapInstance,
        flightsForAirport(
          selectedAirport.value,
          flights,
        ),
      )
    } else {
      updateFlightMapData(
        mapInstance,
        flights,
      )
    }

    if (
      activeTab.value ===
      'flights' &&
      !selectedAirport.value
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
    window.addEventListener(
      'resize',
      normalizeRightPanelControls,
    )

    document.addEventListener(
      'fullscreenchange',
      handleFullscreenChange,
    )

    const params =
      new URLSearchParams(
        window.location.search,
      )

    const requestedAccountMode =
      params.get('konto')

    const token =
      params.get('token')

    if (
      requestedAccountMode === 'aktywacja' &&
      token
    ) {
      accountPanelMode.value =
        'activate'

      accountToken.value =
        token

      activeTab.value =
        'account'
    } else if (
      requestedAccountMode === 'reset' &&
      token
    ) {
      accountPanelMode.value =
        'reset'

      accountToken.value =
        token

      activeTab.value =
        'account'
    }
    else if (
      requestedAccountMode === 'email' &&
      token
    ) {
      accountPanelMode.value =
        'email-confirm'

      accountToken.value =
        token

      activeTab.value =
        'account'
    }

    const path =
      decodeURIComponent(
        window.location.pathname,
      )

    const publicMatch =
      path.match(
        /^\/profil\/([^/]+)\/?$/,
      )

    const sharedMatch =
      path.match(
        /^\/udostepniona\/([^/]+)\/?$/,
      )

    try {
      if (publicMatch) {
        const response =
          await getPublicProfile(
            publicMatch[1],
          )

        publicProfile.value =
          response.profile

        publicAccessMode.value =
          response.access_mode

        allFlights.value =
          response.flights
      } else if (sharedMatch) {
        const response =
          await getSharedMap(
            sharedMatch[1],
          )

        publicProfile.value =
          response.profile

        publicAccessMode.value =
          response.access_mode

        allFlights.value =
          response.flights
      } else {
        const authState =
          await getAuthState()

        currentUser.value =
          authState.user

        if (authState.authenticated) {
          const response =
            await getUserFlights()

          allFlights.value =
            response.flights
        } else {
          allFlights.value = []
        }
      }

      filteredFlights.value =
        filterFlightsByScope(
          allFlights.value,
          scope.value,
        )
    } catch (error) {
      console.error(error)
      allFlights.value = []
      filteredFlights.value = []
    }

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
  },
)


onBeforeUnmount(
  () => {
    window.removeEventListener(
      'resize',
      normalizeRightPanelControls,
    )

    document.removeEventListener(
      'fullscreenchange',
      handleFullscreenChange,
    )
  },
)
</script>

<template>
  <main
    ref="appShell"
    class="app-shell"
    :class="{
      'app-shell--map-fullscreen':
        fullscreenMapMode,
    }"
  >
    <AdminPanel
      v-if="adminRoute"
      :user="currentUser"
    />

    <div
      ref="mapContainer"
      class="map"
    ></div>

    <AppSidebar
      v-show="!fullscreenMapMode"
      :flights="visibleFlights"
      :active-tab="activeTab"
      :scope="scope"
      :collapsed="sidebarCollapsed"
      :active-flight-id="selectedFlightId"
      :initial-aircraft-filter-key="aircraftFilterKey"
      :authenticated="authenticated"
      :user-name="currentUser?.nick ?? ''"
      :privacy-mode="currentUser?.privacy_mode ?? 'private'"
      :is-admin="currentUser?.is_admin ?? false"
      @toggle="toggleSidebar"
      @tab="changeTab"
      @scope="changeScope"
      @flight="selectFlightFromList"
      @filtered-flights="receiveFilteredFlights"
      @aircraft-filter-changed="receiveAircraftFilterChanged"
      @statistics-airports="openAirportStatistics"
      @statistics-report="openStatisticsReport"
      @statistics-section="openStatisticsSection"
      @statistics-records="openStatisticsRecords"
      @add-flight="openAddFlight"
      @auth-choice="openAuthChoice"
      @account-action="openAccountPanel"
      @fullscreen="toggleMapFullscreen"
      @logout="requestToolboxLogout"
    />

    <div
      v-if="
        !sidebarCollapsed &&
        !fullscreenMapMode
      "
      ref="transAzjaAdPanel"
      class="transazja-ad-panel"
    >
      <TripAdCard />
    </div>

    <button
      v-if="
        rightPanelKey &&
        ![
          'activate',
          'login',
          'register',
          'reset',
          'email-confirm',
        ].includes(
          accountPanelMode ?? '',
        ) &&
        !fullscreenMapMode
      "
      ref="rightPanelCollapseButton"
      type="button"
      class="right-panel-collapse-button"
      :class="{
        'right-panel-collapse-button--collapsed':
          rightPanelCollapsed,
      }"
      :title="
        rightPanelCollapsed
          ? 'Rozwiń panel'
          : 'Zwiń panel'
      "
      :aria-label="
        rightPanelCollapsed
          ? 'Rozwiń panel'
          : 'Zwiń panel'
      "
      @click="toggleRightPanelCollapsed"
    >
      <svg
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path
          v-if="!rightPanelCollapsed"
          d="M7 9l5 5 5-5"
        />

        <path
          v-else
          d="M7 15l5-5 5 5"
        />
      </svg>

      <span
        v-if="rightPanelCollapsed"
      >
        Rozwiń panel
      </span>
    </button>

    <aside
      v-if="
        authChoiceOpen &&
        !fullscreenMapMode
      "
      class="auth-choice-panel"
      aria-label="Zaloguj się lub załóż konto"
    >
      <button
        type="button"
        class="auth-choice-panel__close"
        title="Zamknij"
        aria-label="Zamknij"
        @click="closeAuthChoice"
      >
        ×
      </button>

      <div class="auth-choice-panel__header">
        <div class="auth-choice-panel__eyebrow">
          Dodawanie lotów
        </div>

        <h2>
          Zaloguj się lub załóż konto
        </h2>

        <p>
          Aby dodawać własne loty i zapisywać historię,
          potrzebujesz konta w Mapie Lotów.
        </p>
      </div>

      <div class="auth-choice-panel__actions">
        <a
          href="#"
          class="auth-choice-panel__primary"
          @click.prevent="openAccountPanel('login')"
        >
          Zaloguj się
        </a>

        <a
          href="#"
          class="auth-choice-panel__secondary"
          @click.prevent="openAccountPanel('register')"
        >
          Załóż konto
        </a>
      </div>
    </aside>

    <div
      v-if="
        logoutConfirmOpen &&
        !fullscreenMapMode
      "
      class="logout-confirm-overlay"
      @click.self="cancelToolboxLogout"
    >
      <section
        class="logout-confirm-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="logout-confirm-title"
      >
        <div class="logout-confirm-panel__icon">
          <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path d="M10 5H5v14h5" />
            <path d="M13 8l4 4-4 4" />
            <path d="M17 12H9" />
          </svg>
        </div>

        <h2 id="logout-confirm-title">
          Czy chcesz opuścić Mapę Lotów?
        </h2>

        <p>
          Zostaniesz wylogowany z bieżącej sesji.
        </p>

        <div class="logout-confirm-panel__actions">
          <button
            type="button"
            class="logout-confirm-panel__cancel"
            @click="cancelToolboxLogout"
          >
            Zostań
          </button>

          <button
            type="button"
            class="logout-confirm-panel__confirm"
            @click="confirmToolboxLogout"
          >
            Wyloguj
          </button>
        </div>
      </section>
    </div>

    <AccountPanel
      v-if="
        accountPanelMode &&
        !fullscreenMapMode
      "
      v-show="!rightPanelCollapsed"
      :user="currentUser"
      :mode="accountPanelMode"
      :token="accountToken"
      @close="closeAccountPanel"
      @mode="setAccountPanelMode"
      @authenticated="handleAuthenticatedUser"
      @user-updated="handleAccountUserUpdated"
      @logged-out="handleLoggedOut"
    />

    <div
      v-if="
        publicProfile &&
        !fullscreenMapMode
      "
      class="public-profile-badge"
    >
      <div class="public-profile-badge__avatar">
        <img
          :src="publicProfile.avatar_url"
          alt=""
          onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'"
        >
        <span>
          {{ publicProfile.nick.charAt(0).toUpperCase() }}
        </span>
      </div>

      <div>
        <small>
          {{ publicAccessMode === 'link' ? 'Mapa udostępniona' : 'Profil publiczny' }}
        </small>
        <strong>{{ publicProfile.nick }}</strong>
      </div>
    </div>

    <AddFlightPanel
      v-if="
        addFlightOpen &&
        !fullscreenMapMode
      "
      v-show="!rightPanelCollapsed"
      :user-id="currentUser?.id ?? 0"
      :mode="flightFormMode"
      :initial-flight="flightFormInitialFlight"
      @close="closeAddFlight"
      @saved="refreshFlightsAfterSave"
      @open-existing="openExistingFromAddFlight"
    />

    <button
      v-if="
        aircraftReturnToStatistics &&
        activeTab === 'flights' &&
        selectedFlightId === null &&
        !fullscreenMapMode
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
      v-if="
        selectedAirport &&
        !fullscreenMapMode
      "
      v-show="!rightPanelCollapsed"
      :airport="selectedAirport"
      :flights="visibleFlights"
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
          null &&
        !fullscreenMapMode
      "
      v-show="!rightPanelCollapsed"
      class="route-panel-wrapper"
      @click="handleRoutePanelClick"
      @keydown="handleRoutePanelKeydown"
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

    <div
      v-if="
        selectedFlightId !==
        null &&
        !fullscreenMapMode
      "
      v-show="!rightPanelCollapsed"
      class="flight-panel-wrapper"
      @click="handleFlightPanelClick"
      @keydown="handleFlightPanelKeydown"
    >
      <FlightDetailsPanel
        :flight="selectedFlight"
        :loading="flightLoading"
        :error="flightError"
        @back="backFromFlight"
        @close="closeFlight"
        @edit="openEditFlight"
        @duplicate="openDuplicateFlight"
        @delete="deleteSelectedFlight"
      />
    </div>

    <AirportStatisticsPanel
      v-if="
        airportStatisticsOpen &&
        !fullscreenMapMode
      "
      key="statistics-airports"
      v-draggable-report
      v-show="!rightPanelCollapsed"
      :flights="visibleFlights"
      @airport="openAirportFromStatistics"
      @close="closeAirportStatistics"
    />

    <StatisticsMetricPanel
      v-if="
        statisticsReport &&
        !fullscreenMapMode
      "
      :key="`statistics-report:${statisticsReport}`"
      v-draggable-report
      v-show="!rightPanelCollapsed"
      :flights="visibleFlights"
      :report-type="statisticsReport"
      @close="closeStatisticsReport"
    />

    <StatisticsCategoryPanel
      v-if="
        statisticsSection &&
        !fullscreenMapMode
      "
      :key="`statistics-section:${statisticsSection}`"
      v-draggable-report
      v-show="!rightPanelCollapsed"
      :flights="visibleFlights"
      :section="statisticsSection"
      @aircraft="openAircraftFlightsFromStatistics"
      @route="openRouteFromStatistics"
      @close="closeStatisticsSection"
    />

    <RecordsStatisticsPanel
      v-if="
        statisticsRecordsOpen &&
        !fullscreenMapMode
      "
      key="statistics-records"
      v-draggable-report
      v-show="!rightPanelCollapsed"
      :flights="visibleFlights"
      @close="closeStatisticsRecords"
    />

    <footer
      v-if="!fullscreenMapMode"
      class="legal-footer"
      aria-label="Informacje prawne"
    >
      <span>
        © 2026 Mapa Lotów
      </span>

      <a
        href="#"
        @click.prevent
      >
        Regulamin
      </a>

      <a
        href="#"
        @click.prevent
      >
        Polityka Prywatności
      </a>

      <a
        href="#"
        @click.prevent
      >
        O projekcie
      </a>

      <a
        href="#"
        @click.prevent
      >
        Kontakt
      </a>
    </footer>

    <button
      v-if="fullscreenMapMode"
      type="button"
      class="fullscreen-exit-button"
      title="Wyjdź z pełnego ekranu"
      aria-label="Wyjdź z pełnego ekranu"
      @click="toggleMapFullscreen"
    >
      <svg
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path d="M9 9H4V4" />
        <path d="M15 9h5V4" />
        <path d="M20 15v5h-5" />
        <path d="M9 15H4v5" />
      </svg>
    </button>
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


.app-shell:fullscreen {
  background: #f4f4f4;
}


.logout-confirm-overlay {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 18px;
  background: rgba(15, 23, 42, 0.26);
  backdrop-filter: blur(3px);
}


.logout-confirm-panel {
  width: min(390px, calc(100vw - 36px));
  box-sizing: border-box;
  padding: 24px;
  border: 1px solid #dbe2e9;
  border-radius: 15px;
  background: rgba(255, 255, 255, 0.99);
  box-shadow: 0 20px 52px rgba(15, 23, 42, 0.22);
  text-align: center;
}


.logout-confirm-panel__icon {
  display: inline-flex;
  width: 44px;
  height: 44px;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
  border-radius: 50%;
  background: #eef3f8;
  color: #0b2d5c;
}


.logout-confirm-panel__icon svg {
  width: 21px;
  height: 21px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}


.logout-confirm-panel h2 {
  margin: 0;
  color: #0b2d5c;
  font-size: 20px;
}


.logout-confirm-panel p {
  margin: 9px 0 20px;
  color: #687686;
  font-size: 12px;
  line-height: 1.5;
}


.logout-confirm-panel__actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 9px;
}


.logout-confirm-panel__actions button {
  min-height: 42px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 800;
}


.logout-confirm-panel__cancel {
  border: 1px solid #d6dde5;
  background: #f7f9fb;
  color: #526273;
}


.logout-confirm-panel__confirm {
  border: 1px solid #0b2d5c;
  background: #0b2d5c;
  color: #fff;
}


.legal-footer {
  position: absolute;
  bottom: 4px;
  left: 50%;
  z-index: 17;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 3px 9px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.74);
  color: rgba(74, 85, 104, 0.78);
  font-size: 11px;
  font-weight: 550;
  line-height: 1;
  white-space: nowrap;
  backdrop-filter: blur(5px);
  transform: translateX(-50%);
}


.legal-footer a {
  color: inherit;
  text-decoration: none;
}


.legal-footer a:hover {
  color: #0b2d5c;
  text-decoration: underline;
  text-underline-offset: 2px;
}


.fullscreen-exit-button {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 120;
  display: inline-flex;
  width: 40px;
  height: 40px;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 1px solid rgba(11, 45, 92, 0.18);
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.90);
  color: #536171;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.10);
  backdrop-filter: blur(8px);
}


.fullscreen-exit-button:hover {
  background: #fff;
  color: #0b2d5c;
}


.fullscreen-exit-button svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}


@media (max-width: 760px) {
  .legal-footer {
    bottom: 3px;
    gap: 5px;
    max-width: calc(100vw - 20px);
    overflow: hidden;
    padding: 3px 7px;
    font-size: 10px;
  }
}

.map {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}


.public-profile-badge {
  position: absolute;
  top: 18px;
  left: 50%;
  z-index: 20;
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 7px 11px 7px 7px;
  border: 1px solid rgba(11, 45, 92, 0.13);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.10);
  backdrop-filter: blur(8px);
  transform: translateX(-50%);
}

.public-profile-badge__avatar {
  position: relative;
  display: flex;
  width: 31px;
  height: 31px;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border-radius: 50%;
  background: #0b2d5c;
  color: #fff;
  font-size: 12px;
  font-weight: 800;
}

.public-profile-badge__avatar img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.public-profile-badge__avatar span {
  display: flex;
  width: 100%;
  height: 100%;
  align-items: center;
  justify-content: center;
}

.public-profile-badge small,
.public-profile-badge strong {
  display: block;
}

.public-profile-badge small {
  color: #87919d;
  font-size: 8px;
  line-height: 1.1;
}

.public-profile-badge strong {
  margin-top: 2px;
  color: #0b2d5c;
  font-size: 11px;
  line-height: 1.1;
}


.auth-choice-panel {
  position: absolute;
  top: 18px;
  right: 18px;
  z-index: 34;
  width: min(390px, calc(100vw - 36px));
  padding: 20px;
  border: 1px solid #e0e4e9;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.98);
  box-shadow: 0 14px 38px rgba(15, 23, 42, 0.15);
  backdrop-filter: blur(10px);
}


.auth-choice-panel__close {
  position: absolute;
  top: 12px;
  right: 12px;
  display: inline-flex;
  width: 36px;
  height: 36px;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  border-radius: 8px;
  background: #f1f2f3;
  color: #505862;
  cursor: pointer;
  font-size: 22px;
  line-height: 1;
}


.auth-choice-panel__header {
  padding-right: 38px;
}


.auth-choice-panel__eyebrow {
  margin-bottom: 5px;
  color: #9098a3;
  font-size: 9px;
  font-weight: 750;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}


.auth-choice-panel h2 {
  margin: 0;
  color: #0b2d5c;
  font-size: 19px;
  line-height: 1.15;
}


.auth-choice-panel p {
  margin: 9px 0 0;
  color: #687483;
  font-size: 11px;
  line-height: 1.45;
}


.auth-choice-panel__actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 9px;
  margin-top: 18px;
}


.auth-choice-panel__actions a {
  display: inline-flex;
  min-height: 40px;
  align-items: center;
  justify-content: center;
  padding: 0 12px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 750;
  text-decoration: none;
}


.auth-choice-panel__primary {
  border: 1px solid #0b2d5c;
  background: #0b2d5c;
  color: #fff;
}


.auth-choice-panel__secondary {
  border: 1px solid #d8dde4;
  background: #fff;
  color: #0b2d5c;
}


@media (max-width: 560px) {
  .auth-choice-panel {
    top: 10px;
    right: 10px;
    width: calc(100vw - 20px);
  }

  .auth-choice-panel__actions {
    grid-template-columns: 1fr;
  }
}


.transazja-ad-panel {
  position: absolute;
  right: 18px;
  bottom: 38px;
  z-index: 18;
  width: 360px;
  max-width: calc(100vw - 36px);
  pointer-events: auto;
}


@media (max-width: 900px) {
  .transazja-ad-panel {
    right: 10px;
    bottom: 34px;
    width: min(
      340px,
      calc(100vw - 20px)
    );
  }
}

.route-panel-wrapper {
  position: static;
}


.flight-panel-wrapper .flight-airport-code-link {
  color: #0b2d5c !important;
  cursor: pointer;
  text-decoration-line: underline;
  text-decoration-color: rgba(11, 45, 92, 0.5);
  text-decoration-thickness: 1px;
  text-underline-offset: 4px;
}

.flight-panel-wrapper .flight-airport-code-link:hover,
.flight-panel-wrapper .flight-airport-code-link:focus-visible {
  text-decoration-color: #0b2d5c;
  text-decoration-thickness: 2px;
  outline: none;
}


.route-panel-wrapper .route-airport-code-link {
  color: #0b2d5c !important;
  cursor: pointer;
  text-decoration-line: underline;
  text-decoration-color: rgba(11, 45, 92, 0.5);
  text-decoration-thickness: 1px;
  text-underline-offset: 4px;
}

.route-panel-wrapper .route-airport-code-link:hover,
.route-panel-wrapper .route-airport-code-link:focus-visible {
  text-decoration-color: #0b2d5c;
  text-decoration-thickness: 2px;
  outline: none;
}

.right-panel-collapse-button {
  position: fixed;
  top: 33px;
  right: 84px;
  z-index: 95;
  display: inline-flex;
  width: 40px;
  min-width: 40px;
  height: 40px;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  border-radius: 8px;
  background: #f1f2f3;
  color: #505862;
  cursor: pointer;
  box-shadow: none;
  line-height: 1;
}

.right-panel-collapse-button:hover {
  background: #e8eaed;
  color: #3f4650;
}

.right-panel-collapse-button svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.right-panel-collapse-button--collapsed {
  top: 18px !important;
  right: 18px !important;
  width: auto !important;
  min-width: 142px !important;
  height: 42px !important;
  padding: 0 14px !important;
  border: 1px solid rgba(11, 45, 92, 0.22) !important;
  border-radius: 9px !important;
  background: rgba(255, 255, 255, 0.98) !important;
  color: #0b2d5c !important;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.10) !important;
  font-size: 11px !important;
  font-weight: 700 !important;
  letter-spacing: 0.01em;
  white-space: nowrap !important;
}

.right-panel-collapse-button--collapsed:hover {
  border-color: rgba(11, 45, 92, 0.34) !important;
  background: #ffffff !important;
  box-shadow: 0 5px 16px rgba(0, 0, 0, 0.13) !important;
}

.right-panel-collapse-button--collapsed svg {
  width: 14px !important;
  height: 14px !important;
  flex: 0 0 auto;
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
  right: 118px;
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
