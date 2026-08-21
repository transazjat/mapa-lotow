import type {
  AdminActionResponse,
  AdminDashboardResponse,
  AdminFlightResponse,
  AdminFlightsResponse,
  AdminUserResponse,
  AdminUsersResponse,
} from '../types/admin'


type QueryValue =
  | string
  | number
  | null
  | undefined


function queryString(
  values: Record<string, QueryValue>,
): string {
  const params =
    new URLSearchParams()

  for (
    const [key, value]
    of Object.entries(values)
  ) {
    if (
      value === null
      || value === undefined
      || value === ''
    ) {
      continue
    }

    params.set(
      key,
      String(value),
    )
  }

  const result =
    params.toString()

  return result
    ? `?${result}`
    : ''
}


async function adminRequest<T>(
  url: string,
  options: RequestInit = {},
): Promise<T> {
  const headers =
    new Headers(
      options.headers,
    )

  if (
    options.body
    && !headers.has('Content-Type')
  ) {
    headers.set(
      'Content-Type',
      'application/json',
    )
  }

  const response =
    await fetch(
      url,
      {
        ...options,
        credentials:
          'same-origin',
        headers,
      },
    )

  let data:
    | Record<string, unknown>
    | null = null

  try {
    data =
      await response.json()
  } catch {
    // Celowo - obsługa komunikatu poniżej.
  }

  if (!response.ok) {
    throw new Error(
      typeof data?.message === 'string'
        ? data.message
        : `Błąd API: ${response.status}`,
    )
  }

  return data as T
}


export async function getAdminDashboard():
Promise<AdminDashboardResponse> {
  return await adminRequest<
    AdminDashboardResponse
  >(
    '/api/admin/dashboard',
  )
}


export async function getAdminUsers(
  options: {
    q?: string
    status?: string
    page?: number
    perPage?: number
  } = {},
): Promise<AdminUsersResponse> {
  return await adminRequest<
    AdminUsersResponse
  >(
    '/api/admin/users'
    + queryString({
      q: options.q,
      status: options.status,
      page: options.page,
      per_page:
        options.perPage,
    }),
  )
}


export async function getAdminUser(
  id: number,
): Promise<AdminUserResponse> {
  return await adminRequest<
    AdminUserResponse
  >(
    `/api/admin/users/${id}`,
  )
}


export async function updateAdminUser(
  id: number,
  values: {
    is_active?: boolean
    is_admin?: boolean
  },
): Promise<AdminActionResponse> {
  return await adminRequest<
    AdminActionResponse
  >(
    `/api/admin/users/${id}`,
    {
      method: 'PUT',
      body: JSON.stringify(
        values,
      ),
    },
  )
}


export async function getAdminFlights(
  options: {
    q?: string
    scope?: string
    userId?: number | null
    dateFrom?: string
    dateTo?: string
    page?: number
    perPage?: number
  } = {},
): Promise<AdminFlightsResponse> {
  return await adminRequest<
    AdminFlightsResponse
  >(
    '/api/admin/flights'
    + queryString({
      q: options.q,
      scope: options.scope,
      user_id:
        options.userId,
      date_from:
        options.dateFrom,
      date_to:
        options.dateTo,
      page:
        options.page,
      per_page:
        options.perPage,
    }),
  )
}


export async function getAdminFlight(
  id: number,
): Promise<AdminFlightResponse> {
  return await adminRequest<
    AdminFlightResponse
  >(
    `/api/admin/flights/${id}`,
  )
}


export async function deleteAdminFlight(
  id: number,
): Promise<AdminActionResponse> {
  return await adminRequest<
    AdminActionResponse
  >(
    `/api/admin/flights/${id}`,
    {
      method: 'DELETE',
    },
  )
}
