import type {
  AircraftTypeSearchItem,
  AirlineSearchItem,
  AirportSearchItem,
  CreateFlightPayload,
  CreateFlightResponse,
  DeleteFlightResponse,
  FlightDetailsResponse,
  FlightsResponse,
  SearchResponse,
} from '../types/flight'

import type {
  UserOverviewResponse,
} from '../types/overview'


async function readJson<T>(
  response: Response,
): Promise<T> {
  const contentType =
    response.headers.get(
      'content-type',
    ) ?? ''

  if (
    !contentType.includes(
      'application/json',
    )
  ) {
    const text =
      await response.text()

    throw new Error(
      `Błąd API ${response.status}: ${text.slice(0, 180)}`,
    )
  }

  const data =
    await response.json()

  if (
    !response.ok &&
    response.status !==
      409
  ) {
    throw new Error(
      data?.message ??
      `Błąd API: ${response.status}`,
    )
  }

  return data
}


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


export async function searchAirports(
  query: string,
): Promise<AirportSearchItem[]> {
  const response =
    await fetch(
      `/api/airports/search?q=${encodeURIComponent(query)}`,
    )

  const data =
    await readJson<
      SearchResponse<AirportSearchItem>
    >(
      response,
    )

  return data.items
}


export async function searchAirlines(
  query: string,
): Promise<AirlineSearchItem[]> {
  const response =
    await fetch(
      `/api/airlines/search?q=${encodeURIComponent(query)}`,
    )

  const data =
    await readJson<
      SearchResponse<AirlineSearchItem>
    >(
      response,
    )

  return data.items
}


export async function searchAircraftTypes(
  query: string,
): Promise<AircraftTypeSearchItem[]> {
  const response =
    await fetch(
      `/api/aircraft-types/search?q=${encodeURIComponent(query)}`,
    )

  const data =
    await readJson<
      SearchResponse<AircraftTypeSearchItem>
    >(
      response,
    )

  return data.items
}


export async function createFlight(
  payload: CreateFlightPayload,
): Promise<CreateFlightResponse> {
  const response =
    await fetch(
      '/api/flights',
      {
        method:
          'POST',

        headers: {
          'Content-Type':
            'application/json',
        },

        body:
          JSON.stringify(
            payload,
          ),
      },
    )

  return await readJson<
    CreateFlightResponse
  >(
    response,
  )
}



export async function updateFlight(
  flightId: number,
  payload: CreateFlightPayload,
): Promise<CreateFlightResponse> {
  const response =
    await fetch(
      `/api/flights/${flightId}`,
      {
        method:
          'PUT',

        headers: {
          'Content-Type':
            'application/json',
        },

        body:
          JSON.stringify(
            payload,
          ),
      },
    )

  return await readJson<
    CreateFlightResponse
  >(
    response,
  )
}


export async function deleteFlight(
  flightId: number,
  userId: number,
): Promise<DeleteFlightResponse> {
  const response =
    await fetch(
      `/api/flights/${flightId}`,
      {
        method:
          'DELETE',

        headers: {
          'Content-Type':
            'application/json',
        },

        body:
          JSON.stringify({
            user_id:
              userId,
          }),
      },
    )

  return await readJson<
    DeleteFlightResponse
  >(
    response,
  )
}



export interface TransAzjaOffer {
  id: string
  title: string
  days: number
  date_text: string
  status:
    | 'zapisy'
    | 'potwierdzony'
    | 'promocja'
  image: string | null
  url: string
}


interface TransAzjaOffersResponse {
  status: 'ok'
  source: string
  offers: TransAzjaOffer[]
  warning?: string
}


export async function getTransAzjaOffers():
Promise<TransAzjaOffer[]> {
  const response =
    await fetch(
      '/api/transazja/offers',
      {
        headers: {
          Accept:
            'application/json',
        },
      },
    )

  const data =
    await readJson<
      TransAzjaOffersResponse
    >(
      response,
    )

  return Array.isArray(
    data.offers,
  )
    ? data.offers
    : []
}
