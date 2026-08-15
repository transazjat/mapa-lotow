<script setup lang="ts">
import {
  computed,
  nextTick,
  ref,
  watch,
} from 'vue'

import 'flag-icons/css/flag-icons.min.css'

import {
  createFlight,
  updateFlight,
  searchAircraftTypes,
  searchAirlines,
  searchAirports,
} from '../services/api'

import type {
  AircraftTypeSearchItem,
  AirlineSearchItem,
  AirportSearchItem,
  CreateFlightPayload,
  CreateFlightResponse,
  FlightDetails,
  FlightFormMode,
} from '../types/flight'


const props = withDefaults(
  defineProps<{
    userId: number
    mode?: FlightFormMode
    initialFlight?: FlightDetails | null
  }>(),
  {
    mode:
      'create',
    initialFlight:
      null,
  },
)


const emit = defineEmits<{
  close: []
  saved: [flightId: number]
  openExisting: [flightId: number]
}>()


type AirportSide =
  | 'departure'
  | 'arrival'

type CalendarTarget =
  | 'departure'
  | 'arrival'
  | null

type TimeTarget =
  | 'departure'
  | 'arrival'
  | null


const departureQuery =
  ref('')

const arrivalQuery =
  ref('')

const departureAirport =
  ref<AirportSearchItem | null>(
    null,
  )

const arrivalAirport =
  ref<AirportSearchItem | null>(
    null,
  )

const departureResults =
  ref<AirportSearchItem[]>(
    [],
  )

const arrivalResults =
  ref<AirportSearchItem[]>(
    [],
  )

const departureSearchLoading =
  ref(false)

const arrivalSearchLoading =
  ref(false)


const departureDate =
  ref<string | null>(
    null,
  )

const arrivalDate =
  ref<string | null>(
    null,
  )

const departureTime =
  ref<string | null>(
    null,
  )

const arrivalTime =
  ref<string | null>(
    null,
  )

const unknownDepartureTime =
  ref(false)

const unknownArrivalDate =
  ref(false)

const unknownArrivalTime =
  ref(false)


const airlineQuery =
  ref('')

const selectedAirline =
  ref<AirlineSearchItem | null>(
    null,
  )

const airlineResults =
  ref<AirlineSearchItem[]>(
    [],
  )

const airlineLoading =
  ref(false)


const aircraftQuery =
  ref('')

const selectedAircraft =
  ref<AircraftTypeSearchItem | null>(
    null,
  )

const aircraftResults =
  ref<AircraftTypeSearchItem[]>(
    [],
  )

const aircraftLoading =
  ref(false)


const activeDepartureIndex =
  ref(-1)

const activeArrivalIndex =
  ref(-1)

const activeAirlineIndex =
  ref(-1)

const activeAircraftIndex =
  ref(-1)


const flightNumber =
  ref('')

const travelClass =
  ref<
    'e' |
    'p' |
    'b' |
    'f'
  >(
    'e',
  )

const seatType =
  ref<
    'w' |
    'm' |
    'a' |
    null
  >(
    null,
  )

const travelReason =
  ref<
    'p' |
    'b'
  >(
    'p',
  )

const notes =
  ref('')


const calendarTarget =
  ref<CalendarTarget>(
    null,
  )

const calendarMonth =
  ref(
    new Date(
      new Date()
        .getFullYear(),
      new Date()
        .getMonth(),
      1,
    ),
  )


const timeTarget =
  ref<TimeTarget>(
    null,
  )

const timeHour =
  ref('12')

const timeMinute =
  ref('00')


const saving =
  ref(false)

const submitError =
  ref<string | null>(
    null,
  )

const duplicateResponse =
  ref<CreateFlightResponse | null>(
    null,
  )


const savedSuccessfully =
  ref(
    false,
  )


let departureTimer:
  ReturnType<typeof setTimeout> |
  null =
  null

let arrivalTimer:
  ReturnType<typeof setTimeout> |
  null =
  null

let airlineTimer:
  ReturnType<typeof setTimeout> |
  null =
  null

let aircraftTimer:
  ReturnType<typeof setTimeout> |
  null =
  null


const panelTitle =
  computed(
    () => {
      if (
        props.mode ===
        'edit'
      ) {
        return 'EDYTUJ LOT'
      }

      if (
        props.mode ===
        'duplicate'
      ) {
        return 'DUPLIKUJ LOT'
      }

      return 'DODAJ LOT'
    },
  )


const submitButtonLabel =
  computed(
    () =>
      props.mode ===
        'edit'
        ? 'Zapisz zmiany'
        : 'Dodaj lot',
  )


const successMessage =
  computed(
    () =>
      props.mode ===
        'edit'
        ? 'Lot został zmieniony'
        : 'Lot został dodany',
  )


function databaseTravelClassToForm(
  value:
    string | null,
):
  | 'e'
  | 'p'
  | 'b'
  | 'f' {
  switch (value) {
    case 'premium_economy':
    case 'premium':
    case 'p':
      return 'p'

    case 'business':
    case 'b':
      return 'b'

    case 'first':
    case 'f':
      return 'f'

    default:
      return 'e'
  }
}


function databaseSeatTypeToForm(
  value:
    string | null,
):
  | 'w'
  | 'm'
  | 'a'
  | null {
  switch (value) {
    case 'window':
    case 'w':
      return 'w'

    case 'middle':
    case 'm':
      return 'm'

    case 'aisle':
    case 'a':
      return 'a'

    default:
      return null
  }
}


function databaseTravelReasonToForm(
  value:
    string | null,
):
  | 'p'
  | 'b' {
  switch (value) {
    case 'business':
    case 'b':
      return 'b'

    default:
      return 'p'
  }
}


function hydrateFromInitialFlight(): void {
  const flight =
    props.initialFlight

  if (
    !flight ||
    props.mode ===
      'create'
  ) {
    return
  }


  departureAirport.value = {
    id:
      flight.departure_airport_id,

    iata_code:
      flight.departure_iata,

    icao_code:
      flight.departure_icao,

    name:
      flight.departure_airport_name,

    city:
      flight.departure_city,

    country:
      flight.departure_country,

    country_code:
      flight.departure_country_code ??
      null,

    latitude:
      flight.departure_latitude,

    longitude:
      flight.departure_longitude,

    timezone_name:
      flight.departure_timezone,
  }


  arrivalAirport.value = {
    id:
      flight.arrival_airport_id,

    iata_code:
      flight.arrival_iata,

    icao_code:
      flight.arrival_icao,

    name:
      flight.arrival_airport_name,

    city:
      flight.arrival_city,

    country:
      flight.arrival_country,

    country_code:
      flight.arrival_country_code ??
      null,

    latitude:
      flight.arrival_latitude,

    longitude:
      flight.arrival_longitude,

    timezone_name:
      flight.arrival_timezone,
  }


  departureQuery.value =
    airportPrimary(
      departureAirport.value,
    )

  arrivalQuery.value =
    airportPrimary(
      arrivalAirport.value,
    )


  selectedAirline.value =
    flight.airline_id !==
      null &&
    flight.airline_name
      ? {
          id:
            flight.airline_id,

          name:
            flight.airline_name,

          iata_code:
            flight.airline_iata,

          icao_code:
            flight.airline_icao,
        }
      : null


  airlineQuery.value =
    selectedAirline.value
      ?.name ??
    ''


  selectedAircraft.value =
    flight.aircraft_type_id !==
      null &&
    flight.aircraft_name
      ? {
          id:
            flight.aircraft_type_id,

          name:
            flight.aircraft_name,

          family:
            flight.aircraft_family,

          manufacturer:
            flight.aircraft_manufacturer,

          model:
            flight.aircraft_model,

          variant:
            flight.aircraft_variant,
        }
      : null


  aircraftQuery.value =
    selectedAircraft.value
      ?.name ??
    ''


  travelClass.value =
    databaseTravelClassToForm(
      flight.travel_class,
    )

  seatType.value =
    databaseSeatTypeToForm(
      flight.seat_type,
    )

  travelReason.value =
    databaseTravelReasonToForm(
      flight.travel_reason,
    )


  if (
    props.mode ===
    'duplicate'
  ) {
    departureDate.value =
      null

    departureTime.value =
      null

    arrivalDate.value =
      null

    arrivalTime.value =
      null

    unknownDepartureTime.value =
      false

    unknownArrivalDate.value =
      false

    unknownArrivalTime.value =
      false

    flightNumber.value =
      ''

    notes.value =
      ''

    return
  }


  departureDate.value =
    flight.departure_date

  departureTime.value =
    flight.departure_time

  arrivalDate.value =
    flight.arrival_date

  arrivalTime.value =
    flight.arrival_time

  unknownDepartureTime.value =
    flight.departure_time ===
      null

  unknownArrivalDate.value =
    flight.arrival_date ===
      null

  unknownArrivalTime.value =
    flight.arrival_time ===
      null

  flightNumber.value =
    flight.flight_number ??
    ''

  notes.value =
    flight.notes ??
    ''
}


watch(
  () => [
    props.mode,
    props.initialFlight?.id,
  ],

  () => {
    hydrateFromInitialFlight()
  },

  {
    immediate:
      true,
  },
)


const todayIso =
  computed(
    () => {
      const now =
        new Date()

      const y =
        now.getFullYear()

      const m =
        String(
          now.getMonth() +
          1,
        ).padStart(
          2,
          '0',
        )

      const d =
        String(
          now.getDate(),
        ).padStart(
          2,
          '0',
        )

      return `${y}-${m}-${d}`
    },
  )


const planned =
  computed(
    () =>
      departureDate.value !==
        null &&
      departureDate.value >
        todayIso.value,
  )


const hours =
  Array.from(
    {
      length:
        24,
    },
    (
      _,
      index,
    ) =>
      String(
        index,
      ).padStart(
        2,
        '0',
      ),
  )


const minutes = [
  '00',
  '05',
  '10',
  '15',
  '20',
  '25',
  '30',
  '35',
  '40',
  '45',
  '50',
  '55',
]


const calendarTitle =
  computed(
    () =>
      new Intl.DateTimeFormat(
        undefined,
        {
          month:
            'long',
          year:
            'numeric',
        },
      ).format(
        calendarMonth.value,
      ),
  )


const calendarDays =
  computed(
    () => {
      const year =
        calendarMonth.value
          .getFullYear()

      const month =
        calendarMonth.value
          .getMonth()

      const first =
        new Date(
          year,
          month,
          1,
        )

      const startOffset =
        (
          first.getDay() +
          6
        ) %
        7

      const daysInMonth =
        new Date(
          year,
          month + 1,
          0,
        ).getDate()

      const cells:
        Array<
          {
            day:
              number | null
            iso:
              string | null
          }
        > =
        []

      for (
        let i =
          0;
        i <
          startOffset;
        i++
      ) {
        cells.push({
          day:
            null,
          iso:
            null,
        })
      }

      for (
        let day =
          1;
        day <=
          daysInMonth;
        day++
      ) {
        const iso =
          `${year}-${String(
            month + 1,
          ).padStart(
            2,
            '0',
          )}-${String(
            day,
          ).padStart(
            2,
            '0',
          )}`

        cells.push({
          day,
          iso,
        })
      }

      return cells
    },
  )


const canSubmit =
  computed(
    () =>
      departureAirport.value !==
        null &&
      arrivalAirport.value !==
        null &&
      departureDate.value !==
        null &&
      !saving.value,
  )


function focusElement(
  id:
    string,
): void {
  void nextTick(
    () => {
      document
        .getElementById(
          id,
        )
        ?.focus()
    },
  )
}


function formatDate(
  value:
    string | null,
): string {
  if (!value) {
    return ''
  }

  const date =
    new Date(
      `${value}T12:00:00`,
    )

  return new Intl.DateTimeFormat(
    undefined,
    {
      day:
        '2-digit',
      month:
        'short',
      year:
        'numeric',
    },
  ).format(
    date,
  )
}


function flagClass(
  code:
    string | null,
): string | null {
  if (
    !code ||
    code.length !==
      2
  ) {
    return null
  }

  return `fi fi-${code.toLowerCase()}`
}


function localizedCountryName(
  code:
    string | null,

  fallback:
    string | null,
): string {
  if (!code) {
    return fallback ?? ''
  }

  const browserLanguage =
    navigator.language
      .toLowerCase()

  const locale =
    browserLanguage.startsWith(
      'en',
    )
      ? 'en'
      : 'pl'

  try {
    const displayNames =
      new Intl.DisplayNames(
        [locale],
        {
          type:
            'region',
        },
      )

    return (
      displayNames.of(
        code.toUpperCase(),
      ) ??
      fallback ??
      ''
    )
  } catch {
    return fallback ?? ''
  }
}


function airportPrimary(
  airport:
    AirportSearchItem,
): string {
  const code =
    airport.iata_code ??
    airport.icao_code ??
    '---'

  const country =
    localizedCountryName(
      airport.country_code,
      airport.country,
    )

  return [
    code,
    airport.city,
    country,
  ]
    .filter(Boolean)
    .join(' - ')
}


async function runAirportSearch(
  side:
    AirportSide,
): Promise<void> {
  const query =
    side ===
      'departure'
      ? departureQuery.value
      : arrivalQuery.value

  if (
    query.trim().length <
    2
  ) {
    if (
      side ===
      'departure'
    ) {
      departureResults.value =
        []
    } else {
      arrivalResults.value =
        []
    }

    return
  }

  const loading =
    side ===
      'departure'
      ? departureSearchLoading
      : arrivalSearchLoading

  loading.value =
    true

  try {
    const items =
      await searchAirports(
        query.trim(),
      )

    if (
      side ===
      'departure'
    ) {
      departureResults.value =
        items

      activeDepartureIndex.value =
        items.length
          ? 0
          : -1
    } else {
      arrivalResults.value =
        items

      activeArrivalIndex.value =
        items.length
          ? 0
          : -1
    }
  } finally {
    loading.value =
      false
  }
}


watch(
  departureQuery,

  () => {
    if (
      departureAirport.value &&
      departureQuery.value ===
        airportPrimary(
          departureAirport.value,
        )
    ) {
      departureResults.value =
        []

      activeDepartureIndex.value =
        -1

      return
    }

    if (
      departureAirport.value
    ) {
      departureAirport.value =
        null
    }

    if (
      departureTimer
    ) {
      clearTimeout(
        departureTimer,
      )
    }

    departureTimer =
      setTimeout(
        () => {
          void runAirportSearch(
            'departure',
          )
        },
        180,
      )
  },
)


watch(
  arrivalQuery,

  () => {
    if (
      arrivalAirport.value &&
      arrivalQuery.value ===
        airportPrimary(
          arrivalAirport.value,
        )
    ) {
      arrivalResults.value =
        []

      activeArrivalIndex.value =
        -1

      return
    }

    if (
      arrivalAirport.value
    ) {
      arrivalAirport.value =
        null
    }

    if (
      arrivalTimer
    ) {
      clearTimeout(
        arrivalTimer,
      )
    }

    arrivalTimer =
      setTimeout(
        () => {
          void runAirportSearch(
            'arrival',
          )
        },
        180,
      )
  },
)


watch(
  airlineQuery,

  () => {
    if (
      selectedAirline.value &&
      airlineQuery.value ===
        selectedAirline.value.name
    ) {
      airlineResults.value =
        []

      activeAirlineIndex.value =
        -1

      return
    }

    if (
      selectedAirline.value
    ) {
      selectedAirline.value =
        null
    }

    if (
      airlineTimer
    ) {
      clearTimeout(
        airlineTimer,
      )
    }

    airlineTimer =
      setTimeout(
        async () => {
          const query =
            airlineQuery.value
              .trim()

          if (
            query.length <
            2
          ) {
            airlineResults.value =
              []

            return
          }

          airlineLoading.value =
            true

          try {
            airlineResults.value =
              await searchAirlines(
                query,
              )

            activeAirlineIndex.value =
              airlineResults.value.length
                ? 0
                : -1
          } finally {
            airlineLoading.value =
              false
          }
        },
        180,
      )
  },
)


watch(
  aircraftQuery,

  () => {
    if (
      selectedAircraft.value &&
      aircraftQuery.value ===
        selectedAircraft.value.name
    ) {
      aircraftResults.value =
        []

      activeAircraftIndex.value =
        -1

      return
    }

    if (
      selectedAircraft.value
    ) {
      selectedAircraft.value =
        null
    }

    if (
      aircraftTimer
    ) {
      clearTimeout(
        aircraftTimer,
      )
    }

    aircraftTimer =
      setTimeout(
        async () => {
          const query =
            aircraftQuery.value
              .trim()

          if (
            query.length <
            1
          ) {
            aircraftResults.value =
              []

            return
          }

          aircraftLoading.value =
            true

          try {
            aircraftResults.value =
              await searchAircraftTypes(
                query,
              )

            activeAircraftIndex.value =
              aircraftResults.value.length
                ? 0
                : -1
          } finally {
            aircraftLoading.value =
              false
          }
        },
        180,
      )
  },
)


watch(
  departureDate,

  (
    value,
    oldValue,
  ) => {
    if (
      value &&
      !unknownArrivalDate.value &&
      (
        !arrivalDate.value ||
        arrivalDate.value ===
          oldValue
      )
    ) {
      arrivalDate.value =
        value
    }
  },
)


watch(
  unknownDepartureTime,

  (
    value,
  ) => {
    if (value) {
      departureTime.value =
        null
    }
  },
)


watch(
  unknownArrivalDate,

  (
    value,
  ) => {
    if (value) {
      arrivalDate.value =
        null

      arrivalTime.value =
        null

      unknownArrivalTime.value =
        true
    } else {
      unknownArrivalTime.value =
        false

      if (
        departureDate.value
      ) {
        arrivalDate.value =
          departureDate.value
      }
    }
  },
)


watch(
  unknownArrivalTime,

  (
    value,
  ) => {
    if (value) {
      arrivalTime.value =
        null
    }
  },
)


function chooseAirport(
  side:
    AirportSide,

  airport:
    AirportSearchItem,
): void {
  if (
    side ===
    'departure'
  ) {
    departureAirport.value =
      airport

    departureQuery.value =
      airportPrimary(
        airport,
      )

    departureResults.value =
      []

    activeDepartureIndex.value =
      -1

    void nextTick(
      () => {
        const input =
          document.querySelector<HTMLInputElement>(
            '#arrival-airport-input',
          )

        input?.focus()
      },
    )

    return
  }

  arrivalAirport.value =
    airport

  arrivalQuery.value =
    airportPrimary(
      airport,
    )

  arrivalResults.value =
    []

  activeArrivalIndex.value =
    -1

  focusElement(
    'departure-date-button',
  )
}


function chooseAirline(
  airline:
    AirlineSearchItem,
): void {
  selectedAirline.value =
    airline

  airlineQuery.value =
    airline.name

  airlineResults.value =
    []

  activeAirlineIndex.value =
    -1

  focusElement(
    'flight-number-input',
  )
}


function chooseAircraft(
  aircraft:
    AircraftTypeSearchItem,
): void {
  selectedAircraft.value =
    aircraft

  aircraftQuery.value =
    aircraft.name

  aircraftResults.value =
    []

  activeAircraftIndex.value =
    -1

  focusElement(
    'class-economy-button',
  )
}


function closeAutocompleteLater(
  type:
    'departure' |
    'arrival' |
    'airline' |
    'aircraft',
): void {
  setTimeout(
    () => {
      if (
        type ===
        'departure'
      ) {
        departureResults.value =
          []

        activeDepartureIndex.value =
          -1

        if (
          departureQuery.value.trim() &&
          !departureAirport.value
        ) {
          departureQuery.value =
            ''
        }
      } else if (
        type ===
        'arrival'
      ) {
        arrivalResults.value =
          []

        activeArrivalIndex.value =
          -1

        if (
          arrivalQuery.value.trim() &&
          !arrivalAirport.value
        ) {
          arrivalQuery.value =
            ''
        }
      } else if (
        type ===
        'airline'
      ) {
        airlineResults.value =
          []

        activeAirlineIndex.value =
          -1

        if (
          airlineQuery.value.trim() &&
          !selectedAirline.value
        ) {
          airlineQuery.value =
            ''
        }
      } else {
        aircraftResults.value =
          []

        activeAircraftIndex.value =
          -1

        if (
          aircraftQuery.value.trim() &&
          !selectedAircraft.value
        ) {
          aircraftQuery.value =
            ''
        }
      }
    },
    140,
  )
}

function moveActiveIndex(
  current:
    number,

  length:
    number,

  direction:
    1 |
    -1,
): number {
  if (
    length <=
    0
  ) {
    return -1
  }

  if (
    current <
    0
  ) {
    return direction ===
      1
      ? 0
      : length - 1
  }

  return (
    current +
    direction +
    length
  ) %
    length
}


function handleDepartureKeydown(
  event:
    KeyboardEvent,
): void {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    activeDepartureIndex.value =
      moveActiveIndex(
        activeDepartureIndex.value,
        departureResults.value.length,
        1,
      )

    scrollActiveSuggestion(
      'departure-suggestions',
      activeDepartureIndex.value,
    )
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    activeDepartureIndex.value =
      moveActiveIndex(
        activeDepartureIndex.value,
        departureResults.value.length,
        -1,
      )

    scrollActiveSuggestion(
      'departure-suggestions',
      activeDepartureIndex.value,
    )
  } else if (
    event.key === 'Enter' &&
    activeDepartureIndex.value >= 0
  ) {
    event.preventDefault()

    const item =
      departureResults.value[
        activeDepartureIndex.value
      ]

    if (item) {
      chooseAirport(
        'departure',
        item,
      )
    }
  } else if (
    event.key === 'Tab' &&
    departureResults.value.length > 0
  ) {
    event.preventDefault()

    const index =
      activeDepartureIndex.value >= 0
        ? activeDepartureIndex.value
        : 0

    const item =
      departureResults.value[
        index
      ]

    if (item) {
      chooseAirport(
        'departure',
        item,
      )
    }
  } else if (event.key === 'Escape') {
    departureResults.value = []
  }
}


function handleArrivalKeydown(
  event:
    KeyboardEvent,
): void {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    activeArrivalIndex.value =
      moveActiveIndex(
        activeArrivalIndex.value,
        arrivalResults.value.length,
        1,
      )

    scrollActiveSuggestion(
      'arrival-suggestions',
      activeArrivalIndex.value,
    )
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    activeArrivalIndex.value =
      moveActiveIndex(
        activeArrivalIndex.value,
        arrivalResults.value.length,
        -1,
      )

    scrollActiveSuggestion(
      'arrival-suggestions',
      activeArrivalIndex.value,
    )
  } else if (
    event.key === 'Enter' &&
    activeArrivalIndex.value >= 0
  ) {
    event.preventDefault()

    const item =
      arrivalResults.value[
        activeArrivalIndex.value
      ]

    if (item) {
      chooseAirport(
        'arrival',
        item,
      )
    }
  } else if (
    event.key === 'Tab' &&
    arrivalResults.value.length > 0
  ) {
    event.preventDefault()

    const index =
      activeArrivalIndex.value >= 0
        ? activeArrivalIndex.value
        : 0

    const item =
      arrivalResults.value[
        index
      ]

    if (item) {
      chooseAirport(
        'arrival',
        item,
      )
    }
  } else if (event.key === 'Escape') {
    arrivalResults.value = []
  }
}


function handleAirlineKeydown(
  event:
    KeyboardEvent,
): void {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    activeAirlineIndex.value =
      moveActiveIndex(
        activeAirlineIndex.value,
        airlineResults.value.length,
        1,
      )

    scrollActiveSuggestion(
      'airline-suggestions',
      activeAirlineIndex.value,
    )
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    activeAirlineIndex.value =
      moveActiveIndex(
        activeAirlineIndex.value,
        airlineResults.value.length,
        -1,
      )

    scrollActiveSuggestion(
      'airline-suggestions',
      activeAirlineIndex.value,
    )
  } else if (
    event.key === 'Enter' &&
    activeAirlineIndex.value >= 0
  ) {
    event.preventDefault()

    const item =
      airlineResults.value[
        activeAirlineIndex.value
      ]

    if (item) {
      chooseAirline(item)
    }
  } else if (
    event.key === 'Tab' &&
    airlineResults.value.length > 0
  ) {
    event.preventDefault()

    const index =
      activeAirlineIndex.value >= 0
        ? activeAirlineIndex.value
        : 0

    const item =
      airlineResults.value[
        index
      ]

    if (item) {
      chooseAirline(item)
    }
  } else if (event.key === 'Escape') {
    airlineResults.value = []
  }
}


function handleAircraftKeydown(
  event:
    KeyboardEvent,
): void {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    activeAircraftIndex.value =
      moveActiveIndex(
        activeAircraftIndex.value,
        aircraftResults.value.length,
        1,
      )

    scrollActiveSuggestion(
      'aircraft-suggestions',
      activeAircraftIndex.value,
    )
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    activeAircraftIndex.value =
      moveActiveIndex(
        activeAircraftIndex.value,
        aircraftResults.value.length,
        -1,
      )

    scrollActiveSuggestion(
      'aircraft-suggestions',
      activeAircraftIndex.value,
    )
  } else if (
    event.key === 'Enter' &&
    activeAircraftIndex.value >= 0
  ) {
    event.preventDefault()

    const item =
      aircraftResults.value[
        activeAircraftIndex.value
      ]

    if (item) {
      chooseAircraft(item)
    }
  } else if (
    event.key === 'Tab' &&
    aircraftResults.value.length > 0
  ) {
    event.preventDefault()

    const index =
      activeAircraftIndex.value >= 0
        ? activeAircraftIndex.value
        : 0

    const item =
      aircraftResults.value[
        index
      ]

    if (item) {
      chooseAircraft(item)
    }
  } else if (event.key === 'Escape') {
    aircraftResults.value = []
  }
}


function scrollActiveSuggestion(
  listId:
    string,

  index:
    number,
): void {
  void nextTick(
    () => {
      const list =
        document.getElementById(
          listId,
        )

      if (!list) {
        return
      }

      const item =
        list.querySelector<HTMLElement>(
          `[data-autocomplete-index="${index}"]`,
        )

      item?.scrollIntoView({
        block:
          'nearest',
      })
    },
  )
}


function prepareFlightNumber(): void {
  if (
    flightNumber.value.trim()
  ) {
    return
  }

  const prefix =
    selectedAirline.value
      ?.iata_code
      ?.trim()
      .toUpperCase()

  if (prefix) {
    flightNumber.value =
      prefix
  }
}


function focusCalendarDate(): void {
  void nextTick(
    () => {
      const selected =
        document.querySelector<HTMLButtonElement>(
          '.calendar-grid button.selected:not(:disabled)',
        )

      const today =
        document.querySelector<HTMLButtonElement>(
          '.calendar-grid button.today:not(:disabled)',
        )

      const first =
        document.querySelector<HTMLButtonElement>(
          '.calendar-grid button:not(:disabled)',
        )

      ;(
        selected ??
        today ??
        first
      )?.focus()
    },
  )
}


function handleCalendarKeydown(
  event:
    KeyboardEvent,

  iso:
    string | null,
): void {
  if (!iso) {
    return
  }

  const buttons =
    Array.from(
      document.querySelectorAll<HTMLButtonElement>(
        '.calendar-grid button:not(:disabled)',
      ),
    )

  const currentIndex =
    buttons.findIndex(
      (button) =>
        button.dataset.date ===
        iso,
    )

  if (
    currentIndex <
    0
  ) {
    return
  }

  let nextIndex:
    number | null =
    null

  switch (
    event.key
  ) {
    case 'ArrowRight':
      nextIndex =
        currentIndex +
        1
      break

    case 'ArrowLeft':
      nextIndex =
        currentIndex -
        1
      break

    case 'ArrowDown':
      nextIndex =
        currentIndex +
        7
      break

    case 'ArrowUp':
      nextIndex =
        currentIndex -
        7
      break

    case 'Home':
      nextIndex =
        Math.max(
          0,
          currentIndex -
          (
            currentIndex %
            7
          ),
        )
      break

    case 'End':
      nextIndex =
        Math.min(
          buttons.length -
          1,
          currentIndex +
          (
            6 -
            (
              currentIndex %
              7
            )
          ),
        )
      break

    case 'PageUp':
      event.preventDefault()

      changeCalendarMonth(
        -1,
      )

      void nextTick(
        focusCalendarDate,
      )

      return

    case 'PageDown':
      event.preventDefault()

      changeCalendarMonth(
        1,
      )

      void nextTick(
        focusCalendarDate,
      )

      return

    case 'Escape':
      event.preventDefault()

      calendarTarget.value =
        null

      return

    default:
      return
  }

  event.preventDefault()

  if (
    nextIndex !==
      null &&
    nextIndex >=
      0 &&
    nextIndex <
      buttons.length
  ) {
    buttons[
      nextIndex
    ]?.focus()
  }
}


function openCalendar(
  target:
    Exclude<
      CalendarTarget,
      null
    >,
): void {
  calendarTarget.value =
    target

  const source =
    target ===
      'departure'
      ? departureDate.value
      : arrivalDate.value

  if (source) {
    const [
      year,
      month,
    ] =
      source
        .split(
          '-',
        )
        .map(
          Number,
        )

    calendarMonth.value =
      new Date(
        year,
        month - 1,
        1,
      )
  } else {
    const now =
      new Date()

    calendarMonth.value =
      new Date(
        now.getFullYear(),
        now.getMonth(),
        1,
      )
  }

  focusCalendarDate()
}


function changeCalendarMonth(
  delta:
    number,
): void {
  calendarMonth.value =
    new Date(
      calendarMonth.value
        .getFullYear(),
      calendarMonth.value
        .getMonth() +
        delta,
      1,
    )
}


function chooseDate(
  iso:
    string,
): void {
  const target =
    calendarTarget.value

  if (
    target ===
    'departure'
  ) {
    departureDate.value =
      iso
  } else if (
    target ===
    'arrival'
  ) {
    arrivalDate.value =
      iso
  }

  calendarTarget.value =
    null

  if (
    target ===
    'departure'
  ) {
    focusElement(
      'departure-time-button',
    )
  } else if (
    target ===
    'arrival'
  ) {
    focusElement(
      'arrival-time-button',
    )
  }
}


function openTimePicker(
  target:
    Exclude<
      TimeTarget,
      null
    >,
): void {
  timeTarget.value =
    target

  const source =
    target ===
      'departure'
      ? departureTime.value
      : (
          arrivalTime.value ??
          departureTime.value
        )

  if (source) {
    const [
      hour,
      minute,
    ] =
      source
        .slice(
          0,
          5,
        )
        .split(
          ':',
        )

    timeHour.value =
      hour

    timeMinute.value =
      minute
  } else {
    timeHour.value =
      '12'

    timeMinute.value =
      '00'
  }

  void nextTick(
    () => {
      document
        .querySelector<HTMLSelectElement>(
          '#time-hour-select',
        )
        ?.focus()
    },
  )
}


function saveTime(): void {
  const target =
    timeTarget.value

  const value =
    `${timeHour.value}:${timeMinute.value}:00`

  if (
    target ===
    'departure'
  ) {
    departureTime.value =
      value

    unknownDepartureTime.value =
      false
  } else if (
    target ===
    'arrival'
  ) {
    arrivalTime.value =
      value

    unknownArrivalTime.value =
      false
  }

  timeTarget.value =
    null

  if (
    target ===
    'departure'
  ) {
    focusElement(
      'arrival-date-button',
    )
  } else if (
    target ===
    'arrival'
  ) {
    focusElement(
      'airline-input',
    )
  }
}


function clearTime(): void {
  const target =
    timeTarget.value

  if (
    target ===
    'departure'
  ) {
    departureTime.value =
      null

    unknownDepartureTime.value =
      true
  } else if (
    target ===
    'arrival'
  ) {
    arrivalTime.value =
      null

    unknownArrivalTime.value =
      true
  }

  timeTarget.value =
    null

  if (
    target ===
    'departure'
  ) {
    focusElement(
      'arrival-date-button',
    )
  } else if (
    target ===
    'arrival'
  ) {
    focusElement(
      'airline-input',
    )
  }
}


function normalizeFlightNumber(): void {
  const normalized =
    flightNumber.value
      .trim()
      .toUpperCase()
      .replace(
        /\s+/g,
        '',
      )

  const airlinePrefix =
    selectedAirline.value
      ?.iata_code
      ?.trim()
      .toUpperCase() ??
    ''

  flightNumber.value =
    (
      airlinePrefix &&
      normalized ===
        airlinePrefix
    )
      ? ''
      : normalized
}


function validateForm(): string | null {
  if (
    !departureAirport.value
  ) {
    return 'Wybierz lotnisko wylotu z listy podpowiedzi.'
  }

  if (
    !arrivalAirport.value
  ) {
    return 'Wybierz lotnisko przylotu z listy podpowiedzi.'
  }

  if (
    !departureDate.value
  ) {
    return 'Data odlotu jest wymagana.'
  }

  if (
    airlineQuery.value.trim() &&
    !selectedAirline.value
  ) {
    return 'Wybierz linię lotniczą z listy podpowiedzi albo wyczyść pole.'
  }

  if (
    aircraftQuery.value.trim() &&
    !selectedAircraft.value
  ) {
    return 'Wybierz typ samolotu z listy podpowiedzi albo wyczyść pole.'
  }

  if (
    !unknownArrivalDate.value &&
    arrivalTime.value &&
    !arrivalDate.value
  ) {
    return 'Dla godziny przylotu trzeba wybrać datę przylotu.'
  }

  if (
    departureAirport.value.id ===
      arrivalAirport.value.id
  ) {
    return null
  }

  return null
}


function buildPayload(
  force:
    boolean,
): CreateFlightPayload {
  return {
    user_id:
      props.userId,

    departure_airport_id:
      departureAirport.value!.id,

    arrival_airport_id:
      arrivalAirport.value!.id,

    departure_date:
      departureDate.value!,

    departure_time:
      unknownDepartureTime.value
        ? null
        : departureTime.value,

    arrival_date:
      unknownArrivalDate.value
        ? null
        : arrivalDate.value,

    arrival_time:
      (
        unknownArrivalDate.value ||
        unknownArrivalTime.value
      )
        ? null
        : arrivalTime.value,

    airline_id:
      selectedAirline.value
        ?.id ??
      null,

    aircraft_type_id:
      selectedAircraft.value
        ?.id ??
      null,

    flight_number:
      flightNumber.value
        .trim()
        ? flightNumber.value
        : null,

    travel_class:
      travelClass.value,

    seat_type:
      seatType.value,

    travel_reason:
      travelReason.value,

    notes:
      notes.value
        .trim()
        ? notes.value.trim()
        : null,

    force,
  }
}


async function submit(
  force =
    false,
): Promise<void> {
  submitError.value =
    null

  duplicateResponse.value =
    null

  normalizeFlightNumber()

  const error =
    validateForm()

  if (error) {
    submitError.value =
      error

    return
  }

  saving.value =
    true

  try {
    const payload =
      buildPayload(
        force,
      )

    const response =
      (
        props.mode ===
          'edit' &&
        props.initialFlight
      )
        ? await updateFlight(
            props.initialFlight.id,
            payload,
          )
        : await createFlight(
            payload,
          )

    if (
      response.status ===
      'duplicate'
    ) {
      duplicateResponse.value =
        response

      return
    }

    if (
      response.status ===
        'ok' &&
      response.flight_id
    ) {
      savedSuccessfully.value =
        true

      const flightId =
        response.flight_id

      window.setTimeout(
        () => {
          emit(
            'saved',
            flightId,
          )
        },
        1000,
      )
    }
  } catch (
    error
  ) {
    submitError.value =
      error instanceof
        Error
        ? error.message
        : 'Nie udało się zapisać lotu.'
  } finally {
    saving.value =
      false
  }
}
</script>


<template>
  <aside class="add-flight-panel">
    <header class="panel-header">
      <div class="panel-title">
        <div class="route-symbol" aria-hidden="true">
          <span class="route-symbol__dot"></span>
          <span class="route-symbol__line"></span>
          <span class="route-symbol__plane">✈</span>
          <span class="route-symbol__line"></span>
          <span class="route-symbol__dot"></span>
        </div>

        <h2>{{ panelTitle }}</h2>

        <span
          v-if="planned"
          class="planned-badge"
        >
          ZAPLANOWANY
        </span>
      </div>

      <button
        type="button"
        class="close-button"
        title="Zamknij"
        aria-label="Zamknij"
        @click="emit('close')"
      >
        ×
      </button>
    </header>

    <div
      v-if="savedSuccessfully"
      class="save-success"
      role="status"
      aria-live="polite"
    >
      <div class="save-success__icon">
        ✓
      </div>

      <strong>
        {{ successMessage }}
      </strong>
    </div>

    <form
      v-else
      class="flight-form"
      @submit.prevent="submit()"
    >
      <div class="route-grid compact-block compact-block--route">
        <div class="field autocomplete-field">
          <label for="departure-airport-input">Wylot z</label>

          <input
            id="departure-airport-input"
            v-model="departureQuery"
            type="text"
            autocomplete="off"
            placeholder="Kod, miasto lub lotnisko"
            @keydown="handleDepartureKeydown"
            @blur="closeAutocompleteLater('departure')"
          >

          <div
            v-if="departureResults.length || departureSearchLoading"
            id="departure-suggestions"
            class="suggestions"
          >
            <div
              v-if="departureSearchLoading"
              class="suggestion-loading"
            >
              Szukam...
            </div>

            <button
              v-for="(airport, index) in departureResults"
              :key="airport.id"
              :data-autocomplete-index="index"
              type="button"
              class="suggestion"
              :class="{
                active:
                  activeDepartureIndex ===
                  index,
              }"
              @mousedown.prevent
              @click="chooseAirport('departure', airport)"
            >
              <span
                v-if="flagClass(airport.country_code)"
                :class="flagClass(airport.country_code)!"
                class="suggestion-flag"
              ></span>

              <span class="suggestion-main suggestion-main--airport">
                <span class="airport-result-line">
                  <strong>
                    {{
                      airport.iata_code ??
                      airport.icao_code ??
                      '---'
                    }}
                    -
                    {{ airport.city }}
                  </strong>

                  <span
                    v-if="
                      localizedCountryName(
                        airport.country_code,
                        airport.country,
                      )
                    "
                    class="airport-country"
                  >
                    -
                    {{
                      localizedCountryName(
                        airport.country_code,
                        airport.country,
                      )
                    }}
                  </span>
                </span>

                <small>{{ airport.name }}</small>
              </span>
            </button>
          </div>
        </div>

        <div class="route-arrow">→</div>

        <div class="field autocomplete-field">
          <label for="arrival-airport-input">Przylot do</label>

          <input
            id="arrival-airport-input"
            v-model="arrivalQuery"
            type="text"
            autocomplete="off"
            placeholder="Kod, miasto lub lotnisko"
            @keydown="handleArrivalKeydown"
            @blur="closeAutocompleteLater('arrival')"
          >

          <div
            v-if="arrivalResults.length || arrivalSearchLoading"
            id="arrival-suggestions"
            class="suggestions"
          >
            <div
              v-if="arrivalSearchLoading"
              class="suggestion-loading"
            >
              Szukam...
            </div>

            <button
              v-for="(airport, index) in arrivalResults"
              :key="airport.id"
              :data-autocomplete-index="index"
              type="button"
              class="suggestion"
              :class="{
                active:
                  activeArrivalIndex ===
                  index,
              }"
              @mousedown.prevent
              @click="chooseAirport('arrival', airport)"
            >
              <span
                v-if="flagClass(airport.country_code)"
                :class="flagClass(airport.country_code)!"
                class="suggestion-flag"
              ></span>

              <span class="suggestion-main suggestion-main--airport">
                <span class="airport-result-line">
                  <strong>
                    {{
                      airport.iata_code ??
                      airport.icao_code ??
                      '---'
                    }}
                    -
                    {{ airport.city }}
                  </strong>

                  <span
                    v-if="
                      localizedCountryName(
                        airport.country_code,
                        airport.country,
                      )
                    "
                    class="airport-country"
                  >
                    -
                    {{
                      localizedCountryName(
                        airport.country_code,
                        airport.country,
                      )
                    }}
                  </span>
                </span>

                <small>{{ airport.name }}</small>
              </span>
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="
          departureAirport &&
          arrivalAirport &&
          departureAirport.id === arrivalAirport.id
        "
        class="info-note"
      >
        To samo lotnisko startu i lądowania - lot widokowy / inny.
      </div>

      <div class="term-grid compact-block">
        <div class="field">
          <label>Data odlotu *</label>

          <button
            id="departure-date-button"
            type="button"
            class="picker-button"
            @click="openCalendar('departure')"
          >
            <span>
              {{ departureDate ? formatDate(departureDate) : 'Wybierz datę' }}
            </span>
            <span class="picker-icon">▣</span>
          </button>
        </div>

        <div class="field">
          <label>Godzina odlotu</label>

          <button
            id="departure-time-button"
            type="button"
            class="picker-button"
            :disabled="unknownDepartureTime"
            @click="openTimePicker('departure')"
          >
            <span>
              {{ departureTime ? departureTime.slice(0, 5) : 'Wybierz' }}
            </span>
            <span class="picker-icon">◷</span>
          </button>

          <label class="check-row">
            <input
              v-model="unknownDepartureTime"
              type="checkbox"
            >
            Nie pamiętam
          </label>
        </div>

        <div class="field">
          <label>Data przylotu</label>

          <button
            id="arrival-date-button"
            type="button"
            class="picker-button"
            :disabled="unknownArrivalDate"
            @click="openCalendar('arrival')"
          >
            <span>
              {{ arrivalDate ? formatDate(arrivalDate) : 'Wybierz datę' }}
            </span>
            <span class="picker-icon">▣</span>
          </button>

          <label class="check-row">
            <input
              v-model="unknownArrivalDate"
              type="checkbox"
            >
            Nie pamiętam
          </label>
        </div>

        <div class="field">
          <label>Godzina przylotu</label>

          <button
            id="arrival-time-button"
            type="button"
            class="picker-button"
            :disabled="unknownArrivalDate || unknownArrivalTime"
            @click="openTimePicker('arrival')"
          >
            <span>
              {{ arrivalTime ? arrivalTime.slice(0, 5) : 'Wybierz' }}
            </span>
            <span class="picker-icon">◷</span>
          </button>

          <label class="check-row">
            <input
              v-model="unknownArrivalTime"
              type="checkbox"
              :disabled="unknownArrivalDate"
            >
            Nie pamiętam
          </label>
        </div>
      </div>

      <div class="flight-data-grid compact-block compact-block--flight">
        <div class="field autocomplete-field">
          <label>Linia lotnicza</label>

          <input
            id="airline-input"
            v-model="airlineQuery"
            type="text"
            autocomplete="off"
            placeholder="Nazwa, IATA lub ICAO"
            @keydown="handleAirlineKeydown"
            @blur="closeAutocompleteLater('airline')"
          >

          <div
            v-if="airlineResults.length || airlineLoading"
            id="airline-suggestions"
            class="suggestions"
          >
            <div
              v-if="airlineLoading"
              class="suggestion-loading"
            >
              Szukam...
            </div>

            <button
              v-for="(airline, index) in airlineResults"
              :key="airline.id"
              :data-autocomplete-index="index"
              type="button"
              class="suggestion suggestion--single-line"
              :class="{
                active:
                  activeAirlineIndex === index,
              }"
              @mousedown.prevent
              @click="chooseAirline(airline)"
            >
              <strong>{{ airline.name }}</strong>

              <span>
                -
                {{
                  airline.iata_code ??
                  airline.icao_code ??
                  '---'
                }}
              </span>
            </button>
          </div>
        </div>

        <div class="field">
          <label>Numer lotu</label>

          <input
            id="flight-number-input"
            v-model="flightNumber"
            type="text"
            maxlength="16"
            autocomplete="off"
            placeholder="np. QR260"
            @focus="prepareFlightNumber"
            @blur="normalizeFlightNumber"
          >
        </div>

        <div class="field autocomplete-field">
          <label>Typ samolotu</label>

          <input
            id="aircraft-input"
            v-model="aircraftQuery"
            type="text"
            autocomplete="off"
            placeholder="np. A319, A330, B777"
            @keydown="handleAircraftKeydown"
            @blur="closeAutocompleteLater('aircraft')"
          >

          <div
            v-if="aircraftResults.length || aircraftLoading"
            id="aircraft-suggestions"
            class="suggestions"
          >
            <div
              v-if="aircraftLoading"
              class="suggestion-loading"
            >
              Szukam...
            </div>

            <button
              v-for="(aircraft, index) in aircraftResults"
              :key="aircraft.id"
              :data-autocomplete-index="index"
              type="button"
              class="suggestion"
              :class="{
                active:
                  activeAircraftIndex === index,
              }"
              @mousedown.prevent
              @click="chooseAircraft(aircraft)"
            >
              <span class="suggestion-main suggestion-main--aircraft">
                <strong>{{ aircraft.name }}</strong>
              </span>
            </button>
          </div>
        </div>
      </div>

      <div class="option-grid compact-block">
        <div class="option-group">
          <div class="option-label">Klasa</div>

          <div class="chips">
            <button id="class-economy-button" type="button" :class="{ active: travelClass === 'e' }" @click="travelClass = 'e'">Ekonomiczna</button>
            <button type="button" :class="{ active: travelClass === 'p' }" @click="travelClass = 'p'">Premium</button>
            <button type="button" :class="{ active: travelClass === 'b' }" @click="travelClass = 'b'">Biznes</button>
            <button type="button" :class="{ active: travelClass === 'f' }" @click="travelClass = 'f'">Pierwsza</button>
          </div>
        </div>

        <div class="option-group">
          <div class="option-label">Miejsce</div>

          <div class="chips">
            <button type="button" :class="{ active: seatType === 'w' }" @click="seatType = 'w'">Okno</button>
            <button type="button" :class="{ active: seatType === 'm' }" @click="seatType = 'm'">Środek</button>
            <button type="button" :class="{ active: seatType === 'a' }" @click="seatType = 'a'">Przejście</button>
            <button type="button" :class="{ active: seatType === null }" @click="seatType = null">Nie pamiętam</button>
          </div>
        </div>

        <div class="option-group">
          <div class="option-label">Cel</div>

          <div class="chips">
            <button type="button" :class="{ active: travelReason === 'p' }" @click="travelReason = 'p'">Prywatny</button>
            <button type="button" :class="{ active: travelReason === 'b' }" @click="travelReason = 'b'">Biznesowy</button>
          </div>
        </div>
      </div>

      <div class="field compact-block notes-field">
        <label>Notatka</label>

        <textarea
          v-model="notes"
          rows="2"
          placeholder="Opcjonalnie"
        ></textarea>
      </div>

      <div
        v-if="submitError"
        class="error-box"
      >
        {{ submitError }}
      </div>

      <div
        v-if="duplicateResponse?.status === 'duplicate'"
        class="duplicate-box"
      >
        <strong>Podobny lot już istnieje.</strong>

        <div
          v-if="duplicateResponse.duplicate"
          class="duplicate-flight"
        >
          {{ duplicateResponse.duplicate.departure_iata ?? '---' }}
          →
          {{ duplicateResponse.duplicate.arrival_iata ?? '---' }},
          {{ formatDate(duplicateResponse.duplicate.departure_date ?? null) }}
          <template v-if="duplicateResponse.duplicate.flight_number">
            · {{ duplicateResponse.duplicate.flight_number }}
          </template>
        </div>

        <div class="duplicate-actions">
          <button
            v-if="duplicateResponse.duplicate?.id"
            type="button"
            class="secondary-button"
            @click="emit('openExisting', duplicateResponse.duplicate!.id)"
          >
            Pokaż istniejący
          </button>

          <button
            type="button"
            class="warning-button"
            @click="submit(true)"
          >
            Mimo to dodaj
          </button>
        </div>
      </div>

      <footer class="form-actions">
        <div class="time-rule">
          Czas lotu liczymy tylko przy komplecie dat i godzin.
        </div>

        <div class="action-buttons">
          <button
            type="button"
            class="secondary-button"
            @click="emit('close')"
          >
            Anuluj
          </button>

          <button
            type="submit"
            class="primary-button"
            :disabled="!canSubmit"
          >
            {{ saving ? 'Zapisywanie...' : submitButtonLabel }}
          </button>
        </div>
      </footer>
    </form>

    <div
      v-if="calendarTarget"
      class="picker-backdrop"
      @click.self="calendarTarget = null"
    >
      <div class="calendar-popover">
        <div class="calendar-header">
          <button
            type="button"
            aria-label="Poprzedni miesiąc"
            @click="changeCalendarMonth(-1)"
          >
            ‹
          </button>
          <strong>{{ calendarTitle }}</strong>
          <button
            type="button"
            aria-label="Następny miesiąc"
            @click="changeCalendarMonth(1)"
          >
            ›
          </button>
        </div>

        <div class="calendar-weekdays">
          <span>Pn</span><span>Wt</span><span>Śr</span><span>Cz</span><span>Pt</span><span>So</span><span>Nd</span>
        </div>

        <div class="calendar-grid">
          <button
            v-for="(cell, index) in calendarDays"
            :key="index"
            type="button"
            :data-date="cell.iso ?? undefined"
            :disabled="!cell.iso"
            :class="{
              selected:
                cell.iso &&
                (
                  cell.iso === departureDate ||
                  cell.iso === arrivalDate
                ),
              today: cell.iso === todayIso,
            }"
            @keydown="
              handleCalendarKeydown(
                $event,
                cell.iso,
              )
            "
            @click="cell.iso && chooseDate(cell.iso)"
          >
            {{ cell.day }}
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="timeTarget"
      class="picker-backdrop"
      @click.self="timeTarget = null"
    >
      <div
        class="time-popover"
        @keydown.esc="
          timeTarget =
            null
        "
      >
        <div class="time-title">Wybierz godzinę</div>

        <div class="time-selects">
          <select
            id="time-hour-select"
            v-model="timeHour"
            aria-label="Godzina"
          >
            <option v-for="hour in hours" :key="hour" :value="hour">
              {{ hour }}
            </option>
          </select>

          <span>:</span>

          <select
            v-model="timeMinute"
            aria-label="Minuty"
          >
            <option v-for="minute in minutes" :key="minute" :value="minute">
              {{ minute }}
            </option>
          </select>
        </div>

        <div class="time-actions">
          <button
            type="button"
            class="secondary-button"
            @click="clearTime"
          >
            Nie pamiętam
          </button>

          <button
            type="button"
            class="primary-button"
            @click="saveTime"
          >
            Ustaw
          </button>
        </div>
      </div>
    </div>
  </aside>
</template>


<style scoped>
.add-flight-panel {
  position: absolute;
  top: 18px;
  right: 18px;
  z-index: 55;
  display: flex;
  width: min(880px, calc(100vw - 430px));
  max-height: calc(100vh - 36px);
  flex-direction: column;
  overflow: hidden;
  padding: 16px;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.985);
  box-shadow: 0 14px 40px rgba(0, 0, 0, 0.18);
  backdrop-filter: blur(12px);
}

.panel-header {
  display: flex;
  flex: 0 0 auto;
  min-height: 40px;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding-bottom: 9px;
  border-bottom: 1px solid #eceff2;
}

.panel-title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.panel-header h2 {
  margin: 0;
  color: #4f5864;
  font-size: 16px;
  font-weight: 800;
  letter-spacing: 0.04em;
}

.route-symbol {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  color: #0b2d5c;
}

.route-symbol__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.route-symbol__line {
  width: 19px;
  height: 1px;
  background: currentColor;
}

.route-symbol__plane {
  font-size: 13px;
  line-height: 1;
}

.planned-badge {
  padding: 4px 7px;
  border: 1px solid rgba(242, 140, 40, 0.32);
  border-radius: 6px;
  background: rgba(242, 140, 40, 0.09);
  color: #b96612;
  font-size: 9px;
  font-weight: 750;
}

.close-button {
  display: flex;
  width: 34px;
  height: 34px;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  border-radius: 8px;
  background: #f1f2f3;
  color: #505862;
  cursor: pointer;
  font-size: 21px;
}

.save-success {
  display: flex;
  min-height: 260px;
  flex: 1 1 auto;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #0b2d5c;
  text-align: center;
}

.save-success__icon {
  display: flex;
  width: 54px;
  height: 54px;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(11, 45, 92, 0.18);
  border-radius: 50%;
  background: rgba(11, 45, 92, 0.07);
  font-size: 28px;
  font-weight: 700;
}

.save-success strong {
  font-size: 17px;
  font-weight: 700;
}


.flight-form {
  min-height: 0;
  flex: 1 1 auto;
  overflow-y: auto;
  padding: 10px 2px 1px 0;
}

.compact-block {
  position: relative;
  z-index: 1;
  padding: 9px 10px;
  border: 1px solid #e4e7eb;
  border-radius: 9px;
  background: #fafafa;
}

.compact-block--route {
  z-index: 30;
}

.compact-block--flight {
  z-index: 20;
}

.compact-block + .compact-block,
.info-note + .compact-block {
  margin-top: 7px;
}

.route-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 26px minmax(0, 1fr);
  align-items: end;
  gap: 7px;
}

.route-arrow {
  padding-bottom: 10px;
  color: #83909e;
  font-size: 15px;
  text-align: center;
}

.term-grid {
  display: grid;
  grid-template-columns: 1.15fr 0.75fr 1.15fr 0.75fr;
  gap: 7px;
}

.flight-data-grid {
  display: grid;
  grid-template-columns: 1.15fr 0.7fr 1.15fr;
  gap: 7px;
}

.field {
  position: relative;
}

.field > label:not(.check-row) {
  display: block;
  margin-bottom: 4px;
  color: #606874;
  font-size: 10px;
  font-weight: 650;
}

.field input[type='text'],
.field textarea,
.picker-button {
  width: 100%;
  min-height: 33px;
  padding: 6px 9px;
  border: 1px solid #d6dce3;
  border-radius: 7px;
  outline: none;
  background: #fff;
  color: #0b2d5c;
  font-size: 11px;
  font-weight: 700;
}

.field input[type='text']:focus,
.field textarea:focus,
.picker-button:focus {
  border-color: #aab7c5;
  box-shadow: 0 0 0 2px rgba(11, 45, 92, 0.05);
}

.picker-button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  text-align: left;
}

.picker-button:disabled {
  background: #f0f1f2;
  color: #9ca3af;
  cursor: default;
}

.picker-icon {
  color: #9ca3af;
}

.check-row {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
  color: #7a828c;
  cursor: pointer;
  font-size: 9px;
}

.check-row input {
  position: relative;
  width: 14px;
  height: 14px;
  flex: 0 0 auto;
  margin: 0;
  border: 1px solid #b9c2cd;
  border-radius: 3px;
  appearance: none;
  background: #fff;
  cursor: pointer;
}

.check-row input:hover {
  border-color: #8e9baa;
}

.check-row input:checked {
  border-color: #0b2d5c;
  background: #0b2d5c;
}

.check-row input:checked::after {
  position: absolute;
  top: 0px;
  left: 3px;
  width: 4px;
  height: 8px;
  border-right: 2px solid #fff;
  border-bottom: 2px solid #fff;
  content: '';
  transform: rotate(45deg);
}

.check-row input:disabled {
  border-color: #d5d9de;
  background: #f1f2f3;
  cursor: default;
}

.autocomplete-field {
  z-index: 6;
}

.suggestions {
  position: absolute;
  top: calc(100% + 3px);
  right: 0;
  left: 0;
  z-index: 120;
  max-height: 160px;
  overflow-y: auto;
  border: 1px solid #dce1e7;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
}

.suggestion,
.suggestion-loading {
  width: 100%;
  padding: 6px 8px;
  border: 0;
  border-bottom: 1px solid #eef0f2;
  background: #fff;
  text-align: left;
}

.suggestion {
  display: flex;
  align-items: center;
  gap: 7px;
  min-height: 39px;
  cursor: pointer;
}

.suggestion:hover,
.suggestion.active {
  background: #eef3f8;
}

.suggestion-flag {
  width: 18px;
  height: 12px;
  flex: 0 0 auto;
  border-radius: 2px;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.07);
}

.suggestion-main {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.suggestion-main strong {
  color: #0b2d5c;
  font-size: 10px;
  font-weight: 700;
}

.suggestion-main small {
  margin-top: 1px;
  overflow: hidden;
  color: #0b2d5c;
  font-size: 8px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.suggestion-main--airport {
  gap: 1px;
}

.airport-result-line {
  display: flex;
  min-width: 0;
  align-items: baseline;
  gap: 4px;
  line-height: 1.1;
  white-space: nowrap;
}

.airport-result-line strong {
  color: #2d333b;
  font-size: 10px;
  font-weight: 700;
}

.airport-country {
  overflow: hidden;
  color: #0b2d5c;
  font-size: 11px;
  font-weight: 400;
  text-overflow: ellipsis;
}

.suggestion-main--airport small {
  margin: 1px 0 0;
  color: #0b2d5c;
  font-size: 11px;
  font-weight: 400;
  line-height: 1.1;
}

.suggestion-main--aircraft {
  justify-content: center;
}

.suggestion-main--aircraft strong {
  color: #0b2d5c;
  font-size: 10px;
  font-weight: 700;
}

.suggestion-loading {
  color: #8b929d;
  font-size: 9px;
}

.suggestion--single-line {
  min-height: 27px;
  gap: 5px;
  white-space: nowrap;
}

.suggestion--single-line strong {
  color: #0b2d5c;
  font-size: 10px;
  font-weight: 700;
}

.suggestion--single-line span {
  color: #0b2d5c;
  font-size: 9px;
  font-weight: 700;
}

.info-note {
  margin-top: 6px;
  padding: 6px 8px;
  border-radius: 7px;
  background: rgba(11, 45, 92, 0.045);
  color: #777f89;
  font-size: 9px;
}

.option-grid {
  display: grid;
  gap: 7px;
}

.option-group {
  display: grid;
  grid-template-columns: 70px 1fr;
  align-items: center;
  gap: 8px;
}

.option-label {
  color: #606771;
  font-size: 10px;
  font-weight: 650;
}

.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.chips button {
  min-height: 29px;
  padding: 0 9px;
  border: 1px solid #d8dde3;
  border-radius: 7px;
  background: #fff;
  color: #68717c;
  cursor: pointer;
  font-size: 10px;
}

.chips button.active {
  border-color: rgba(11, 45, 92, 0.28);
  background: rgba(11, 45, 92, 0.08);
  color: #0b2d5c;
  font-weight: 700;
}

.notes-field textarea {
  resize: vertical;
  font-size: 12px;
  font-weight: 400;
}

.time-rule {
  color: #828a94;
  font-size: 10px;
}

.error-box,
.duplicate-box {
  margin-top: 7px;
  padding: 8px 10px;
  border-radius: 8px;
  font-size: 10px;
}

.error-box {
  font-size: 12px;
  line-height: 1.35;
}

.error-box {
  border: 1px solid rgba(190, 24, 24, 0.18);
  background: rgba(190, 24, 24, 0.055);
  color: #991b1b;
}

.duplicate-box {
  border: 1px solid rgba(242, 140, 40, 0.28);
  background: rgba(242, 140, 40, 0.07);
  color: #704214;
}

.duplicate-flight {
  margin-top: 3px;
}

.duplicate-actions {
  display: flex;
  justify-content: flex-end;
  gap: 6px;
  margin-top: 7px;
}

.form-actions {
  position: sticky;
  bottom: 0;
  z-index: 8;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 7px;
  padding: 8px 0 1px;
  background: linear-gradient(to bottom, rgba(255,255,255,0), #fff 24%);
}

.action-buttons {
  display: flex;
  gap: 7px;
}

.primary-button,
.secondary-button,
.warning-button {
  min-height: 33px;
  padding: 0 12px;
  border-radius: 7px;
  cursor: pointer;
  font-size: 10px;
  font-weight: 700;
}

.primary-button {
  border: 1px solid #0b2d5c;
  background: #0b2d5c;
  color: #fff;
}

.primary-button:disabled {
  opacity: 0.45;
  cursor: default;
}

.secondary-button {
  border: 1px solid #d7dce2;
  background: #fff;
  color: #626b76;
}

.warning-button {
  border: 1px solid #d97706;
  background: #d97706;
  color: #fff;
}

.picker-backdrop {
  position: absolute;
  inset: 0;
  z-index: 90;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(15, 23, 42, 0.18);
  backdrop-filter: blur(2px);
}

.calendar-popover,
.time-popover {
  border: 1px solid #dce1e7;
  border-radius: 11px;
  background: #fff;
  box-shadow: 0 18px 45px rgba(0, 0, 0, 0.18);
}

.calendar-popover {
  width: min(330px, 100%);
  padding: 12px;
}

.calendar-header {
  display: grid;
  grid-template-columns: 34px 1fr 34px;
  align-items: center;
  gap: 6px;
}

.calendar-header strong {
  color: #4f5660;
  font-size: 12px;
  text-align: center;
  text-transform: capitalize;
}

.calendar-header button {
  width: 34px;
  height: 30px;
  border: 1px solid #e0e4e8;
  border-radius: 7px;
  background: #f8f9fa;
  color: #66707c;
  cursor: pointer;
}

.calendar-weekdays,
.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 3px;
}

.calendar-weekdays {
  margin-top: 10px;
  color: #9ca3af;
  font-size: 9px;
  text-align: center;
}

.calendar-grid {
  margin-top: 5px;
}

.calendar-grid button {
  height: 34px;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: #4b5563;
  cursor: pointer;
  font-size: 11px;
}

.calendar-grid button:hover:not(:disabled) {
  background: #f0f3f6;
}

.calendar-grid button.today {
  box-shadow: inset 0 0 0 1px #cbd5e1;
}

.calendar-grid button.selected {
  background: #0b2d5c;
  color: #fff;
  font-weight: 700;
}

.time-popover {
  width: min(280px, 100%);
  padding: 15px;
}

.time-title {
  color: #555d67;
  font-size: 12px;
  font-weight: 700;
  text-align: center;
}

.time-selects {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 13px;
}

.time-selects select {
  width: 76px;
  height: 42px;
  border: 1px solid #d7dce2;
  border-radius: 8px;
  background: #fff;
  color: #333b44;
  font-size: 16px;
  text-align: center;
}

.time-actions {
  display: flex;
  justify-content: flex-end;
  gap: 7px;
  margin-top: 13px;
}

@media (max-width: 980px) {
  .add-flight-panel {
    top: 10px;
    right: 10px;
    left: 10px;
    width: auto;
    max-height: calc(100vh - 20px);
  }

  .term-grid {
    grid-template-columns: 1fr 1fr;
  }

  .flight-data-grid {
    grid-template-columns: 1fr 1fr;
  }

  .flight-data-grid .field:last-child {
    grid-column: 1 / -1;
  }
}

@media (max-width: 620px) {
  .add-flight-panel {
    inset: 0;
    width: 100%;
    max-height: 100dvh;
    padding: 12px;
    border: 0;
    border-radius: 0;
  }

  .panel-header {
    padding-bottom: 8px;
  }

  .route-symbol__line {
    width: 14px;
  }

  .route-grid,
  .term-grid,
  .flight-data-grid {
    grid-template-columns: 1fr;
  }

  .route-arrow {
    display: none;
  }

  .flight-data-grid .field:last-child {
    grid-column: auto;
  }

  .option-group {
    grid-template-columns: 1fr;
    gap: 5px;
  }

  .form-actions {
    align-items: flex-end;
    flex-direction: column;
  }

  .time-rule {
    width: 100%;
  }

  .action-buttons {
    width: 100%;
  }

  .action-buttons button {
    flex: 1 1 0;
  }
}
</style>
