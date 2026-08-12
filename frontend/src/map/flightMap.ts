import {
  GeoJSONSource,
  Map as MapLibreMap,
} from 'maplibre-gl'

import {
  greatCircle,
  point,
} from '@turf/turf'

import type {
  AirportData,
  AirportDirectionStat,
  Flight,
  RouteFlight,
  SelectedAirport,
  SelectedRoute,
} from '../types/flight'

import {
  isPlannedFlight,
} from '../utils/flightScope'


const currentFlights =
  new WeakMap<
    MapLibreMap,
    Flight[]
  >()


const currentRoutes =
  new WeakMap<
    MapLibreMap,
    Map<string, SelectedRoute>
  >()


function getAirportKey(
  code: string | null,
  name: string,
): string {
  return `${code ?? ''}|${name}`
}


function getRouteKey(
  flight: Flight,
): string {
  return [
    flight.departure_iata ?? '',
    flight.arrival_iata ?? '',
    flight.departure_airport_name,
    flight.arrival_airport_name,
    isPlannedFlight(flight)
      ? 'planned'
      : 'completed',
  ].join('|')
}


function matchesAirport(
  flightCode: string | null,
  flightName: string,
  selectedCode: string | null,
  selectedName: string,
): boolean {
  if (
    flightCode &&
    selectedCode
  ) {
    return (
      flightCode ===
      selectedCode
    )
  }

  return (
    flightName ===
    selectedName
  )
}


function buildRoute(
  flights: Flight[],
): SelectedRoute {
  const first =
    flights[0]

  if (!first) {
    throw new Error(
      'Brak lotów dla trasy.',
    )
  }


  let totalDistanceKm =
    0

  let totalDurationSeconds =
    0


  const airlines =
    new Map<string, number>()

  const aircraft =
    new Map<string, number>()


  const flightList:
    RouteFlight[] =
    flights.map(
      (flight) => {
        totalDistanceKm +=
          flight.distance_km ??
          0

        totalDurationSeconds +=
          flight.duration_seconds ??
          0


        if (
          flight.airline_name
        ) {
          airlines.set(
            flight.airline_name,

            (
              airlines.get(
                flight.airline_name,
              ) ?? 0
            ) + 1,
          )
        }


        if (
          flight.aircraft_name
        ) {
          aircraft.set(
            flight.aircraft_name,

            (
              aircraft.get(
                flight.aircraft_name,
              ) ?? 0
            ) + 1,
          )
        }


        return {
          id:
            flight.id,

          departureDate:
            flight.departure_date,

          departureTime:
            flight.departure_time,

          arrivalDate:
            flight.arrival_date,

          arrivalTime:
            flight.arrival_time,

          flightNumber:
            flight.flight_number,

          airlineName:
            flight.airline_name,

          aircraftName:
            flight.aircraft_name,

          distanceKm:
            flight.distance_km,

          durationSeconds:
            flight.duration_seconds,

          travelClass:
            flight.travel_class,

          seatType:
            flight.seat_type,

          travelReason:
            flight.travel_reason,
        }
      },
    )


  flightList.sort(
    (a, b) =>
      (
        b.departureDate ??
        ''
      ).localeCompare(
        a.departureDate ??
        '',
      ),
  )


  const dates =
    flightList
      .map(
        (flight) =>
          flight.departureDate,
      )
      .filter(
        (
          value,
        ): value is string =>
          Boolean(value),
      )
      .sort()


  const topAirline =
    [...airlines.entries()]
      .sort(
        (a, b) =>
          b[1] -
          a[1],
      )[0]?.[0] ??
    null


  const topAircraft =
    [...aircraft.entries()]
      .sort(
        (a, b) =>
          b[1] -
          a[1],
      )[0]?.[0] ??
    null


  return {
    departureCode:
      first.departure_iata,

    departureName:
      first.departure_airport_name,

    departureCity:
      first.departure_city,

    departureLongitude:
      Number(
        first.departure_longitude,
      ),

    departureLatitude:
      Number(
        first.departure_latitude,
      ),


    arrivalCode:
      first.arrival_iata,

    arrivalName:
      first.arrival_airport_name,

    arrivalCity:
      first.arrival_city,

    arrivalLongitude:
      Number(
        first.arrival_longitude,
      ),

    arrivalLatitude:
      Number(
        first.arrival_latitude,
      ),


    flights:
      flights.length,

    totalDistanceKm,

    totalDurationSeconds,

    firstFlightDate:
      dates[0] ??
      null,

    lastFlightDate:
      dates[
        dates.length - 1
      ] ??
      null,

    topAirline,

    topAircraft,

    flightList,
  }
}


function buildRoutes(
  flights: Flight[],
): {
  routes: Map<string, SelectedRoute>
  collection: GeoJSON.FeatureCollection
} {
  const groups =
    new Map<
      string,
      Flight[]
    >()


  for (
    const flight
    of flights
  ) {
    const key =
      getRouteKey(
        flight,
      )


    const group =
      groups.get(key)


    if (group) {
      group.push(
        flight,
      )
    } else {
      groups.set(
        key,
        [
          flight,
        ],
      )
    }
  }


  const routes =
    new Map<
      string,
      SelectedRoute
    >()


  const features:
    GeoJSON.Feature[] =
    []


  for (
    const [
      key,
      routeFlights,
    ]
    of groups
  ) {
    const route =
      buildRoute(
        routeFlights,
      )


    routes.set(
      key,
      route,
    )


    const arc =
      greatCircle(
        point([
          route.departureLongitude,
          route.departureLatitude,
        ]),

        point([
          route.arrivalLongitude,
          route.arrivalLatitude,
        ]),

        {
          npoints: 100,
        },
      )


    features.push({
      ...arc,

      properties: {
        routeKey:
          key,

        departure:
          route.departureCode ??
          '',

        arrival:
          route.arrivalCode ??
          '',

        flights:
          route.flights,

        planned:
          isPlannedFlight(
            routeFlights[0]!,
          )
            ? 1
            : 0,
      },
    })
  }


  return {
    routes,

    collection: {
      type:
        'FeatureCollection',

      features,
    },
  }
}


function buildAirports(
  flights: Flight[],
): GeoJSON.FeatureCollection {
  interface AirportAggregate
    extends AirportData {
    completed: number
    planned: number
  }


  const map =
    new Map<
      string,
      AirportAggregate
    >()


  function addAirport(
    key: string,
    airport: AirportData,
    planned: boolean,
  ): void {
    const existing =
      map.get(key)


    if (existing) {
      existing.flights++

      if (planned) {
        existing.planned++
      } else {
        existing.completed++
      }

      return
    }


    map.set(
      key,
      {
        ...airport,

        completed:
          planned ? 0 : 1,

        planned:
          planned ? 1 : 0,
      },
    )
  }


  for (
    const flight
    of flights
  ) {
    const planned =
      isPlannedFlight(
        flight,
      )


    addAirport(
      getAirportKey(
        flight.departure_iata,
        flight.departure_airport_name,
      ),

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

      planned,
    )


    addAirport(
      getAirportKey(
        flight.arrival_iata,
        flight.arrival_airport_name,
      ),

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

      planned,
    )
  }


  return {
    type:
      'FeatureCollection',

    features:
      [...map.values()]
        .map(
          (airport) => ({
            type:
              'Feature',

            geometry: {
              type:
                'Point',

              coordinates: [
                airport.longitude,
                airport.latitude,
              ],
            },

            properties: {
              code:
                airport.code ??
                '',

              name:
                airport.name,

              city:
                airport.city,

              flights:
                airport.flights,

              plannedOnly:
                airport.completed ===
                  0 &&
                airport.planned >
                  0
                  ? 1
                  : 0,
            },
          }),
        ),
  }
}


function buildAirportStats(
  selectedCode: string | null,
  selectedName: string,
  flights: Flight[],
): {
  departures: number
  arrivals: number
  topDestinations: AirportDirectionStat[]
  topOrigins: AirportDirectionStat[]
} {
  const destinations =
    new Map<
      string,
      AirportDirectionStat
    >()


  const origins =
    new Map<
      string,
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
    const departure =
      matchesAirport(
        flight.departure_iata,
        flight.departure_airport_name,
        selectedCode,
        selectedName,
      )


    const arrival =
      matchesAirport(
        flight.arrival_iata,
        flight.arrival_airport_name,
        selectedCode,
        selectedName,
      )


    if (departure) {
      departures++

      const key =
        getAirportKey(
          flight.arrival_iata,
          flight.arrival_airport_name,
        )


      const existing =
        destinations.get(
          key,
        )


      if (existing) {
        existing.flights++
      } else {
        destinations.set(
          key,
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


    if (arrival) {
      arrivals++

      const key =
        getAirportKey(
          flight.departure_iata,
          flight.departure_airport_name,
        )


      const existing =
        origins.get(
          key,
        )


      if (existing) {
        existing.flights++
      } else {
        origins.set(
          key,
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
    departures,

    arrivals,

    topDestinations:
      [...destinations.values()]
        .sort(
          (a, b) =>
            b.flights -
            a.flights,
        )
        .slice(0, 5),

    topOrigins:
      [...origins.values()]
        .sort(
          (a, b) =>
            b.flights -
            a.flights,
        )
        .slice(0, 5),
  }
}


export function addFlightsToMap(
  map: MapLibreMap,
  flights: Flight[],
  onRouteClick?:
    (route: SelectedRoute) => void,
): void {
  currentFlights.set(
    map,
    flights,
  )


  const data =
    buildRoutes(
      flights,
    )


  currentRoutes.set(
    map,
    data.routes,
  )


  map.addSource(
    'flights',
    {
      type:
        'geojson',

      data:
        data.collection,
    },
  )


  map.addLayer({
    id:
      'flights',

    type:
      'line',

    source:
      'flights',

    layout: {
      'line-cap':
        'round',

      'line-join':
        'round',
    },

    paint: {
      'line-color': [
        'case',

        [
          '==',
          ['get', 'planned'],
          1,
        ],

        '#f28c28',

        '#d62828',
      ],

      'line-width': [
        'interpolate',
        ['linear'],
        ['get', 'flights'],

        1,
        1.6,

        5,
        1.9,

        10,
        2.3,

        20,
        3,
      ],

      'line-opacity': [
        'interpolate',
        ['linear'],
        ['get', 'flights'],

        1,
        0.5,

        5,
        0.6,

        10,
        0.7,

        20,
        0.8,
      ],
    },
  })


  map.addLayer({
    id:
      'flight-highlight',

    type:
      'line',

    source:
      'flights',

    filter: [
      '==',
      ['get', 'routeKey'],
      '__NONE__',
    ],

    layout: {
      'line-cap':
        'round',

      'line-join':
        'round',
    },

    paint: {
      'line-color':
        '#0b2d5c',

      'line-width':
        8,

      'line-opacity':
        0.98,
    },
  })


  map.on(
    'mouseenter',
    'flights',
    () => {
      map.getCanvas()
        .style.cursor =
        'pointer'
    },
  )


  map.on(
    'mouseleave',
    'flights',
    () => {
      map.getCanvas()
        .style.cursor =
        ''
    },
  )


  map.on(
    'click',
    'flights',
    (event) => {
      const feature =
        event.features?.[0]


      const key =
        String(
          feature?.properties
            ?.routeKey ??
          '',
        )


      const route =
        currentRoutes
          .get(map)
          ?.get(key)


      if (!route) {
        return
      }


      highlightRoute(
        map,

        route.departureCode,
        route.arrivalCode,

        route.departureLongitude,
        route.departureLatitude,

        route.arrivalLongitude,
        route.arrivalLatitude,

        key,
      )


      onRouteClick?.(
        route,
      )
    },
  )
}


export async function addAirportsToMap(
  map: MapLibreMap,
  flights: Flight[],
  onAirportClick?:
    (airport: SelectedAirport) => void,
): Promise<void> {
  currentFlights.set(
    map,
    flights,
  )


  map.addSource(
    'airports',
    {
      type:
        'geojson',

      data:
        buildAirports(
          flights,
        ),
    },
  )


  map.addLayer({
    id:
      'airports',

    type:
      'circle',

    source:
      'airports',

    paint: {
      'circle-radius': [
        'interpolate',
        ['linear'],
        ['get', 'flights'],

        1,
        2.5,

        10,
        3.5,

        50,
        5,

        150,
        7,
      ],

      'circle-color':
        '#ffffff',

      'circle-stroke-color': [
        'case',

        [
          '==',
          ['get', 'plannedOnly'],
          1,
        ],

        '#f28c28',

        '#d62828',
      ],

      'circle-stroke-width':
        1.5,

      'circle-opacity':
        0.95,
    },
  })


  map.on(
    'mouseenter',
    'airports',
    () => {
      map.getCanvas()
        .style.cursor =
        'pointer'
    },
  )


  map.on(
    'mouseleave',
    'airports',
    () => {
      map.getCanvas()
        .style.cursor =
        ''
    },
  )


  map.on(
    'click',
    'airports',
    (event) => {
      const feature =
        event.features?.[0]


      if (
        !feature?.properties ||
        feature.geometry.type !==
          'Point'
      ) {
        return
      }


      const geometry =
        feature.geometry as
          GeoJSON.Point


      const codeValue =
        String(
          feature.properties.code ??
          '',
        )


      const code =
        codeValue ||
        null


      const name =
        String(
          feature.properties.name ??
          '',
        )


      const city =
        String(
          feature.properties.city ??
          '',
        )


      const flightsNow =
        currentFlights.get(
          map,
        ) ??
        []


      const stats =
        buildAirportStats(
          code,
          name,
          flightsNow,
        )


      onAirportClick?.({
        code,
        name,
        city,

        longitude:
          Number(
            geometry.coordinates[0],
          ),

        latitude:
          Number(
            geometry.coordinates[1],
          ),

        flights:
          Number(
            feature.properties.flights ??
            0,
          ),

        departures:
          stats.departures,

        arrivals:
          stats.arrivals,

        topDestinations:
          stats.topDestinations,

        topOrigins:
          stats.topOrigins,
      })
    },
  )
}


export function updateFlightMapData(
  map: MapLibreMap,
  flights: Flight[],
): void {
  currentFlights.set(
    map,
    flights,
  )


  const routes =
    buildRoutes(
      flights,
    )


  currentRoutes.set(
    map,
    routes.routes,
  )


  const flightSource =
    map.getSource(
      'flights',
    ) as
      GeoJSONSource |
      undefined


  flightSource?.setData(
    routes.collection,
  )


  const airportSource =
    map.getSource(
      'airports',
    ) as
      GeoJSONSource |
      undefined


  airportSource?.setData(
    buildAirports(
      flights,
    ),
  )


  clearHighlightedRoute(
    map,
  )
}


export function highlightRoute(
  map: MapLibreMap,

  departureCode:
    string | null,

  arrivalCode:
    string | null,

  departureLongitude:
    number,

  departureLatitude:
    number,

  arrivalLongitude:
    number,

  arrivalLatitude:
    number,

  routeKey?: string,
): void {
  if (
    !map.getLayer(
      'flight-highlight',
    )
  ) {
    return
  }


  if (routeKey) {
    map.setFilter(
      'flight-highlight',

      [
        '==',
        ['get', 'routeKey'],
        routeKey,
      ],
    )
  } else {
    map.setFilter(
      'flight-highlight',

      [
        'all',

        [
          '==',
          ['get', 'departure'],
          departureCode ??
          '',
        ],

        [
          '==',
          ['get', 'arrival'],
          arrivalCode ??
          '',
        ],
      ],
    )
  }


  map.fitBounds(
    [
      [
        Math.min(
          departureLongitude,
          arrivalLongitude,
        ),

        Math.min(
          departureLatitude,
          arrivalLatitude,
        ),
      ],

      [
        Math.max(
          departureLongitude,
          arrivalLongitude,
        ),

        Math.max(
          departureLatitude,
          arrivalLatitude,
        ),
      ],
    ],

    {
      padding: {
        top: 100,
        right: 410,
        bottom: 100,
        left: 420,
      },

      maxZoom: 6,

      duration: 900,
    },
  )
}


export function clearHighlightedRoute(
  map: MapLibreMap,
): void {
  if (
    !map.getLayer(
      'flight-highlight',
    )
  ) {
    return
  }


  map.setFilter(
    'flight-highlight',

    [
      '==',
      ['get', 'routeKey'],
      '__NONE__',
    ],
  )
}