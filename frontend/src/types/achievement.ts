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
  | 'routes'
  | 'duration'
  | 'astronomical'
  | 'intensity_year'
  | 'intensity_month'
  | 'intensity_streak'
  | 'intensity_day'
  | 'special'

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

export interface RouteAchievementState {
  completed_routes: number
  achievements: AchievementItem[]
  pending_unlocks: AchievementItem[]
  summary: AchievementSummary
}

export interface DurationAchievementState {
  completed_hours: number
  achievements: AchievementItem[]
  pending_unlocks: AchievementItem[]
  summary: AchievementSummary
}


export interface AstronomicalAchievementState {
  completed_distance_km: number
  achievements: AchievementItem[]
  pending_unlocks: AchievementItem[]
  summary: AchievementSummary
}


export interface IntensityRecord {
  value: number
  label: string
  start_date: string | null
  end_date: string | null
}

export interface IntensitySubState {
  completed_value: number
  achievements: AchievementItem[]
  pending_unlocks: AchievementItem[]
  summary: AchievementSummary
  record: IntensityRecord
}

export interface IntensityAchievementState {
  year: IntensitySubState
  month: IntensitySubState
  streak: IntensitySubState
  day: IntensitySubState
}


export interface SpecialAchievementDetail {
  label: string
  value: string
  note?: string | null
}

export interface SpecialAchievementItem {
  key: string
  label: string
  slug: string
  order: number
  category: 'calendar' | 'history' | 'repeat' | 'geography'
  description: string
  status: AchievementStatus
  earned: boolean
  active: boolean
  earned_at: string | null
  notified_at: string | null
  details: SpecialAchievementDetail[]
}

export interface SpecialAchievementState {
  achievements: SpecialAchievementItem[]
  pending_unlocks: SpecialAchievementItem[]
  summary: {
    earned_count: number
    active_count: number
    total_count: number
    last_earned: SpecialAchievementItem | null
  }
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
  routes: RouteAchievementState
  duration: DurationAchievementState
  astronomical: AstronomicalAchievementState
  intensity: IntensityAchievementState
  special: SpecialAchievementState
  aircraft_manufacturers: AircraftCollectionAchievementState
  aircraft_origins: AircraftCollectionAchievementState
  aircraft_unique: AircraftCollectionAchievementState
}

export interface AchievementSyncResponse extends AchievementsResponse {
  newly_earned: (AchievementItem | AircraftCollectionAchievementItem | SpecialAchievementItem)[]
}
