export type AchievementStatus =
  | 'active'
  | 'inactive'
  | 'locked'

export interface AchievementItem {
  key: string
  family: 'flights'
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

export interface AchievementsResponse {
  status: 'ok'
  completed_flights: number
  achievements: AchievementItem[]
  pending_unlocks: AchievementItem[]
  summary: AchievementSummary
}

export interface AchievementSyncResponse
  extends AchievementsResponse {
  newly_earned: AchievementItem[]
}
