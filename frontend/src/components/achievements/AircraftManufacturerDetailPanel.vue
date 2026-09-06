<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import type { AircraftManufacturerAchievementItem } from '../../types/achievement'
import { achievementIcons } from './achievementUi'

const props = defineProps<{
  selectedManufacturer: AircraftManufacturerAchievementItem | null
  description: string
  exporting: 'png' | 'jpg' | null
  badgeImage: string | null
  formatDate: (value: string | null) => string
}>()

const emit = defineEmits<{
  export: [format: 'png' | 'jpg']
}>()

const previewOpen = ref(false)
function openPreview(): void {
  if (props.selectedManufacturer?.earned && props.badgeImage) previewOpen.value = true
}
function closePreview(): void { previewOpen.value = false }
function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') closePreview()
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <aside class="achievement-detail-card aircraft-manufacturer-detail-panel">
    <template v-if="selectedManufacturer?.earned">
      <span class="achievement-detail-card__caption">Szczegóły odznaki</span>
      <span class="achievement-detail-status achievement-detail-status--active">
        <span class="achievement-detail-status__icon" v-html="achievementIcons.check"></span>
        Aktywna
      </span>

      <button
        class="achievement-detail-badge-stage achievement-detail-badge-stage--clickable"
        type="button"
        :aria-label="`Pokaż odznakę ${selectedManufacturer.manufacturer} w pełnej skali`"
        @click="openPreview"
      >
        <img
          class="achievement-detail-card__badge aircraft-manufacturer-detail-panel__badge"
          :src="badgeImage ?? ''"
          :alt="selectedManufacturer.manufacturer"
        >
      </button>

      <h3>{{ selectedManufacturer.manufacturer }}</h3>
      <p>{{ description }}</p>

      <div class="achievement-detail-meta aircraft-manufacturer-detail-panel__meta">
        <div class="achievement-detail-meta__item">
          <span class="achievement-detail-meta__icon" v-html="achievementIcons.calendar"></span>
          <div>
            <span>Zdobyta</span>
            <strong>{{ formatDate(selectedManufacturer.earned_at) }}</strong>
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

    <template v-else-if="selectedManufacturer">
      <span class="achievement-detail-card__caption">Szczegóły odznaki</span>
      <span class="achievement-detail-status achievement-detail-status--locked">
        <span class="achievement-detail-status__icon" v-html="achievementIcons.lock"></span>
        Nieodkryta
      </span>
      <div class="achievement-detail-secret">
        <span class="achievement-detail-lock" v-html="achievementIcons.lock"></span>
      </div>
      <h3>{{ selectedManufacturer.manufacturer }}</h3>
      <p>Wygląd odznaki pozostaje ukryty do momentu pierwszego lotu samolotem tej rodziny producenta.</p>
      <div class="achievement-secret-hint">Kolejne maszyny czekają</div>
    </template>

    <Teleport to="body">
      <div
        v-if="previewOpen && selectedManufacturer?.earned && badgeImage"
        class="achievement-badge-preview"
        role="dialog"
        aria-modal="true"
        :aria-label="`Odznaka ${selectedManufacturer.manufacturer} w pełnej skali`"
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
          :src="badgeImage"
          :alt="selectedManufacturer.manufacturer"
        >
      </div>
    </Teleport>
  </aside>
</template>
