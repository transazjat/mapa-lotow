<script setup lang="ts">
import type { AchievementItem } from '../../types/achievement'
import { achievementIcons } from './achievementUi'

const props = defineProps<{
  achievements: AchievementItem[]
  selectedKey: string | null
  getBadgeName: (threshold: number) => string
  getBadgeImage: (threshold: number) => string | null
  formatThreshold: (threshold: number) => string
}>()

const emit = defineEmits<{
  select: [item: AchievementItem]
}>()

function getStatusText(
  status: AchievementItem['status'],
): string {
  if (status === 'active') return 'Aktywna'
  if (status === 'inactive') return 'Nieaktywna'
  return 'Nieodkryta'
}

function getStatusIcon(
  status: AchievementItem['status'],
): string {
  if (status === 'active') return achievementIcons.check
  if (status === 'inactive') return achievementIcons.minus
  return achievementIcons.lock
}
</script>

<template>
  <div class="achievement-badge-grid">
    <button
      v-for="item in props.achievements"
      :key="item.key"
      type="button"
      :class="[
        'achievement-badge-card',
        `achievement-badge-card--${item.status}`,
        { 'achievement-badge-card--selected': selectedKey === item.key },
      ]"
      :title="item.earned ? `${formatThreshold(item.threshold)} · ${getBadgeName(item.threshold)}` : 'Nieodkryta odznaka'"
      @click="emit('select', item)"
    >
      <div v-if="item.earned" class="achievement-badge-picture">
        <img :src="getBadgeImage(item.threshold) ?? ''" :alt="formatThreshold(item.threshold)">
      </div>
      <div v-else class="achievement-badge-secret" aria-label="Nieodkryta odznaka">
        <span class="achievement-badge-secret__medallion" aria-hidden="true"></span>
        <span class="achievement-lock-icon" v-html="achievementIcons.lock"></span>
      </div>

      <strong>{{ formatThreshold(item.threshold) }}</strong>

      <span :class="['badge-status', `badge-status--${item.status}`]">
        <span class="badge-status__icon" v-html="getStatusIcon(item.status)"></span>
        {{ getStatusText(item.status) }}
      </span>
    </button>
  </div>
</template>
