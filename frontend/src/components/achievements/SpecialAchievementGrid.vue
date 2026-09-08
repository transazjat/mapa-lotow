<script setup lang="ts">
import type { SpecialAchievementItem } from '../../types/achievement'

const props = defineProps<{
  achievements: SpecialAchievementItem[]
  selectedKey: string | null
  getBadgeImage: (slug: string) => string | null
}>()

const emit = defineEmits<{
  select: [item: SpecialAchievementItem]
}>()

const categories: { key: SpecialAchievementItem['category']; label: string }[] = [
  { key: 'calendar', label: 'Daty i okazje' },
  { key: 'history', label: 'Historia latania' },
  { key: 'repeat', label: 'Powtarzalność i kolekcje' },
  { key: 'geography', label: 'Geografia świata' },
]

function itemsFor(category: SpecialAchievementItem['category']): SpecialAchievementItem[] {
  return props.achievements.filter((item) => item.category === category)
}
</script>

<template>
  <div class="special-achievement-groups">
    <section v-for="category in categories" :key="category.key" class="special-achievement-group">
      <h3>{{ category.label }}</h3>
      <div class="special-achievement-grid">
        <button
          v-for="item in itemsFor(category.key)"
          :key="item.key"
          type="button"
          :class="['special-achievement-card', item.status, { 'is-selected': item.key === selectedKey }]"
          @click="emit('select', item)"
        >
          <div class="special-achievement-card__visual">
            <img
              v-if="item.earned && getBadgeImage(item.slug)"
              :src="getBadgeImage(item.slug) ?? ''"
              :alt="`Odznaka ${item.label}`"
            >
            <div
              v-else
              :class="[
                'special-achievement-placeholder',
                `special-achievement-placeholder--${item.category}`,
              ]"
              aria-hidden="true"
            >
              <span>?</span>
            </div>
          </div>
          <strong>{{ item.label }}</strong>
          <span>{{ item.earned ? 'Zdobyta' : 'Nieodblokowana' }}</span>
        </button>
      </div>
    </section>
  </div>
</template>
