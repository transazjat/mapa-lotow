<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import type { AchievementItem } from '../../types/achievement'
import { achievementIcons } from './achievementUi'

defineProps<{
  selectedAchievement: AchievementItem | null
  currentValueLabel: string
  currentMetricIcon: string
  detailDescription: string
  exporting: 'png' | 'jpg' | null
  getBadgeName: (threshold: number) => string
  getBadgeImage: (threshold: number) => string | null
  formatThreshold: (threshold: number) => string
  formatDate: (value: string | null) => string
}>()

const emit = defineEmits<{
  export: [format: 'png' | 'jpg']
}>()

const previewOpen = ref(false)

function openPreview(): void {
  previewOpen.value = true
}

function closePreview(): void {
  previewOpen.value = false
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') closePreview()
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))

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
  <aside class="achievement-detail-card">
    <template v-if="selectedAchievement?.earned">
      <span class="achievement-detail-card__caption">Szczegóły odznaki</span>
      <span :class="['achievement-detail-status', `achievement-detail-status--${selectedAchievement.status}`]">
        <span class="achievement-detail-status__icon" v-html="getStatusIcon(selectedAchievement.status)"></span>
        {{ getStatusText(selectedAchievement.status) }}
      </span>

      <button
        class="achievement-detail-badge-stage achievement-detail-badge-stage--clickable"
        type="button"
        :aria-label="`Pokaż odznakę ${formatThreshold(selectedAchievement.threshold)} w pełnej skali`"
        @click="openPreview"
      >
        <img
          class="achievement-detail-card__badge"
          :class="{ 'achievement-detail-card__badge--inactive': selectedAchievement.status === 'inactive' }"
          :src="getBadgeImage(selectedAchievement.threshold) ?? ''"
          :alt="formatThreshold(selectedAchievement.threshold)"
        >
      </button>

      <h3>{{ formatThreshold(selectedAchievement.threshold) }}</h3>

      <div class="achievement-detail-name-wrap">
        <span class="achievement-detail-name-wrap__line"></span>
        <strong class="achievement-detail-name">{{ getBadgeName(selectedAchievement.threshold) }}</strong>
        <span class="achievement-detail-name-wrap__line"></span>
      </div>

      <p>{{ detailDescription }}</p>

      <div class="achievement-detail-meta">
        <div class="achievement-detail-meta__item">
          <span class="achievement-detail-meta__icon" v-html="achievementIcons.calendar"></span>
          <div>
            <span>Po raz pierwszy</span>
            <strong>{{ formatDate(selectedAchievement.earned_at) }}</strong>
          </div>
        </div>
        <div class="achievement-detail-meta__item">
          <span class="achievement-detail-meta__icon" v-html="currentMetricIcon"></span>
          <div>
            <span>Aktualnie</span>
            <strong>{{ currentValueLabel }}</strong>
          </div>
        </div>
      </div>

      <button
        class="achievement-share-primary"
        type="button"
        :disabled="exporting !== null"
        @click="emit('export', 'png')"
      >
        ⌯ {{ exporting === 'png' ? 'Tworzę kartę…' : 'Utwórz kartę PNG' }}
      </button>
      <button
        class="achievement-share-secondary"
        type="button"
        :disabled="exporting !== null"
        @click="emit('export', 'jpg')"
      >
        Pobierz także JPG
      </button>
    </template>

    <template v-else-if="selectedAchievement">
      <span class="achievement-detail-card__caption">Szczegóły odznaki</span>
      <span class="achievement-detail-status achievement-detail-status--locked">
        <span class="achievement-detail-status__icon" v-html="achievementIcons.lock"></span>
        Nieodkryta
      </span>
      <div class="achievement-detail-secret">
        <span class="achievement-detail-lock" v-html="achievementIcons.lock"></span>
      </div>
      <h3>Nieodkryta odznaka</h3>
      <p>Szczegóły pozostają tajemnicą do momentu zdobycia tego progu.</p>
      <div class="achievement-secret-hint">Kolejne przygody czekają</div>
    </template>
    <Teleport to="body">
      <div
        v-if="previewOpen && selectedAchievement?.earned"
        class="achievement-badge-preview"
        role="dialog"
        aria-modal="true"
        :aria-label="`Odznaka ${formatThreshold(selectedAchievement.threshold)} w pełnej skali`"
        @click.self="closePreview"
      >
        <button
          class="achievement-badge-preview__close"
          type="button"
          aria-label="Zamknij podgląd odznaki"
          @click="closePreview"
        >×</button>
        <img
          class="achievement-badge-preview__image"
          :src="getBadgeImage(selectedAchievement.threshold) ?? ''"
          :alt="formatThreshold(selectedAchievement.threshold)"
        >
      </div>
    </Teleport>
  </aside>
</template>
