export interface FlightTypesStats {
  domestic: number
  continental: number
  intercontinental: number
  other: number
}

export interface UserStats {
  flights: number
  distance_km: number
  duration_seconds: number
  airports: number
  airlines: number
  aircraft_types: number
  routes: number
  countries: number
  flight_types: FlightTypesStats
}

export interface UserOverviewResponse {
  status: string
  user_id: number
  stats: UserStats
}