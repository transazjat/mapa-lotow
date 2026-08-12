import type {
  Flight,
  FlightScope,
} from '../types/flight'


export function getTodayString(): string {
  const now =
    new Date()

  const year =
    now.getFullYear()

  const month =
    String(
      now.getMonth() + 1,
    ).padStart(
      2,
      '0',
    )

  const day =
    String(
      now.getDate(),
    ).padStart(
      2,
      '0',
    )

  return `${year}-${month}-${day}`
}


export function isPlannedDate(
  departureDate: string | null,
): boolean {
  if (!departureDate) {
    return false
  }

  return (
    departureDate >
    getTodayString()
  )
}


export function isPlannedFlight(
  flight: Flight,
): boolean {
  return isPlannedDate(
    flight.departure_date,
  )
}


export function filterFlightsByScope(
  flights: Flight[],
  scope: FlightScope,
): Flight[] {
  if (
    scope === 'all'
  ) {
    return flights
  }

  if (
    scope === 'planned'
  ) {
    return flights.filter(
      isPlannedFlight,
    )
  }

  return flights.filter(
    (flight) =>
      !isPlannedFlight(
        flight,
      ),
  )
}