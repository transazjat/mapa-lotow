<script setup lang="ts">
import { computed } from 'vue'
import { achievementIcons } from './achievementUi'

const props = defineProps<{
  currentValue: string
  currentValueCaption: string
  currentBadgeLabel: string
  nextBadgeLabel: string
  progressValueLabel: string
  progressPercent: number
}>()

const progressPlaneStyle = computed(
  () => ({
    left: `clamp(10px, calc(${props.progressPercent}% - 12px), calc(100% - 18px))`,
  }),
)
</script>

<template>
  <div class="achievement-progress-card">
    <div class="achievement-progress-card__count">
      <strong>{{ currentValue }}</strong>
      <span>{{ currentValueCaption }}</span>
    </div>

    <div class="achievement-progress-card__bar">
      <div class="achievement-progress-card__labels">
        <span>Aktualna odznaka: <b>{{ currentBadgeLabel }}</b></span>
        <span>Do kolejnej: <b>{{ nextBadgeLabel }}</b></span>
      </div>

      <div class="achievement-progress-track">
        <span
          class="achievement-progress-track__fill"
          :style="{ width: `${progressPercent}%` }"
        ></span>
        <span
          class="achievement-progress-plane progress-plane"
          :style="progressPlaneStyle"
          v-html="achievementIcons.progressPlane"
        ></span>
      </div>

      <strong class="achievement-progress-value">{{ progressValueLabel }}</strong>
    </div>
  </div>
</template>
