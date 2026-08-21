export interface AdminDashboardUsers {
  total: number
  active: number
  inactive: number
  verified: number
  unverified: number
  admins: number
  new_7: number
  new_30: number
}

export interface AdminDashboardFlights {
  total: number
  completed: number
  planned: number
  distance_km: number
  duration_seconds: number
  users_with_flights: number
}

export interface AdminRecentUser {
  id: number
  nick: string
  email: string
  is_active: number | boolean
  email_verified_at: string | null
  created_at: string
  last_login_at: string | null
}

export interface AdminRecentFlight {
  id: number
  user_id: number
  departure_date: string
  departure_time: string | null
  flight_number: string | null
  user_nick: string
  departure_iata: string | null
  arrival_iata: string | null
  airline_name: string | null
}

export interface AdminDashboardResponse {
  status: 'ok'
  users: AdminDashboardUsers
  flights: AdminDashboardFlights
  recent_users: AdminRecentUser[]
  recent_flights: AdminRecentFlight[]
}

export interface AdminUserListItem {
  id: number
  nick: string
  email: string
  is_active: number | boolean
  is_admin: number | boolean
  email_verified_at: string | null
  privacy_mode: 'private' | 'link' | 'public'
  created_at: string
  last_login_at: string | null
  flights_count: number | string
  distance_km: number | string
  duration_seconds: number | string
}

export interface AdminUserDetail
  extends AdminUserListItem {
  public_slug: string | null
  updated_at: string | null
  planned_count: number | string | null
}

export interface AdminUsersResponse {
  status: 'ok'
  page: number
  per_page: number
  total: number
  pages: number
  users: AdminUserListItem[]
}

export interface AdminUserResponse {
  status: 'ok'
  user: AdminUserDetail
  recent_flights: AdminRecentFlight[]
  is_current_admin: boolean
}

export interface AdminFlightListItem {
  id: number
  user_id: number
  departure_date: string
  departure_time: string | null
  arrival_date: string | null
  arrival_time: string | null
  flight_number: string | null
  distance_km: number | null
  duration_seconds: number | null
  travel_class: string | null
  seat_type: string | null
  travel_reason: string | null
  notes: string | null
  user_nick: string
  user_email: string
  departure_iata: string | null
  departure_airport: string
  departure_city: string | null
  arrival_iata: string | null
  arrival_airport: string
  arrival_city: string | null
  airline_name: string | null
  airline_iata: string | null
  aircraft_name: string | null
}

export interface AdminFlightsResponse {
  status: 'ok'
  page: number
  per_page: number
  total: number
  pages: number
  flights: AdminFlightListItem[]
}

export interface AdminFlightResponse {
  status: 'ok'
  flight: Record<string, unknown>
}

export interface AdminActionResponse {
  status: 'ok' | 'error'
  message?: string
}
