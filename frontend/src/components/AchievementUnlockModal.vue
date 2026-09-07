<script setup lang="ts">
import { computed } from 'vue'

import type {
  AchievementItem,
  AircraftCollectionAchievementItem,
} from '../types/achievement'

import {
  getAchievementBadgeImage,
  getAircraftManufacturerBadgeImage,
  getAircraftOriginBadgeImage,
  getAircraftUniqueBadgeImage,
} from '../utils/achievementBadges'

type UnlockItem = AchievementItem | AircraftCollectionAchievementItem

const props = defineProps<{
  achievements: UnlockItem[]
  completedFlights: number
  nick: string
}>()

const emit = defineEmits<{
  close: []
}>()

const single = computed(
  () => props.achievements.length === 1
    ? props.achievements[0]
    : null,
)

function isThresholdAchievement(item: UnlockItem): item is AchievementItem {
  return 'threshold' in item && 'family' in item
}

function itemLabel(item: UnlockItem): string {
  if (!isThresholdAchievement(item)) return item.label

  if (item.family === 'distance') return `${item.threshold.toLocaleString('pl-PL')} km`
  if (item.family === 'airports') return `${item.threshold} lotnisk`
  if (item.family === 'countries') return `${item.threshold} państw`
  if (item.family === 'continents') return `${item.threshold} kontynentów`
  if (item.family === 'airlines') return `${item.threshold} linii lotniczych`
  if (item.family === 'aircraft') return `${item.threshold} typów samolotów`
  if (item.family === 'routes') return `${item.threshold} tras`
  if (item.family === 'duration') return `${item.threshold} h w powietrzu`
  if (item.family === 'astronomical') return `${item.threshold.toLocaleString('pl-PL')} km`
  if (item.family === 'intensity_year') return `${item.threshold} lotów w roku`
  if (item.family === 'intensity_month') return `${item.threshold} lotów w miesiącu`
  if (item.family === 'intensity_streak') return `${item.threshold} dni serii`
  if (item.family === 'intensity_day') return `${item.threshold} lotów jednego dnia`
  return `${item.threshold} lotów`
}

function itemDescription(item: UnlockItem): string {
  if (isThresholdAchievement(item)) {
    if (item.family === 'distance') return 'Przekroczyłeś kolejny próg łącznego dystansu w powietrzu.'
    if (item.family === 'airports') return 'Do Twojej mapy dołączył kolejny próg liczby odwiedzonych lotnisk.'
    if (item.family === 'countries') return 'Do Twojej historii podróży dołączył kolejny próg liczby państw.'
    if (item.family === 'continents') return 'Twoja mapa podróży objęła kolejny próg liczby kontynentów.'
    if (item.family === 'airlines') return 'Twoja kolekcja przewoźników przekroczyła kolejny próg.'
    if (item.family === 'aircraft') return 'Twoja kolekcja typów samolotów przekroczyła kolejny próg.'
    if (item.family === 'routes') return 'Twoja sieć lotniczych połączeń przekroczyła kolejny próg liczby różnych tras.'
    if (item.family === 'duration') return 'Łączny czas Twoich odbytych lotów przekroczył kolejny próg godzin spędzonych w powietrzu.'
    if (item.family === 'astronomical') return 'Twój łączny dystans lotniczy przekroczył kolejny kosmiczny próg porównawczy.'
    if (item.family.startsWith('intensity_')) return 'Osiągnąłeś nowy próg intensywności w swojej historii lotów.'
    return 'Osiągnąłeś kolejny lotniczy kamień milowy.'
  }

  if (item.key.startsWith('aircraft_manufacturer_')) {
    return `Pierwszy lot samolotem producenta ${item.label} odblokował nową odznakę.`
  }
  if (item.key.startsWith('aircraft_origin_')) {
    return `Pierwszy lot maszyną reprezentującą tę szkołę pochodzenia odblokował nową odznakę.`
  }
  return 'Pierwszy lot tą unikalną maszyną odblokował nową odznakę.'
}

function itemImage(item: UnlockItem): string | null {
  if (isThresholdAchievement(item)) {
    return getAchievementBadgeImage(item.family, item.threshold)
  }

  if (item.key.startsWith('aircraft_manufacturer_')) {
    return getAircraftManufacturerBadgeImage(item.slug)
  }
  if (item.key.startsWith('aircraft_origin_')) {
    return getAircraftOriginBadgeImage(item.slug)
  }
  if (item.key.startsWith('aircraft_unique_')) {
    return getAircraftUniqueBadgeImage(item.slug)
  }
  return null
}
</script>

<template>
  <div class="achievement-unlock-backdrop" role="dialog" aria-modal="true" aria-label="Nowe osiągnięcie">
    <section class="achievement-unlock-modal">
      <button class="achievement-unlock-close" type="button" aria-label="Zamknij" @click="emit('close')">×</button>

      <template v-if="single">
        <span class="achievement-unlock-kicker">Nowe osiągnięcie</span>
        <h2>{{ itemLabel(single) }}</h2>
        <p>{{ itemDescription(single) }}</p>

        <img
          v-if="itemImage(single)"
          class="achievement-unlock-badge"
          :src="itemImage(single) ?? ''"
          :alt="itemLabel(single)"
        >

        <div class="achievement-unlock-note">
          Ta odznaka została dodana do Twojej kolekcji.
        </div>

        <div class="achievement-unlock-actions achievement-unlock-actions--single">
          <button type="button" class="achievement-unlock-primary" @click="emit('close')">
            Zamknij
          </button>
        </div>
      </template>

      <template v-else>
        <span class="achievement-unlock-kicker">Nowe osiągnięcia</span>
        <h2>Zdobyłeś {{ achievements.length }} odznak</h2>
        <p>Do Twojej kolekcji trafiło kilka nowych osiągnięć.</p>

        <div class="achievement-unlock-grid">
          <article v-for="item in achievements" :key="item.key">
            <img v-if="itemImage(item)" :src="itemImage(item) ?? ''" :alt="itemLabel(item)">
            <strong>{{ itemLabel(item) }}</strong>
          </article>
        </div>

        <div class="achievement-unlock-actions achievement-unlock-actions--single">
          <button type="button" class="achievement-unlock-primary" @click="emit('close')">Zamknij</button>
        </div>
      </template>
    </section>
  </div>
</template>

<style scoped>
.achievement-unlock-backdrop{position:fixed;inset:0;z-index:160;display:grid;place-items:center;padding:24px;background:rgba(5,23,42,.62);backdrop-filter:blur(9px)}
.achievement-unlock-modal{position:relative;display:flex;flex-direction:column;width:min(680px,calc(100vw - 40px));max-height:calc(100dvh - 48px);padding:36px 38px 30px;overflow:hidden;border:1px solid rgba(255,255,255,.8);border-radius:22px;background:radial-gradient(circle at 50% 0%,#edf7ff,#fff 48%,#f3f8fc);box-shadow:0 34px 100px rgba(4,25,49,.38);text-align:center;color:#113867}
.achievement-unlock-close{position:absolute;right:16px;top:14px;width:38px;height:38px;border:1px solid #d3e1ec;border-radius:9px;background:#f7fbfe;color:#57738f;cursor:pointer;font-size:26px}
.achievement-unlock-kicker{display:block;color:#6d88a3;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.achievement-unlock-modal h2{margin:8px 0 3px;color:#0d3466;font-size:34px;font-weight:800}.achievement-unlock-modal>p{margin:0 auto;max-width:520px;color:#72879a;font-size:14px;line-height:1.45}.achievement-unlock-badge{display:block;width:min(330px,72vw);max-height:430px;object-fit:contain;margin:18px auto 5px;filter:drop-shadow(0 18px 24px rgba(19,52,84,.22))}.achievement-unlock-note{margin:8px auto 18px;color:#5e7893;font-size:12px}.achievement-unlock-actions{display:grid;grid-template-columns:1fr;gap:8px}.achievement-unlock-actions button{min-height:44px;border:1px solid #c8dae8;border-radius:9px;background:#f8fbfe;color:#234f7b;cursor:pointer;font-weight:650}.achievement-unlock-actions .achievement-unlock-primary{border-color:#165894;background:linear-gradient(#246eb1,#15528e);color:#fff}.achievement-unlock-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin:24px 0;min-height:0;overflow-y:auto;overscroll-behavior:contain;padding-right:5px;scrollbar-gutter:stable}.achievement-unlock-grid article{padding:10px;border:1px solid #dce8f1;border-radius:10px;background:#fbfdff}.achievement-unlock-grid img{display:block;width:100%;height:130px;object-fit:contain}.achievement-unlock-grid strong{display:block;margin-top:5px;font-size:11px;line-height:1.25}
@media(max-width:620px){.achievement-unlock-modal{padding:30px 18px 22px}.achievement-unlock-badge{width:250px}}
</style>
