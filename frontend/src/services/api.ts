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

import type {
  AccountUser,
  AuthActionResponse,
  AuthStateResponse,
  PrivacyMode,
  PublicMapResponse,
} from '../types/account'


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


export async function getUserFlights():
Promise<FlightsResponse> {
  const response =
    await fetch(
      '/api/flights',
      {
        credentials:
          'same-origin',
      },
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
      {
        credentials:
          'same-origin',
      },
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

        credentials:
          'same-origin',

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

        credentials:
          'same-origin',

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
): Promise<DeleteFlightResponse> {
  const response =
    await fetch(
      `/api/flights/${flightId}`,
      {
        method:
          'DELETE',

        credentials:
          'same-origin',

        headers: {
          'Content-Type':
            'application/json',
        },
      },
    )

  return await readJson<
    DeleteFlightResponse
  >(
    response,
  )
}




async function accountRequest<T>(
  url: string,
  options: RequestInit = {},
): Promise<T> {
  const response =
    await fetch(
      url,
      {
        credentials:
          'same-origin',
        ...options,
        headers: {
          Accept:
            'application/json',
          ...(options.body
            ? {
                'Content-Type':
                  'application/json',
              }
            : {}),
          ...(options.headers ?? {}),
        },
      },
    )

  const data =
    await response.json()

  if (!response.ok) {
    const error =
      new Error(
        data?.message ??
        `Błąd API: ${response.status}`,
      ) as Error & {
        captcha_required?: boolean
        existing_account?: boolean
        field?: string
      }

    error.captcha_required =
      Boolean(
        data?.captcha_required,
      )

    error.existing_account =
      Boolean(
        data?.existing_account,
      )

    error.field =
      typeof data?.field === 'string'
        ? data.field
        : undefined

    throw error
  }

  return data as T
}


export async function getAuthState():
Promise<AuthStateResponse> {
  return await accountRequest<
    AuthStateResponse
  >(
    '/api/auth/me',
  )
}


export async function loginAccount(
  email: string,
  password: string,
  remember: boolean,
): Promise<AuthActionResponse> {
  return await accountRequest<
    AuthActionResponse
  >(
    '/api/auth/login',
    {
      method: 'POST',
      body: JSON.stringify({
        email,
        password,
        remember,
      }),
    },
  )
}


export async function registerAccount(
  email: string,
  nick: string,
  password: string,
  passwordRepeat: string,
): Promise<AuthActionResponse> {
  return await accountRequest<
    AuthActionResponse
  >(
    '/api/auth/register',
    {
      method: 'POST',
      body: JSON.stringify({
        email,
        nick,
        password,
        password_repeat:
          passwordRepeat,
      }),
    },
  )
}


export async function activateAccount(
  token: string,
): Promise<AuthActionResponse> {
  return await accountRequest<
    AuthActionResponse
  >(
    '/api/auth/activate',
    {
      method: 'POST',
      body: JSON.stringify({
        token,
      }),
    },
  )
}


export async function resendActivation(
  email: string,
): Promise<AuthActionResponse> {
  return await accountRequest<
    AuthActionResponse
  >(
    '/api/auth/resend-activation',
    {
      method: 'POST',
      body: JSON.stringify({
        email,
      }),
    },
  )
}


export async function requestPasswordReset(
  email: string,
): Promise<AuthActionResponse> {
  return await accountRequest<
    AuthActionResponse
  >(
    '/api/auth/forgot-password',
    {
      method: 'POST',
      body: JSON.stringify({
        email,
      }),
    },
  )
}


export async function resetPassword(
  token: string,
  password: string,
  passwordRepeat: string,
): Promise<AuthActionResponse> {
  return await accountRequest<
    AuthActionResponse
  >(
    '/api/auth/reset-password',
    {
      method: 'POST',
      body: JSON.stringify({
        token,
        password,
        password_repeat:
          passwordRepeat,
      }),
    },
  )
}


export async function logoutAccount():
Promise<AuthActionResponse> {
  return await accountRequest<
    AuthActionResponse
  >(
    '/api/auth/logout',
    {
      method: 'POST',
    },
  )
}


export async function updateAccountProfile(
  nick: string,
): Promise<{
  status: 'ok'
  user: AccountUser
}> {
  return await accountRequest(
    '/api/account/profile',
    {
      method: 'PUT',
      body: JSON.stringify({
        nick,
      }),
    },
  )
}


export async function updateAccountPrivacy(
  privacyMode: PrivacyMode,
): Promise<{
  status: 'ok'
  user: AccountUser
}> {
  return await accountRequest(
    '/api/account/privacy',
    {
      method: 'PUT',
      body: JSON.stringify({
        privacy_mode:
          privacyMode,
      }),
    },
  )
}


export async function regenerateShareLink():
Promise<{
  status: 'ok'
  user: AccountUser
}> {
  return await accountRequest(
    '/api/account/share-link/regenerate',
    {
      method: 'POST',
    },
  )
}


export async function changeAccountPassword(
  currentPassword: string,
  password: string,
  passwordRepeat: string,
): Promise<AuthActionResponse> {
  return await accountRequest<
    AuthActionResponse
  >(
    '/api/account/password',
    {
      method: 'PUT',
      body: JSON.stringify({
        current_password:
          currentPassword,
        password,
        password_repeat:
          passwordRepeat,
      }),
    },
  )
}


export async function requestEmailChange(
  currentPassword: string,
  newEmail: string,
): Promise<AuthActionResponse> {
  return await accountRequest<
    AuthActionResponse
  >(
    '/api/account/email',
    {
      method: 'POST',
      body: JSON.stringify({
        current_password:
          currentPassword,
        email: newEmail,
      }),
    },
  )
}


export async function confirmEmailChange(
  token: string,
): Promise<AuthActionResponse> {
  return await accountRequest<
    AuthActionResponse
  >(
    '/api/account/email/confirm',
    {
      method: 'POST',
      body: JSON.stringify({
        token,
      }),
    },
  )
}


export async function downloadAccountExport(
  format: 'csv' | 'xlsx' | 'json',
): Promise<void> {
  const response =
    await fetch(
      `/api/account/export/${format}`,
      {
        credentials:
          'same-origin',
      },
    )

  if (!response.ok) {
    let message =
      `Nie udało się pobrać eksportu: ${response.status}`

    try {
      const data =
        await response.json()

      if (data?.message) {
        message =
          data.message
      }
    } catch {
      // odpowiedź nie musi być JSON-em
    }

    throw new Error(message)
  }

  const blob =
    await response.blob()

  const disposition =
    response.headers.get(
      'content-disposition',
    ) ?? ''

  const match =
    disposition.match(
      /filename="?([^";]+)"?/i,
    )

  const filename =
    match?.[1] ??
    `mapa-lotow-eksport.${format}`

  const url =
    URL.createObjectURL(
      blob,
    )

  const link =
    document.createElement(
      'a',
    )

  link.href =
    url

  link.download =
    filename

  document.body.appendChild(
    link,
  )

  link.click()
  link.remove()

  URL.revokeObjectURL(
    url,
  )
}


export async function getPublicProfile(
  slug: string,
): Promise<PublicMapResponse> {
  return await accountRequest<
    PublicMapResponse
  >(
    `/api/public/profile/${encodeURIComponent(slug)}`,
  )
}


export async function getSharedMap(
  token: string,
): Promise<PublicMapResponse> {
  return await accountRequest<
    PublicMapResponse
  >(
    `/api/shared/map/${encodeURIComponent(token)}`,
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
