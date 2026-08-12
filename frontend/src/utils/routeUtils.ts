import type {
  Flight,
  RouteFlight,
  SelectedRoute,
} from '../types/flight'


function airportMatches(
  codeA: string | null,
  nameA: string,
  codeB: string | null,
  nameB: string,
): boolean {
  if (
    codeA &&
    codeB
  ) {
    return (
      codeA ===
      codeB
    )
  }

  return (
    nameA ===
    nameB
  )
}


export function buildSelectedRoute(
  allFlights: Flight[],

  departureCode:
    string | null,

  departureName:
    string,

  arrivalCode:
    string | null,

  arrivalName:
    string,
): SelectedRoute | null {
  const flights =
    allFlights.filter(
      (flight) =>
        airportMatches(
          flight.departure_iata,
          flight.departure_airport_name,

          departureCode,
          departureName,
        ) &&

        airportMatches(
          flight.arrival_iata,
          flight.arrival_airport_name,

          arrivalCode,
          arrivalName,
        ),
    )


  const firstFlight =
    flights[0]


  if (!firstFlight) {
    return null
  }


  let totalDistanceKm =
    0

  let totalDurationSeconds =
    0


  const airlineCounts =
    new Map<
      string,
      number
    >()


  const aircraftCounts =
    new Map<
      string,
      number
    >()


  const flightList:
    RouteFlight[] =
    flights.map(
      (flight) => {
        if (
          flight.distance_km !==
          null
        ) {
          totalDistanceKm +=
            Number(
              flight.distance_km,
            )
        }


        if (
          flight.duration_seconds !==
          null
        ) {
          totalDurationSeconds +=
            Number(
              flight.duration_seconds,
            )
        }


        if (
          flight.airline_name
        ) {
          airlineCounts.set(
            flight.airline_name,

            (
              airlineCounts.get(
                flight.airline_name,
              ) ?? 0
            ) + 1,
          )
        }


        if (
          flight.aircraft_name
        ) {
          aircraftCounts.set(
            flight.aircraft_name,

            (
              aircraftCounts.get(
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
    (a, b) => {
      const aValue =
        `${a.departureDate ?? ''} ${a.departureTime ?? ''}`

      const bValue =
        `${b.departureDate ?? ''} ${b.departureTime ?? ''}`

      return bValue.localeCompare(
        aValue,
      )
    },
  )


  const dates =
    flightList
      .map(
        (flight) =>
          flight.departureDate,
      )
      .filter(
        (
          date,
        ): date is string =>
          Boolean(date),
      )
      .sort()


  const topAirline =
    [...airlineCounts.entries()]
      .sort(
        (a, b) =>
          b[1] - a[1],
      )[0]?.[0] ??
    null


  const topAircraft =
    [...aircraftCounts.entries()]
      .sort(
        (a, b) =>
          b[1] - a[1],
      )[0]?.[0] ??
    null


  return {
    departureCode:
      firstFlight.departure_iata,

    departureName:
      firstFlight.departure_airport_name,

    departureCity:
      firstFlight.departure_city,

    departureCountryCode:
      firstFlight.departure_country_code,

    departureLongitude:
      Number(
        firstFlight.departure_longitude,
      ),

    departureLatitude:
      Number(
        firstFlight.departure_latitude,
      ),


    arrivalCode:
      firstFlight.arrival_iata,

    arrivalName:
      firstFlight.arrival_airport_name,

    arrivalCity:
      firstFlight.arrival_city,

    arrivalCountryCode:
      firstFlight.arrival_country_code,

    arrivalLongitude:
      Number(
        firstFlight.arrival_longitude,
      ),

    arrivalLatitude:
      Number(
        firstFlight.arrival_latitude,
      ),


    flights:
      flights.length,

    totalDistanceKm,

    totalDurationSeconds,

    firstFlightDate:
      dates[0] ?? null,

    lastFlightDate:
      dates[
        dates.length - 1
      ] ?? null,

    topAirline,

    topAircraft,

    flightList,
  }
}