export type AchievementStatus =
  | 'active'
  | 'inactive'
  | 'locked'

export type AchievementFamily =
  | 'flights'
  | 'distance'
  | 'airports'
  | 'countries'
  | 'continents'
  | 'airlines'
  | 'aircraft'

export interface AchievementItem {
  key: string
  family: AchievementFamily
  threshold: number
  status: AchievementStatus
  earned: boolean
  active: boolean
  earned_at: string | null
  notified_at: string | null
}

export interface AchievementSummary {
  earned_count: number
  active_count: number
  last_earned: AchievementItem | null
  next_threshold: number | null
  remaining_to_next: number | null
}

export interface DistanceAchievementState {
  completed_distance_km: number
  achievements: AchievementItem[]
  pending_unlocks: AchievementItem[]
  summary: AchievementSummary
}

export interface AirportAchievementState {
  completed_airports: number
  achievements: AchievementItem[]
  pending_unlocks: AchievementItem[]
  summary: AchievementSummary
}

export interface CountryAchievementState {
  completed_countries: number
  achievements: AchievementItem[]
  pending_unlocks: AchievementItem[]
  summary: AchievementSummary
}

export interface ContinentAchievementState {
  completed_continents: number
  achievements: AchievementItem[]
  pending_unlocks: AchievementItem[]
  summary: AchievementSummary
}

export interface AirlineAchievementState {
  completed_airlines: number
  achievements: AchievementItem[]
  pending_unlocks: AchievementItem[]
  summary: AchievementSummary
}

export interface AircraftCollectionAchievementItem {
  key: string
  label: string
  slug: string
  order: number
  status: AchievementStatus
  earned: boolean
  active: boolean
  earned_at: string | null
  notified_at: string | null
}

export interface AircraftCollectionAchievementState {
  achievements: AircraftCollectionAchievementItem[]
  pending_unlocks: AircraftCollectionAchievementItem[]
  summary: {
    earned_count: number
    active_count: number
    total_count: number
    last_earned: AircraftCollectionAchievementItem | null
  }
}

export interface AircraftAchievementState {
  completed_aircraft_types: number
  achievements: AchievementItem[]
  pending_unlocks: AchievementItem[]
  summary: AchievementSummary
}

export interface AchievementsResponse {
  status: 'ok'
  completed_flights: number
  achievements: AchievementItem[]
  pending_unlocks: AchievementItem[]
  summary: AchievementSummary
  distance: DistanceAchievementState
  airports: AirportAchievementState
  countries: CountryAchievementState
  continents: ContinentAchievementState
  airlines: AirlineAchievementState
  aircraft: AircraftAchievementState
  aircraft_manufacturers: AircraftCollectionAchievementState
  aircraft_origins: AircraftCollectionAchievementState
  aircraft_unique: AircraftCollectionAchievementState
}

export interface AchievementSyncResponse extends AchievementsResponse {
  newly_earned: (AchievementItem | AircraftCollectionAchievementItem)[]
}
