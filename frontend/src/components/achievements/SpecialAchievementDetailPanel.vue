<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import type { SpecialAchievementItem } from '../../types/achievement'

const props = defineProps<{
  selectedItem: SpecialAchievementItem | null
  badgeImage: string | null
  formatDate: (value: string | null) => string
}>()

const previewOpen = ref(false)

function closePreview(): void {
  previewOpen.value = false
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') closePreview()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <aside class="special-detail-panel">
    <template v-if="selectedItem">
      <div class="special-detail-panel__status" :class="selectedItem.status">
        {{ selectedItem.earned ? 'Zdobyta' : 'Nieodblokowana' }}
      </div>

      <button
        type="button"
        class="special-detail-panel__badge-stage"
        :disabled="!selectedItem.earned"
        :aria-label="selectedItem.earned ? 'Pokaż odznakę w powiększeniu' : undefined"
        @click="selectedItem.earned && (previewOpen = true)"
      >
        <img
          v-if="selectedItem.earned && badgeImage"
          :src="badgeImage"
          :alt="`Odznaka ${selectedItem.label}`"
        >
        <div
          v-else
          :class="[
            'special-achievement-placeholder',
            'special-achievement-placeholder--detail',
            `special-achievement-placeholder--${selectedItem.category}`,
          ]"
          aria-hidden="true"
        >
          <span>?</span>
        </div>
      </button>

      <h3>{{ selectedItem.label }}</h3>
      <p>{{ selectedItem.description }}</p>

      <div class="special-detail-panel__meta">
        <div>
          <span>Zdobyto</span>
          <strong>{{ selectedItem.earned ? formatDate(selectedItem.earned_at) : '—' }}</strong>
        </div>
        <div>
          <span>Rodzaj</span>
          <strong>Osiągnięcie specjalne</strong>
        </div>
      </div>

      <div v-if="selectedItem.details.length" class="special-detail-panel__details">
        <h4>Szczegóły</h4>
        <div v-for="(detail, index) in selectedItem.details" :key="`${detail.label}-${index}`" class="special-detail-row">
          <span>{{ detail.label }}</span>
          <strong>{{ detail.value }}</strong>
          <small v-if="detail.note">{{ detail.note }}</small>
        </div>
      </div>
    </template>

    <div v-else class="special-detail-panel__empty">
      Wybierz odznakę, aby zobaczyć szczegóły.
    </div>

    <Teleport to="body">
      <div v-if="previewOpen && selectedItem && badgeImage" class="special-badge-preview" role="dialog" aria-modal="true" :aria-label="selectedItem.label" @click.self="closePreview">
        <button type="button" class="special-badge-preview__close" aria-label="Zamknij" @click="closePreview">×</button>
        <img :src="badgeImage" :alt="selectedItem.label">
      </div>
    </Teleport>
  </aside>
</template>
