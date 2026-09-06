<script setup lang="ts">
import flightSignUrl from '../../assets/branding/mapa-lotow-symbol.png'

interface FamilyItem {
  key: string
  label: string
  description: string
  icon: string
}

defineProps<{
  families: readonly FamilyItem[]
  activeFamily: string
}>()

const emit = defineEmits<{
  select: [key: string]
}>()
</script>

<template>
  <aside class="achievements-sidebar">
    <div class="achievements-brand">
      <img
        :src="flightSignUrl"
        class="achievements-brand__symbol"
        alt=""
        aria-hidden="true"
      >
      <div class="brand-board" aria-label="Mapa Lotów">
        <div class="brand-board__row">
          <span>M</span><span>A</span><span>P</span><span>A</span><span class="brand-board__empty"></span>
        </div>
        <div class="brand-board__row">
          <span>L</span><span>O</span><span>T</span><span>Ó</span><span>W</span>
        </div>
      </div>
    </div>

    <nav class="achievement-family-nav" aria-label="Rodziny osiągnięć">
      <button
        v-for="family in families"
        :key="family.key"
        type="button"
        :class="[
          'achievement-family-link',
          { 'achievement-family-link--active': activeFamily === family.key },
        ]"
        @click="emit('select', family.key)"
      >
        <span class="achievement-family-link__icon" v-html="family.icon"></span>
        <span class="achievement-family-link__copy">
          <strong>{{ family.label }}</strong>
          <small>{{ family.description }}</small>
        </span>
      </button>
    </nav>
  </aside>
</template>
