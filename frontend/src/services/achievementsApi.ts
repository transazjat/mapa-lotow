import type {
  AchievementSyncResponse,
  AchievementsResponse,
} from '../types/achievement'

async function readJson<T>(
  response: Response,
): Promise<T> {
  const data = await response.json()

  if (!response.ok) {
    throw new Error(
      data?.message ??
        `Błąd API: ${response.status}`,
    )
  }

  return data as T
}

export async function getAchievements(): Promise<AchievementsResponse> {
  const response = await fetch(
    '/api/achievements',
    {
      credentials: 'same-origin',
    },
  )

  return await readJson<AchievementsResponse>(
    response,
  )
}

export async function syncAchievements(): Promise<AchievementSyncResponse> {
  const response = await fetch(
    '/api/achievements/sync',
    {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({}),
    },
  )

  return await readJson<AchievementSyncResponse>(
    response,
  )
}

export async function markAchievementsNotified(
  keys: string[],
): Promise<void> {
  if (keys.length === 0) {
    return
  }

  const response = await fetch(
    '/api/achievements/mark-notified',
    {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ keys }),
    },
  )

  await readJson<{ status: 'ok' }>(
    response,
  )
}
