<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
} from 'vue'

import {
  getAchievements,
} from '../services/achievementsApi'

import type {
  AchievementItem,
  AchievementsResponse,
} from '../types/achievement'

import {
  getFlightBadgeImage,
} from '../utils/achievementBadges'

import flightSignUrl from '../assets/branding/mapa-lotow-symbol.png'
import achievementHeaderArtUrl from '../assets/achievements/achievement-header-art.png'
import achievementHandwrittenNoteUrl from '../assets/achievements/achievement-note-handwritten.png'

import {
  downloadAchievementCard,
} from '../utils/achievementCard'

const props = defineProps<{
  nick: string
}>()

const emit = defineEmits<{
  close: []
}>()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<AchievementsResponse | null>(null)
const selectedKey = ref<string | null>(null)
const activeFamily = ref('flights')
const exporting = ref<'png' | 'jpg' | null>(null)


const flightBadgeNames: Record<number, string> = {
  25: 'Pierwszy rozdział',
  50: 'Na dobre w powietrzu',
  100: 'Setny lot',
  200: 'Stały bywalec',
  300: 'Skrzydła doświadczenia',
  400: 'Wysokie loty',
  500: 'Pół tysiąca',
  600: 'Weteran przestworzy',
  750: 'Trzy czwarte tysiąca',
  1000: 'Tysiąc lotów',
}

function getBadgeName(
  threshold: number,
): string {
  return flightBadgeNames[threshold] ?? `${threshold} lotów`
}

const icons = {
  flights: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.7 12.8 9.4 11l2.8-5.7c.3-.6.9-1 1.6-1 .8 0 1.5.5 1.7 1.2l.6 1.8-1.9 3.2 4.6-1.2 2.6-2c.4-.3.8-.5 1.3-.5.9 0 1.6.7 1.6 1.6 0 .5-.2 1-.6 1.3l-2.7 2.5c-.3.3-.7.5-1.2.6L15 14l1.5 3.2-.7 1.5c-.2.5-.7.8-1.3.8-.5 0-.9-.2-1.2-.5l-3.5-3.7-6.2 1.5c-.9.2-1.7-.3-1.9-1.2-.2-.9.3-1.7 1-2.8Z" fill="currentColor"/></svg>',
  distance: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.9"/><path d="M4 12h16M12 4a13 13 0 0 1 0 16M12 4a13 13 0 0 0 0 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
  airports: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s6-5.2 6-10a6 6 0 1 0-12 0c0 4.8 6 10 6 10Z" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/><circle cx="12" cy="11" r="2.5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
  countries: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 21V4" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M8 5h8l-1.6 3L16 11H8Z" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/></svg>',
  continents: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11.2 2.8 14 4l2.2 2.2 2 .8-.1 2.1-1.5 1.5-.8 2.4-1.5 1.3-.4 2.6-1.7 2.3-.9 2-1.2-1.2-.9-2.5-1.5-1.8-.8-2.5-1.3-1.8.4-2.5 1.4-1.5.7-2.3 1.6-.7 1.5-1.6Z" fill="currentColor"/><path d="m14.8 4.8 2.8-.5 1.2 1.1-1.6.9Z" fill="currentColor" opacity=".9"/></svg>',
  airlines: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h16M5.5 19V9.5h13V19M8 9.5V6h8v3.5M9 13h1.5M13.5 13H15M9 16h1.5M13.5 16H15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  aircraft: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 13.3 10.1 12l3.1-6.6c.3-.6.9-1 1.6-1 .7 0 1.3.4 1.6 1l.6 1.4-2 4.3 4.1-.7 2.4-1.9c.4-.3.8-.5 1.3-.5.8 0 1.5.7 1.5 1.5 0 .5-.2.9-.5 1.2l-2.6 2.3c-.3.3-.7.4-1.1.5l-5.2.8 1.8 3.6-.8 1.5c-.3.5-.8.8-1.4.8-.5 0-.9-.2-1.2-.5l-3.6-3.9-6.1 1.1c-.9.2-1.7-.4-1.9-1.3-.2-.9.4-1.7 1.3-2.3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
  routes: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="17" r="2" fill="currentColor"/><circle cx="12" cy="10" r="2" fill="currentColor"/><circle cx="19" cy="6" r="2" fill="currentColor"/><path d="M6.6 15.8c1.2-2.4 2.9-4.1 4.3-4.8 1.7-.8 3.3-.5 6.5-3.4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-dasharray="2.4 2.4"/></svg>',
  intensity: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19V14M11 19V9M17 19V5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><path d="M4 19h15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" opacity=".55"/></svg>',
  duration: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.9"/><path d="M12 8v4.3l2.9 1.7" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  special: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 4 2.2 4.5 5 .7-3.6 3.5.9 5-4.5-2.4-4.5 2.4.9-5L4.8 9.2l5-.7L12 4Z" fill="currentColor"/></svg>',
  astronomical: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.7 17.8A7.1 7.1 0 0 1 7.1 7.1a7.4 7.4 0 1 0 8.6 10.7Z" fill="currentColor"/><path d="m17.7 4.5.7 1.6 1.7.3-1.3 1.2.3 1.7-1.4-.8-1.5.8.3-1.7-1.2-1.2 1.7-.3.7-1.6Z" fill="currentColor"/><circle cx="20" cy="12.1" r="1" fill="currentColor"/></svg>',
  progressPlane: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 800" role="img" aria-hidden="true"><path fill="currentColor" d="M 25 240 L 78 386 L 20 417 L 78 447 L 22 583 L 84 581 L 198 457 L 426 481 L 281 797 L 347 793 L 605 478 L 875 475 C 925 473 970 450 983 417 C 970 385 925 362 875 360 L 605 356 L 337 25 L 278 34 L 425 360 L 203 384 L 88 250 Z"/></svg>',
  check: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor" opacity=".18"/><circle cx="12" cy="12" r="9" fill="currentColor" opacity=".13"/><path d="m8.2 12.2 2.4 2.5 5.2-5.6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  minus: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor" opacity=".16"/><path d="M8 12h8" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>',
  lock: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.9"/><path d="M8 10V7a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M12 14v2.5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>',
  calendar: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5.5" width="16" height="14" rx="2.2" fill="none" stroke="currentColor" stroke-width="1.9"/><path d="M8 3.8v3.3M16 3.8v3.3M4.8 9.5h14.4" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>',
  notePlane: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.8 10.5 11l2.6-4.8c.3-.6.9-.9 1.6-.9.8 0 1.4.4 1.7 1l.7 1.5-1.8 3.6 4.1-.4 2.2-2c.4-.4.8-.6 1.4-.6.9 0 1.6.7 1.6 1.6 0 .5-.2 1-.6 1.4L21 14c-.4.4-.8.5-1.3.6l-4.2.4 1.3 2.8-.7 1.3c-.2.5-.7.8-1.3.8-.5 0-.9-.2-1.2-.5l-3.2-3.9-6.3.8c-.8.1-1.5-.5-1.6-1.3-.1-.8.4-1.5 1.2-1.6Z" fill="currentColor"/></svg>',
} as const

const families = [
  { key: 'flights', label: 'Loty', description: 'Twoje osiągnięcia', icon: icons.flights },
  { key: 'distance', label: 'Dystans', description: 'Twoje kilometry', icon: icons.distance },
  { key: 'airports', label: 'Lotniska', description: 'Odwiedzone miejsca', icon: icons.airports },
  { key: 'countries', label: 'Państwa', description: 'Odkryte kraje', icon: icons.countries },
  { key: 'continents', label: 'Kontynenty', description: 'Zwiedzony świat', icon: icons.continents },
  { key: 'airlines', label: 'Linie lotnicze', description: 'Twoje linie', icon: icons.airlines },
  { key: 'aircraft', label: 'Typy samolotów', description: 'Flota w powietrzu', icon: icons.aircraft },
  { key: 'routes', label: 'Trasy', description: 'Twoje ścieżki', icon: icons.routes },
  { key: 'intensity', label: 'Intensywność', description: 'Poziom aktywności', icon: icons.intensity },
  { key: 'duration', label: 'Czas w powietrzu', description: 'Godziny w chmurach', icon: icons.duration },
  { key: 'special', label: 'Specjalne', description: 'Wyjątkowe osiągnięcia', icon: icons.special },
  { key: 'astronomical', label: 'Astronomiczne dystanse', description: 'Poza horyzontem', icon: icons.astronomical },
] as const

const achievements = computed(
  () => data.value?.achievements ?? [],
)

const selectedAchievement = computed<AchievementItem | null>(
  () => {
    const key = selectedKey.value

    if (key) {
      return achievements.value.find(
        (item) => item.key === key,
      ) ?? null
    }

    const lastEarnedKey = data.value?.summary.last_earned?.key

    if (lastEarnedKey) {
      return achievements.value.find(
        (item) => item.key === lastEarnedKey,
      ) ?? null
    }

    return achievements.value.find(
      (item) => item.earned,
    ) ?? null
  },
)

const futureFamily = computed(
  () => families.find(
    (item) => item.key === activeFamily.value,
  ) ?? families[0],
)

const progressPercent = computed(
  () => {
    const state = data.value

    if (!state) {
      return 0
    }

    const next = state.summary.next_threshold

    if (!next) {
      return 100
    }

    const thresholds = state.achievements.map(
      (item) => item.threshold,
    )

    const previous = [...thresholds]
      .reverse()
      .find(
        (threshold) => threshold <= state.completed_flights,
      ) ?? 0

    const segment = next - previous

    if (segment <= 0) {
      return 100
    }

    return Math.max(
      0,
      Math.min(
        100,
        (
          (state.completed_flights - previous) /
          segment
        ) * 100,
      ),
    )
  },
)

const progressPlaneStyle = computed(
  () => ({
    left: `clamp(10px, calc(${progressPercent.value}% - 12px), calc(100% - 18px))`,
  }),
)

const currentThreshold = computed(
  () => {
    const state = data.value

    if (!state) {
      return null
    }

    return [...state.achievements]
      .reverse()
      .find(
        (item) => item.active,
      )?.threshold ?? null
  },
)

function formatDate(
  value: string | null,
): string {
  if (!value) {
    return '—'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  const months = [
    'sty', 'lut', 'mar', 'kwi', 'maj', 'cze',
    'lip', 'sie', 'wrz', 'paź', 'lis', 'gru',
  ]

  return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`
}

function selectFamily(
  key: string,
): void {
  activeFamily.value = key
}

function selectAchievement(
  item: AchievementItem,
): void {
  selectedKey.value = item.key
}

function getStatusText(
  status: AchievementItem['status'],
): string {
  if (status === 'active') {
    return 'Aktywna'
  }

  if (status === 'inactive') {
    return 'Nieaktywna'
  }

  return 'Nieodkryta'
}

function getStatusIcon(
  status: AchievementItem['status'],
): string {
  if (status === 'active') {
    return icons.check
  }

  if (status === 'inactive') {
    return icons.minus
  }

  return icons.lock
}

async function exportCard(
  format: 'png' | 'jpg',
): Promise<void> {
  const item = selectedAchievement.value
  const state = data.value

  if (!item || !item.earned || !state) {
    return
  }

  exporting.value = format

  try {
    await downloadAchievementCard(
      item,
      state.completed_flights,
      props.nick,
      format,
    )
  } finally {
    exporting.value = null
  }
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    const response = await getAchievements()
    data.value = response

    selectedKey.value = response.summary.last_earned?.key
      ?? response.achievements.find(
        (item) => item.earned,
      )?.key
      ?? null
  } catch (err) {
    error.value = err instanceof Error
      ? err.message
      : 'Nie udało się pobrać osiągnięć.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div
    class="achievements-shell"
    role="dialog"
    aria-modal="true"
    aria-label="Osiągnięcia"
  >
    <section class="achievements-panel">
      <button
        class="achievements-close"
        type="button"
        aria-label="Zamknij osiągnięcia"
        @click="emit('close')"
      >
        ×
      </button>

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
            @click="selectFamily(family.key)"
          >
            <span class="achievement-family-link__icon" v-html="family.icon"></span>
            <span class="achievement-family-link__copy"><strong>{{ family.label }}</strong><small>{{ family.description }}</small></span>
          </button>
        </nav>
      </aside>

      <main class="achievements-content">
        <header
          class="achievements-header"
          :style="{ '--achievement-header-art': `url(${achievementHeaderArtUrl})` }"
        >
          <div class="achievements-header__copy">
            <span class="achievements-kicker">Konto</span>
            <h1>Osiągnięcia</h1>
            <p>Odznaki i kamienie milowe Twoich podróży.</p>
          </div>
          <div class="achievements-header__art" aria-hidden="true"></div>
        </header>

        <div v-if="loading" class="achievements-loading">
          Ładowanie osiągnięć…
        </div>
        <div v-else-if="error" class="achievements-error">
          {{ error }}
        </div>

        <template v-else-if="data && activeFamily === 'flights'">
          <section class="achievement-summary-grid">
            <article>
              <span class="achievement-summary-grid__label">Zdobyte odznaki</span>
              <strong>{{ data.summary.earned_count }} <small>z {{ data.achievements.length }}</small></strong>
              <span class="achievement-summary-grid__note">&nbsp;</span>
            </article>
            <article>
              <span class="achievement-summary-grid__label">Aktywne</span>
              <strong>{{ data.summary.active_count }} <small>z {{ data.summary.earned_count }}</small></strong>
              <span class="achievement-summary-grid__note">&nbsp;</span>
            </article>
            <article>
              <span class="achievement-summary-grid__label">Ostatnio zdobyta</span>
              <strong>{{ data.summary.last_earned ? `${data.summary.last_earned.threshold} lotów` : '—' }}</strong>
              <span class="achievement-summary-grid__note">{{ formatDate(data.summary.last_earned?.earned_at ?? null) }}</span>
            </article>
            <article>
              <span class="achievement-summary-grid__label">Następny próg</span>
              <strong>{{ data.summary.next_threshold ? `${data.summary.next_threshold} lotów` : 'Seria ukończona' }}</strong>
              <span class="achievement-summary-grid__note" v-if="data.summary.remaining_to_next !== null">Pozostało {{ data.summary.remaining_to_next }} lotów</span>
            </article>
          </section>

          <section class="achievement-workspace">
            <div class="achievement-collection">
              <div class="achievement-family-heading">
                <div>
                  <h2>Loty</h2>
                  <p>Liczba odbytych lotów. Każdy lot to nowa historia, nowe miejsce i nowe możliwości.</p>
                </div>
              </div>

              <div class="achievement-progress-card">
                <div class="achievement-progress-card__count">
                  <strong>{{ data.completed_flights }} lotów</strong>
                  <span>Twoja łączna liczba odbytych lotów</span>
                </div>

                <div class="achievement-progress-card__bar">
                  <div class="achievement-progress-card__labels">
                    <span>Aktualna odznaka: <b>{{ currentThreshold ? `${currentThreshold} lotów` : 'jeszcze żadna' }}</b></span>
                    <span>Do kolejnej: <b>{{ data.summary.next_threshold ? `${data.summary.next_threshold} lotów` : 'ukończono serię' }}</b></span>
                  </div>

                  <div class="achievement-progress-track">
                    <span
                      class="achievement-progress-track__fill"
                      :style="{ width: `${progressPercent}%` }"
                    ></span>
                    <span
                      class="achievement-progress-plane progress-plane"
                      :style="progressPlaneStyle"
                      v-html="icons.progressPlane"
                    ></span>
                  </div>

                  <strong class="achievement-progress-value">
                    {{ data.summary.next_threshold ? `${data.completed_flights} / ${data.summary.next_threshold}` : `${data.completed_flights}` }}
                  </strong>
                </div>
              </div>

              <div class="achievement-badge-grid">
                <button
                  v-for="item in achievements"
                  :key="item.key"
                  type="button"
                  :class="[
                    'achievement-badge-card',
                    `achievement-badge-card--${item.status}`,
                    { 'achievement-badge-card--selected': selectedAchievement?.key === item.key },
                  ]"
                  :title="item.earned ? `${item.threshold} lotów · ${getBadgeName(item.threshold)}` : 'Nieodkryta odznaka'"
                  @click="selectAchievement(item)"
                >
                  <div v-if="item.earned" class="achievement-badge-picture">
                    <img :src="getFlightBadgeImage(item.threshold) ?? ''" :alt="`${item.threshold} lotów`">
                  </div>
                  <div v-else class="achievement-badge-secret" aria-label="Nieodkryta odznaka">
                    <span class="achievement-badge-secret__medallion" aria-hidden="true"></span>
                    <span class="achievement-lock-icon" v-html="icons.lock"></span>
                  </div>

                  <strong>{{ item.threshold }} lotów</strong>

                  <span :class="['badge-status', `badge-status--${item.status}`]">
                    <span class="badge-status__icon" v-html="getStatusIcon(item.status)"></span>
                    {{ getStatusText(item.status) }}
                  </span>
                </button>
              </div>
            </div>

            <aside
              class="achievement-detail-card"
            >
              <template v-if="selectedAchievement?.earned">
                <span class="achievement-detail-card__caption">Szczegóły odznaki</span>
                <span :class="['achievement-detail-status', `achievement-detail-status--${selectedAchievement.status}`]">
                  <span class="achievement-detail-status__icon" v-html="getStatusIcon(selectedAchievement.status)"></span>
                  {{ getStatusText(selectedAchievement.status) }}
                </span>

                <div class="achievement-detail-badge-stage">
                  <img
                    class="achievement-detail-card__badge"
                    :class="{ 'achievement-detail-card__badge--inactive': selectedAchievement.status === 'inactive' }"
                    :src="getFlightBadgeImage(selectedAchievement.threshold) ?? ''"
                    :alt="`${selectedAchievement.threshold} lotów`"
                  >
                </div>

                <h3>{{ selectedAchievement.threshold }} lotów</h3>

                <div class="achievement-detail-name-wrap">
                  <span class="achievement-detail-name-wrap__line"></span>
                  <strong class="achievement-detail-name">{{ getBadgeName(selectedAchievement.threshold) }}</strong>
                  <span class="achievement-detail-name-wrap__line"></span>
                </div>

                <p>Symbol Twojej pasji do podróżowania i odkrywania świata.</p>

                <div class="achievement-detail-meta">
                  <div class="achievement-detail-meta__item">
                    <span class="achievement-detail-meta__icon" v-html="icons.calendar"></span>
                    <div>
                      <span>Po raz pierwszy</span>
                      <strong>{{ formatDate(selectedAchievement.earned_at) }}</strong>
                    </div>
                  </div>
                  <div class="achievement-detail-meta__item">
                    <span class="achievement-detail-meta__icon" v-html="icons.flights"></span>
                    <div>
                      <span>Aktualnie</span>
                      <strong>{{ data.completed_flights }} lotów</strong>
                    </div>
                  </div>
                </div>

                <button
                  class="achievement-share-primary"
                  type="button"
                  :disabled="exporting !== null"
                  @click="exportCard('png')"
                >
                  ⌯ {{ exporting === 'png' ? 'Tworzę kartę…' : 'Utwórz kartę PNG' }}
                </button>
                <button
                  class="achievement-share-secondary"
                  type="button"
                  :disabled="exporting !== null"
                  @click="exportCard('jpg')"
                >
                  Pobierz także JPG
                </button>
              </template>

              <template v-else-if="selectedAchievement">
                <span class="achievement-detail-card__caption">Szczegóły odznaki</span>
                <span class="achievement-detail-status achievement-detail-status--locked">
                  <span class="achievement-detail-status__icon" v-html="icons.lock"></span>
                  Nieodkryta
                </span>
                <div class="achievement-detail-secret">
                  <span class="achievement-detail-lock" v-html="icons.lock"></span>
                </div>
                <h3>Nieodkryta odznaka</h3>
                <p>Szczegóły pozostają tajemnicą do momentu zdobycia tego progu.</p>
                <div class="achievement-secret-hint">Kolejne przygody czekają</div>
              </template>
            </aside>
          </section>

          <section class="achievement-legend">
            <div class="achievement-legend__list">
              <div class="achievement-legend__item">
                <span class="achievement-legend__symbol achievement-legend__symbol--active" v-html="icons.check"></span>
                <p>
                  <strong>Aktywna odznaka</strong>
                  <small>Zdobyta i aktualnie aktywna.</small>
                </p>
              </div>
              <div class="achievement-legend__item">
                <span class="achievement-legend__symbol achievement-legend__symbol--inactive" v-html="icons.minus"></span>
                <p>
                  <strong>Nieaktywna odznaka</strong>
                  <small>Zdobyta wcześniej, ale obecnie poniżej progu.</small>
                </p>
              </div>
              <div class="achievement-legend__item">
                <span class="achievement-legend__symbol achievement-legend__symbol--locked" v-html="icons.lock"></span>
                <p>
                  <strong>Nieodkryta odznaka</strong>
                  <small>Szczegóły pozostają tajemnicą do momentu zdobycia.</small>
                </p>
              </div>
            </div>

            <div class="achievement-legend__note" aria-hidden="true">
              <img
                class="achievement-legend__note-image"
                :src="achievementHandwrittenNoteUrl"
                alt=""
              >
              <span class="achievement-legend__note-plane" v-html="icons.notePlane"></span>
            </div>
          </section>
        </template>

        <section v-else class="achievement-family-future">
          <div class="achievement-family-future__symbol" v-html="futureFamily.icon"></div>
          <span>Rodzina osiągnięć</span>
          <h2>{{ futureFamily.label }}</h2>
          <p>Ta rodzina odznak jest już przewidziana w układzie systemu i zostanie opracowana w kolejnym etapie.</p>
          <button type="button" @click="activeFamily = 'flights'">← Wróć do odznak za loty</button>
        </section>
      </main>
    </section>
  </div>
</template>

<style scoped>
:global(*) {
  box-sizing: border-box;
}

.achievements-shell {
  position: fixed;
  inset: 0;
  z-index: 120;
  padding: 14px;
  background: rgba(15, 38, 61, 0.38);
  backdrop-filter: blur(9px);
  font-family: Inter, 'Segoe UI', Arial, sans-serif;
  color: #103667;
}

.achievements-panel {
  position: relative;
  display: grid;
  grid-template-columns: 248px minmax(0, 1fr);
  width: min(1540px, calc(100vw - 28px));
  height: calc(100vh - 28px);
  margin: 0 auto;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.88);
  border-radius: 22px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.99), rgba(241, 248, 253, 0.985));
  box-shadow: 0 32px 95px rgba(15, 40, 65, 0.3);
}

.achievements-close {
  position: absolute;
  right: 18px;
  top: 16px;
  z-index: 8;
  width: 42px;
  height: 42px;
  border: 1px solid #c8d8e6;
  border-radius: 11px;
  background: rgba(248, 252, 255, 0.92);
  color: #3d6489;
  cursor: pointer;
  font-size: 28px;
  font-weight: 300;
  line-height: 1;
  box-shadow: 0 6px 16px rgba(29, 67, 101, 0.08);
  transition: 0.18s ease;
}

.achievements-close:hover {
  background: #fff;
  color: #123e6c;
  transform: translateY(-1px);
}

.achievements-sidebar {
  overflow-y: auto;
  padding: 22px 14px 18px;
  border-right: 1px solid #dfeaf3;
  background: linear-gradient(180deg, #fbfdff 0%, #eef6fb 100%);
}

.achievements-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 8px 30px;
}

.achievements-brand__symbol {
  display: block;
  width: 50px;
  height: 49px;
  object-fit: contain;
  filter: drop-shadow(0 3px 5px rgba(0, 0, 0, 0.15));
}

.brand-board {
  display: grid;
  gap: 1px;
  text-align: left;
}

.brand-board__row {
  display: flex;
  gap: 2px;
}

.brand-board__row span {
  display: inline-flex;
  width: 21px;
  height: 22px;
  align-items: center;
  justify-content: center;
  border: 1px solid #111820;
  border-radius: 2px;
  background: linear-gradient(to bottom, #30343a 0%, #30343a 48%, #1f2227 49%, #1f2227 100%);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
  color: #f3f4f6;
  font-family: 'Courier New', ui-monospace, monospace;
  font-size: 15px;
  font-weight: 700;
  line-height: 1;
  text-shadow: 0 1px 1px rgba(0, 0, 0, 0.55);
}

.brand-board__empty {
  color: transparent !important;
}

.achievement-family-nav {
  display: grid;
  gap: 5px;
}

.achievement-family-link {
  display: grid;
  grid-template-columns: 34px 1fr;
  gap: 12px;
  align-items: center;
  width: 100%;
  min-height: 50px;
  padding: 8px 12px;
  border: 1px solid transparent;
  border-radius: 11px;
  background: transparent;
  color: #46688b;
  cursor: pointer;
  text-align: left;
  transition: 0.18s ease;
}

.achievement-family-link:hover {
  background: rgba(255, 255, 255, 0.74);
  transform: translateX(2px);
}

.achievement-family-link--active {
  border-color: #b8d5ec;
  background: linear-gradient(135deg, #e7f4ff, #dceefc);
  color: #0c4e8f;
  box-shadow: 0 6px 15px rgba(32, 91, 143, 0.07), inset 0 0 0 1px rgba(255, 255, 255, 0.82);
}

.achievement-family-link__icon {
  display: grid;
  width: 24px;
  height: 24px;
  place-items: center;
  color: #547ba1;
}

.achievement-family-link__icon :deep(svg) {
  width: 26px;
  height: 26px;
}

.achievement-family-link:nth-child(1) .achievement-family-link__icon :deep(svg),
.achievement-family-link:nth-child(5) .achievement-family-link__icon :deep(svg),
.achievement-family-link:nth-child(7) .achievement-family-link__icon :deep(svg) {
  width: 25px;
  height: 25px;
}

.achievement-family-link:nth-child(12) .achievement-family-link__icon :deep(svg) {
  width: 27px;
  height: 27px;
}

.achievement-family-link__copy {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.achievement-family-link__copy strong {
  color: inherit;
  font-size: 13px;
  font-weight: 720;
  line-height: 1.15;
}

.achievement-family-link__copy small {
  color: #49698b;
  font-size: 10.5px;
  font-weight: 450;
  line-height: 1.18;
}

.achievements-content {
  overflow-y: auto;
  padding: 24px 24px 18px;
  background:
    radial-gradient(circle at 74% -8%, rgba(210, 232, 248, 0.58), transparent 32%),
    linear-gradient(180deg, rgba(255, 255, 255, 0.86), rgba(246, 250, 253, 0.98));
}

.achievements-header {
  position: relative;
  min-height: 126px;
  overflow: hidden;
  margin: -4px 0 10px;
  border-radius: 16px;
  background: linear-gradient(90deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 251, 253, 0.96) 40%, rgba(231, 243, 251, 0.56) 100%);
  box-shadow: inset 0 -1px 0 rgba(98, 143, 181, 0.07);
}

.achievements-header__copy {
  position: relative;
  z-index: 2;
  width: 52%;
  padding: 12px 24px 12px 0;
  margin: 0;
  text-align: left;
}

.achievements-header__art {
  position: absolute;
  inset: 0 0 0 46%;
  background-image: var(--achievement-header-art);
  background-size: cover;
  background-position: center right;
  opacity: 0.98;
  pointer-events: none;
  -webkit-mask-image: linear-gradient(90deg, transparent 0%, rgba(0, 0, 0, 0.95) 22%, rgba(0, 0, 0, 1) 100%);
  mask-image: linear-gradient(90deg, transparent 0%, rgba(0, 0, 0, 0.95) 22%, rgba(0, 0, 0, 1) 100%);
}

.achievements-kicker {
  display: block;
  text-align: left;
  margin-bottom: 5px;
  color: #4e74a0;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.achievements-header h1 {
  margin: 0;
  text-align: left;
  color: #102f62;
  font-size: 43px;
  font-weight: 760;
  letter-spacing: -0.04em;
  line-height: 1;
}

.achievements-header p {
  margin: 6px 0 0;
  text-align: left;
  color: #66809c;
  font-size: 16px;
}

.achievements-loading,
.achievements-error {
  display: grid;
  min-height: 360px;
  place-items: center;
  color: #6c8195;
}

.achievements-error {
  color: #9a3b3b;
}

.achievement-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin: 6px 0 16px;
}

.achievement-summary-grid article {
  display: grid;
  grid-template-rows: auto auto auto;
  align-content: center;
  justify-items: center;
  min-height: 94px;
  padding: 13px 17px 12px;
  border: 1px solid #d8e5ef;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.88);
  box-shadow: 0 7px 20px rgba(25, 61, 96, 0.045);
  text-align: center;
}

.achievement-summary-grid__label {
  display: block;
  color: #657d98;
  font-size: 11px;
  line-height: 1.2;
}

.achievement-summary-grid article > strong {
  display: block;
  margin-top: 7px;
  color: #102f62;
  font-size: 26px;
  font-weight: 760;
  line-height: 1;
}

.achievement-summary-grid article > strong small {
  margin-left: 4px;
  color: #7f93a8;
  font-size: 11px;
  font-weight: 500;
}

.achievement-summary-grid__note {
  display: block;
  min-height: 12px;
  margin-top: 6px;
  color: #7f93a8;
  font-size: 10px;
  line-height: 1.2;
}

.achievement-workspace {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 330px;
  gap: 12px;
}

.achievement-collection,
.achievement-detail-card {
  border: 1px solid #d8e5ef;
  border-radius: 15px;
  background: rgba(255, 255, 255, 0.88);
  box-shadow: 0 8px 24px rgba(26, 61, 94, 0.045);
}

.achievement-collection {
  padding: 17px;
}

.achievement-family-heading {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: flex-start;
  padding: 1px 0;
  margin: 0;
  text-align: left;
}

.achievement-family-heading > div {
  width: 100%;
  margin: 0;
  text-align: left;
}

.achievement-family-heading h2 {
  margin: 0;
  text-align: left;
  color: #103667;
  font-size: 27px;
  font-weight: 720;
}

.achievement-family-heading p {
  margin: 6px 0 0;
  text-align: left;
  color: #71869b;
  font-size: 12px;
}

.achievement-progress-card {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 18px;
  align-items: center;
  margin: 14px 0;
  padding: 17px 18px;
  border: 1px solid #d7e5ef;
  border-radius: 12px;
  background: linear-gradient(180deg, #fff, #f9fcff);
}

.achievement-progress-card__count {
  display: grid;
  padding-right: 18px;
  border-right: 1px solid #e0eaf1;
}

.achievement-progress-card__count strong {
  color: #12396d;
  font-size: 30px;
  font-weight: 760;
  line-height: 1;
}

.achievement-progress-card__count span {
  margin-top: 6px;
  color: #6e8499;
  font-size: 11px;
}

.achievement-progress-card__labels {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  color: #68809a;
  font-size: 11px;
}

.achievement-progress-card__labels b {
  color: #12396d;
}

.achievement-progress-track {
  position: relative;
  height: 12px;
  margin: 10px 0 6px;
  overflow: visible;
  border-radius: 999px;
  background: #dfeaf2;
}

.achievement-progress-track__fill {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #277dce, #3d98eb);
  box-shadow: 0 0 9px rgba(46, 137, 221, 0.25);
}

.achievement-progress-plane {
  position: absolute;
  top: 50%;
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  color: #ffffff;
  background: linear-gradient(180deg, #3e98eb, #277dce);
  border: 1px solid rgba(30, 102, 170, 0.18);
  border-radius: 999px;
  transform: translateY(-50%);
  box-shadow: 0 2px 8px rgba(23, 72, 123, 0.22);
}

.achievement-progress-plane :deep(svg) {
  width: 19px;
  height: 19px;
}

.progress-plane {
  color: #fff;
}

.achievement-progress-value {
  display: block;
  color: #284d78;
  font-size: 11px;
  text-align: right;
}

.achievement-badge-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 10px;
}

.achievement-badge-card {
  position: relative;
  min-width: 0;
  padding: 10px 7px 9px;
  border: 1px solid #d9e5ee;
  border-radius: 12px;
  background: linear-gradient(180deg, #fff, #fbfdff);
  cursor: pointer;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.achievement-badge-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 24px rgba(24, 61, 96, 0.09);
}

.achievement-badge-card--selected {
  border-color: #3b95ea;
  box-shadow: 0 0 0 2px rgba(59, 149, 234, 0.14), 0 10px 24px rgba(24, 61, 96, 0.08);
}

.achievement-badge-picture {
  display: grid;
  width: min(126px, 100%);
  aspect-ratio: 1;
  place-items: center;
  margin: 0 auto 5px;
  border-radius: 50%;
  background: radial-gradient(circle, #fff 58%, #edf5fa 100%);
  box-shadow: 0 10px 22px rgba(19, 49, 76, 0.11);
}

.achievement-badge-picture img {
  display: block;
  width: 108%;
  height: 108%;
  object-fit: contain;
  clip-path: circle(47.5% at 50% 50%);
  filter: drop-shadow(0 5px 7px rgba(20, 48, 75, 0.15));
}

.achievement-badge-card--inactive .achievement-badge-picture {
  background: radial-gradient(circle, #f9fbfc, #e7edf1);
}

.achievement-badge-card--inactive .achievement-badge-picture img {
  filter: grayscale(1) saturate(0.25) opacity(0.66);
}

.achievement-badge-card > strong {
  display: block;
  color: #163c6e;
  font-size: 11px;
}

.badge-status {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 6px;
  font-size: 9.5px;
  font-weight: 600;
}

.badge-status__icon {
  display: inline-flex;
  width: 13px;
  height: 13px;
}

.badge-status__icon :deep(svg) {
  width: 13px;
  height: 13px;
}

.badge-status--active {
  color: #17985f;
}

.badge-status--inactive {
  color: #71879c;
}

.badge-status--locked {
  color: #8c9cac;
}

.achievement-badge-secret {
  position: relative;
  display: grid;
  width: min(126px, 100%);
  aspect-ratio: 1;
  place-items: center;
  margin: 0 auto 5px;
  border: 1px dashed #bed3e5;
  border-radius: 50%;
  background: radial-gradient(circle at 50% 38%, #f9fcff, #e8f2f9 70%, #deebf5);
  overflow: hidden;
  box-shadow: inset 0 0 0 8px rgba(255, 255, 255, 0.34);
}

.achievement-badge-secret::before,
.achievement-badge-secret::after {
  content: '';
  position: absolute;
  bottom: 25%;
  width: 47%;
  height: 28%;
  border-top: 2px solid rgba(132, 166, 194, 0.28);
  border-radius: 50%;
}

.achievement-badge-secret::before {
  left: 3%;
  transform: rotate(-18deg);
}

.achievement-badge-secret::after {
  right: 3%;
  transform: rotate(18deg);
}

.achievement-badge-secret__medallion {
  position: absolute;
  width: 62%;
  height: 62%;
  border: 1px solid rgba(138, 169, 195, 0.18);
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.62), rgba(213, 229, 240, 0.28));
}

.achievement-lock-icon {
  position: relative;
  z-index: 2;
  width: 38px;
  height: 38px;
  padding: 8px;
  border-radius: 11px;
  background: linear-gradient(145deg, #9bb2c6, #7f9bb3);
  color: #fff;
  filter: drop-shadow(0 5px 8px rgba(50, 77, 100, 0.18));
}

.achievement-lock-icon :deep(svg) {
  width: 100%;
  height: 100%;
}

.achievement-detail-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-height: 520px;
  padding: 18px;
  overflow: hidden;
  text-align: center;
  background: #ffffff;
}

.achievement-detail-card::before {
  content: none;
}

.achievement-detail-card__caption {
  position: relative;
  z-index: 2;
  align-self: flex-start;
  color: #193f70;
  font-size: 12px;
  font-weight: 700;
}

.achievement-detail-status {
  position: absolute;
  top: 18px;
  right: 18px;
  z-index: 3;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 12px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  box-shadow: 0 6px 16px rgba(30, 70, 104, 0.08);
}

.achievement-detail-status__icon {
  display: inline-flex;
  width: 14px;
  height: 14px;
}

.achievement-detail-status__icon :deep(svg) {
  width: 14px;
  height: 14px;
}

.achievement-detail-status--active {
  background: #e6f7ed;
  color: #0f8a58;
}

.achievement-detail-status--inactive {
  background: #edf1f4;
  color: #667b8e;
}

.achievement-detail-status--locked {
  background: #edf3f7;
  color: #7d91a4;
}

.achievement-detail-badge-stage {
  position: relative;
  z-index: 2;
  display: grid;
  width: min(240px, 88%);
  aspect-ratio: 1;
  place-items: center;
  margin: 15px auto 3px;
  border-radius: 50%;
  background: radial-gradient(circle, #fff 52%, rgba(228, 241, 250, 0.58) 74%, rgba(255, 255, 255, 0) 75%);
  filter: drop-shadow(0 14px 18px rgba(31, 60, 87, 0.16));
}

.achievement-detail-card__badge {
  display: block;
  width: 106%;
  height: 106%;
  object-fit: contain;
  clip-path: circle(47.5% at 50% 50%);
}

.achievement-detail-card__badge--inactive {
  filter: grayscale(1) saturate(0.25);
  opacity: 0.72;
}

.achievement-detail-card h3 {
  position: relative;
  z-index: 2;
  margin: 5px 0 6px;
  color: #0f3568;
  font-size: 25px;
}

.achievement-detail-name-wrap {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  margin-bottom: 8px;
}

.achievement-detail-name-wrap__line {
  width: 56px;
  height: 1px;
  background: linear-gradient(90deg, rgba(215, 178, 87, 0), rgba(215, 178, 87, 0.9), rgba(215, 178, 87, 0));
}


.achievement-detail-name {
  color: #9a6b23;
  font-family: Georgia, 'Times New Roman', serif;
  font-size: 17px;
  font-weight: 600;
}

.achievement-detail-card p {
  position: relative;
  z-index: 2;
  max-width: 250px;
  margin: 0 auto 10px;
  color: #71869a;
  font-size: 12px;
  line-height: 1.45;
}

.achievement-detail-meta {
  position: relative;
  z-index: 2;
  display: grid;
  grid-template-columns: 1fr 1fr;
  width: 100%;
  gap: 0;
  margin: 12px 0 14px;
  border-top: 1px solid rgba(194, 211, 224, 0.9);
}

.achievement-detail-meta__item {
  display: grid;
  grid-template-columns: 18px 1fr;
  align-items: center;
  column-gap: 6px;
  min-height: 46px;
  padding: 6px 9px 0;
  text-align: left;
}

.achievement-detail-meta__item + .achievement-detail-meta__item {
  border-left: 1px solid rgba(194, 211, 224, 0.8);
}

.achievement-detail-meta__icon {
  display: grid;
  width: 17px;
  height: 17px;
  place-items: center;
  color: #1f5ea2;
}

.achievement-detail-meta__icon :deep(svg) {
  width: 17px;
  height: 17px;
}

.achievement-detail-meta__item > div {
  display: grid;
  gap: 2px;
  align-content: center;
  min-width: 0;
}

.achievement-detail-meta__item span {
  display: block;
  color: #8597a8;
  font-size: 9px;
  line-height: 1.05;
  text-align: left;
}

.achievement-detail-meta__item strong {
  display: block;
  margin-top: 0;
  color: #244a75;
  font-size: 11px;
  line-height: 1.1;
  text-align: left;
}

.achievement-share-primary,
.achievement-share-secondary {
  position: relative;
  z-index: 2;
  width: 100%;
  min-height: 43px;
  border-radius: 9px;
  cursor: pointer;
  font-weight: 650;
}

.achievement-share-primary {
  margin-top: auto;
  border: 1px solid #165799;
  background: linear-gradient(180deg, #246db1, #15518c);
  color: #fff;
  box-shadow: 0 8px 18px rgba(21, 81, 140, 0.12);
}

.achievement-share-secondary {
  margin-top: 7px;
  border: 1px solid #c7d9e7;
  background: rgba(250, 253, 255, 0.88);
  color: #28537d;
}

.achievement-detail-secret {
  position: relative;
  z-index: 2;
  display: grid;
  width: 190px;
  aspect-ratio: 1;
  place-items: center;
  margin: 52px auto 12px;
  border: 1px dashed #bed3e5;
  border-radius: 50%;
  background: radial-gradient(circle, #f9fcff, #e5f0f8);
  box-shadow: inset 0 0 0 10px rgba(255, 255, 255, 0.42);
}

.achievement-detail-lock {
  width: 62px;
  height: 62px;
  padding: 14px;
  border-radius: 15px;
  background: linear-gradient(145deg, #9db4c8, #7e9ab3);
  color: #fff;
  filter: drop-shadow(0 8px 11px rgba(53, 79, 101, 0.18));
}

.achievement-detail-lock :deep(svg) {
  width: 100%;
  height: 100%;
}

.achievement-secret-hint {
  position: relative;
  z-index: 2;
  margin-top: auto;
  padding: 14px;
  color: #547ca3;
  font-family: 'Segoe Print', 'Lucida Handwriting', 'Bradley Hand', cursive;
  font-size: 17px;
  font-weight: 600;
  font-style: italic;
  transform: rotate(-3deg);
}

.achievement-legend {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 260px;
  gap: 14px;
  align-items: stretch;
  margin-top: 12px;
  padding: 12px 15px;
  border: 1px solid #d8e5ef;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.76);
  box-shadow: 0 6px 18px rgba(26, 61, 94, 0.035);
}

.achievement-legend__list {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.achievement-legend__item {
  display: flex;
  align-items: center;
  gap: 11px;
}

.achievement-legend__item p {
  margin: 0;
}

.achievement-legend__item strong,
.achievement-legend__item small {
  display: block;
}

.achievement-legend__item strong {
  color: #214a78;
  font-size: 10px;
}

.achievement-legend__item small {
  margin-top: 2px;
  color: #7d91a4;
  font-size: 10px;
}

.achievement-legend__symbol {
  display: grid;
  flex: 0 0 42px;
  width: 42px;
  height: 42px;
  place-items: center;
  border-radius: 50%;
}

.achievement-legend__symbol :deep(svg) {
  width: 26px;
  height: 26px;
}

.achievement-legend__symbol--active {
  background: #e7f7ee;
  color: #0f8a58;
  border: 1px solid #badeca;
}

.achievement-legend__symbol--inactive {
  background: #eef2f6;
  color: #7b8fa3;
  border: 1px solid #cfd9e3;
}

.achievement-legend__symbol--locked {
  background: #eff4f8;
  color: #88a0b6;
  border: 1px dashed #bed0de;
}

.achievement-legend__note {
  display: flex;
  align-items: flex-end;
  justify-content: flex-end;
  gap: 6px;
  padding-right: 6px;
}

.achievement-legend__note-image {
  display: block;
  width: 212px;
  max-width: 100%;
  height: auto;
}

.achievement-legend__note-plane {
  width: 24px;
  height: 24px;
  color: #a9c2dd;
  transform: translateY(10px);
}

.achievement-legend__note-plane :deep(svg) {
  width: 24px;
  height: 24px;
}

.achievement-family-future {
  display: grid;
  min-height: 520px;
  place-items: center;
  align-content: center;
  text-align: center;
}

.achievement-family-future__symbol {
  display: grid;
  width: 100px;
  height: 100px;
  place-items: center;
  border-radius: 50%;
  background: #e9f3fb;
  color: #5e82a6;
}

.achievement-family-future__symbol :deep(svg) {
  width: 48px;
  height: 48px;
}

.achievement-family-future > span {
  margin-top: 18px;
  color: #7d91a5;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.achievement-family-future h2 {
  margin: 5px 0;
  color: #173d6e;
  font-size: 32px;
}

.achievement-family-future p {
  max-width: 520px;
  color: #73889c;
  line-height: 1.5;
}

.achievement-family-future button {
  margin-top: 16px;
  padding: 10px 16px;
  border: 1px solid #bdd2e3;
  border-radius: 8px;
  background: #f8fbfe;
  color: #28537d;
  cursor: pointer;
}

@media (max-width: 1280px) {
  .achievements-panel {
    grid-template-columns: 210px minmax(0, 1fr);
  }

  .achievement-workspace {
    grid-template-columns: minmax(0, 1fr) 285px;
  }

  .achievement-badge-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .achievement-summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .achievement-legend {
    grid-template-columns: 1fr;
  }

  .achievement-legend__list {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .achievement-legend__note {
    justify-content: flex-end;
    padding-top: 4px;
  }
}

@media (max-width: 900px) {
  .achievements-shell {
    padding: 0;
  }

  .achievements-panel {
    grid-template-columns: 1fr;
    width: 100vw;
    height: 100vh;
    border-radius: 0;
  }

  .achievements-sidebar {
    display: none;
  }

  .achievements-content {
    padding: 18px;
  }

  .achievements-header {
    min-height: 110px;
  }

  .achievements-header__art {
    inset: 0 0 0 28%;
    opacity: 0.78;
  }

  .achievement-workspace {
    grid-template-columns: 1fr;
  }

  .achievement-detail-card {
    min-height: 440px;
  }

  .achievement-badge-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .achievement-progress-card {
    grid-template-columns: 1fr;
  }

  .achievement-progress-card__count {
    border-right: 0;
    border-bottom: 1px solid #e1eaf1;
    padding: 0 0 10px;
  }

  .achievement-legend__list {
    grid-template-columns: 1fr;
  }

  .achievement-legend__note {
    justify-content: center;
    text-align: center;
  }
}
</style>
