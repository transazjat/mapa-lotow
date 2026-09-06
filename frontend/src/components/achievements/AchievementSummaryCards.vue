<script setup lang="ts">
defineProps<{
  earnedValue: string
  activeValue: string
  lastEarnedValue: string
  lastEarnedNote: string
  nextThresholdValue: string
  nextThresholdNote: string
}>()
</script>

<template>
  <section class="achievement-summary-grid">
    <article>
      <span class="achievement-summary-grid__label">Zdobyte odznaki</span>
      <strong>{{ earnedValue }}</strong>
      <span class="achievement-summary-grid__note">&nbsp;</span>
    </article>
    <article>
      <span class="achievement-summary-grid__label">Aktywne</span>
      <strong>{{ activeValue }}</strong>
      <span class="achievement-summary-grid__note">&nbsp;</span>
    </article>
    <article>
      <span class="achievement-summary-grid__label">Ostatnio zdobyta</span>
      <strong>{{ lastEarnedValue }}</strong>
      <span class="achievement-summary-grid__note">{{ lastEarnedNote }}</span>
    </article>
    <article>
      <span class="achievement-summary-grid__label">Następny próg</span>
      <strong>{{ nextThresholdValue }}</strong>
      <span class="achievement-summary-grid__note">{{ nextThresholdNote }}</span>
    </article>
  </section>
</template>
