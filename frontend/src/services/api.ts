import type {
  FlightDetailsResponse,
  FlightsResponse,
} from '../types/flight'

import type {
  UserOverviewResponse,
} from '../types/overview'


export async function getUserFlights(
  userId: number,
): Promise<FlightsResponse> {
  const response =
    await fetch(
      `/api/flights?user_id=${userId}`,
    )

  if (!response.ok) {
    throw new Error(
      `Błąd pobierania lotów: ${response.status}`,
    )
  }

  return await response.json()
}


export async function getFlight(
  flightId: number,
): Promise<FlightDetailsResponse> {
  const response =
    await fetch(
      `/api/flights/${flightId}`,
    )

  if (!response.ok) {
    throw new Error(
      `Błąd pobierania lotu: ${response.status}`,
    )
  }

  return await response.json()
}


export async function getUserOverview(
  userId: number,
): Promise<UserOverviewResponse> {
  const response =
    await fetch(
      `/api/users/${userId}/overview`,
    )

  if (!response.ok) {
    throw new Error(
      `Błąd pobierania statystyk: ${response.status}`,
    )
  }

  return await response.json()
}