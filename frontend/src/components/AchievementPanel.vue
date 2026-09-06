<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
} from 'vue'

import { getAchievements } from '../services/achievementsApi'
import type {
  AchievementItem,
  AchievementSummary,
  AchievementsResponse,
} from '../types/achievement'
import { downloadAchievementCard } from '../utils/achievementCard'
import {
  getAirportBadgeImage,
  getContinentBadgeImage,
  getCountryBadgeImage,
  getDistanceBadgeImage,
  getFlightBadgeImage,
} from '../utils/achievementBadges'

import AchievementSidebar from './achievements/AchievementSidebar.vue'
import AchievementHeader from './achievements/AchievementHeader.vue'
import AchievementSummaryCards from './achievements/AchievementSummaryCards.vue'
import AchievementProgressPanel from './achievements/AchievementProgressPanel.vue'
import AchievementBadgeGrid from './achievements/AchievementBadgeGrid.vue'
import AchievementDetailPanel from './achievements/AchievementDetailPanel.vue'
import AchievementLegend from './achievements/AchievementLegend.vue'
import AchievementFamilyPlaceholder from './achievements/AchievementFamilyPlaceholder.vue'

import { achievementFamilies } from './achievements/achievementUi'
import './achievements/achievementShared.css'

const props = defineProps<{ nick: string }>()
const emit = defineEmits<{ close: [] }>()

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

const distanceBadgeNames: Record<number, string> = {
  10000: 'Ćwierć świata',
  25000: 'Ponad pół świata',
  50000: 'Więcej niż obwód Ziemi',
  100000: 'Ponad dwa obwody Ziemi',
  250000: 'Ponad sześć razy wokół Ziemi',
  500000: 'Pół miliona kilometrów w powietrzu',
  750000: 'Osiemnaście obwodów Ziemi',
  1000000: 'Milion kilometrów w powietrzu',
  1500000: 'Półtora miliona kilometrów w powietrzu',
  2000000: 'Pięćdziesiąt obwodów Ziemi',
}

const distanceBadgeDescriptions: Record<number, string> = {
  10000: 'Pierwsze 10 000 kilometrów w powietrzu. To już dystans odpowiadający mniej więcej jednej czwartej obwodu Ziemi.',
  25000: 'Łączny dystans przekroczył 25 000 kilometrów. Twoje podróże sięgają już dalej niż połowa obwodu naszej planety.',
  50000: 'Przekroczyłeś 50 000 kilometrów w powietrzu - więcej niż wynosi pełny obwód Ziemi.',
  100000: 'Sto tysięcy kilometrów w powietrzu to już ponad dwa pełne okrążenia naszej planety.',
  250000: 'Ćwierć miliona kilometrów podróży lotniczych. To dystans odpowiadający ponad sześciu podróżom dookoła świata.',
  500000: 'Pół miliona kilometrów przebytych samolotem. Jeden z najważniejszych kamieni milowych całej serii.',
  750000: 'Siedemset pięćdziesiąt tysięcy kilometrów to dystans zbliżony do osiemnastu pełnych okrążeń Ziemi.',
  1000000: 'Milion kilometrów przebytych samolotem. Osiągnięcie, które pokazuje prawdziwie globalną skalę podróży.',
  1500000: 'Półtora miliona kilometrów lotniczych podróży. To poziom zarezerwowany dla wyjątkowo intensywnego podróżowania.',
  2000000: 'Dwa miliony kilometrów w powietrzu - dystans odpowiadający około pięćdziesięciu podróżom dookoła Ziemi.',
}

const airportBadgeNames: Record<number, string> = {
  5: 'Pierwsze punkty na mapie',
  10: 'Coraz więcej kierunków',
  25: 'Sieć połączeń',
  50: 'Pół setki miejsc',
  75: 'Mapa się zapełnia',
  100: 'Sto lotnisk świata',
  125: 'Szeroki zasięg',
  150: 'Między portami świata',
  200: 'Dwieście lotnisk na mapie',
  250: 'Świat pełen punktów',
}

const airportBadgeDescriptions: Record<number, string> = {
  5: 'Pierwsze pięć różnych lotnisk odwiedzonych podczas podróży. Mapa zaczyna wypełniać się własnymi punktami.',
  10: 'Dziesięć różnych lotnisk to już wyraźnie szersza sieć podróży i więcej miejsc, z których zaczyna się lub kończy lot.',
  25: 'Dwadzieścia pięć różnych lotnisk tworzy już własną, rozpoznawalną sieć podróży.',
  50: 'Pięćdziesiąt różnych lotnisk na Twojej mapie. To już solidny zestaw odwiedzonych portów lotniczych.',
  75: 'Siedemdziesiąt pięć różnych lotnisk sprawia, że mapa podróży staje się coraz gęstsza i bardziej różnorodna.',
  100: 'Sto różnych lotnisk to ważny kamień milowy i wyraźny znak naprawdę szerokiego zasięgu podróży.',
  125: 'Sto dwadzieścia pięć różnych lotnisk pokazuje, jak daleko rozrosła się Twoja osobista mapa podróży.',
  150: 'Sto pięćdziesiąt różnych lotnisk oznacza podróże przez wiele regionów, krajów i dużych portów lotniczych.',
  200: 'Dwieście różnych lotnisk to już imponująca, globalna sieć miejsc odwiedzonych drogą lotniczą.',
  250: 'Dwieście pięćdziesiąt różnych lotnisk na mapie. To poziom, na którym mapa podróży staje się prawdziwie światowa.',
}

const countryBadgeNames: Record<number, string> = {
  5: 'Pięć krajów na mapie',
  10: 'Coraz więcej krajów',
  15: 'Szerzej po świecie',
  20: 'Świat nabiera kształtu',
  25: 'Ćwierć setki krajów',
  30: 'W wielu częściach świata',
  40: 'Czterdzieści krajów',
  50: 'Pół setki krajów świata',
  75: 'Wielki zasięg podróży',
  100: 'Świat bez granic',
}

const countryBadgeDescriptions: Record<number, string> = {
  5: 'Pierwsze pięć odwiedzonych państw. To moment, w którym osobista mapa podróży zaczyna wyraźnie wychodzić poza jeden kierunek.',
  10: 'Dziesięć odwiedzonych państw to już wyraźnie szerszy zasięg podróży i coraz bardziej różnorodna mapa doświadczeń.',
  15: 'Piętnaście państw oznacza, że podróże zaczynają obejmować coraz dalsze regiony i kolejne części świata.',
  20: 'Dwadzieścia odwiedzonych państw sprawia, że Twoja mapa staje się coraz pełniejsza i bardziej globalna.',
  25: 'Dwadzieścia pięć państw to ważny kamień milowy i wyraźny znak szerokiego doświadczenia podróżniczego.',
  30: 'Trzydzieści odwiedzonych państw pokazuje coraz większy zasięg i różnorodność podróży.',
  40: 'Czterdzieści państw na mapie to już imponujący zbiór odwiedzonych miejsc i regionów.',
  50: 'Pięćdziesiąt odwiedzonych państw to jeden z najważniejszych kamieni milowych całej serii.',
  75: 'Siedemdziesiąt pięć państw oznacza naprawdę globalny zasięg i podróże przez bardzo wiele regionów świata.',
  100: 'Sto odwiedzonych państw to wyjątkowe osiągnięcie i symbol niezwykle szerokiego doświadczenia podróżniczego.',
}

const continentBadgeNames: Record<number, string> = {
  1: 'Pierwszy kontynent',
  2: 'Dwa kontynenty',
  3: 'Trzy kontynenty',
  4: 'Cztery kontynenty',
  5: 'Pięć kontynentów',
  6: 'Sześć kontynentów',
  7: 'Siedem kontynentów',
}

const continentBadgeDescriptions: Record<number, string> = {
  1: 'Początek kolekcji kontynentów - na odznace wyróżniona jest Europa, czyli naturalny pierwszy krok na mapie podróży większości polskich użytkowników.',
  2: 'Dwa kontynenty na Twojej mapie. Odznaka podkreśla Europę i Azję jako pierwszy wyraźny krok poza jeden region świata.',
  3: 'Trzy kontynenty na mapie podróży - Europa, Azja i Afryka tworzą już bardzo wyraźny międzyregionalny zasięg.',
  4: 'Cztery kontynenty to poziom, na którym do Europy, Azji i Afryki dołącza Ameryka Północna.',
  5: 'Pięć kontynentów na jednej odznace. Do wcześniejszych kierunków dochodzi Ameryka Południowa, a mapa staje się naprawdę globalna.',
  6: 'Sześć kontynentów to niemal pełna mapa świata - odznaka obejmuje już także Australię i Oceanię.',
  7: 'Najwyższa odznaka serii. Na mapie zaznaczone są wszystkie kontynenty, łącznie z Antarktydą.',
}

const numberFormatter = new Intl.NumberFormat('pl-PL')

function formatContinentLabel(value: number): string {
  const mod10 = value % 10
  const mod100 = value % 100

  const suffix = value === 1
    ? 'kontynent'
    : (mod10 >= 2 && mod10 <= 4 && !(mod100 >= 12 && mod100 <= 14))
      ? 'kontynenty'
      : 'kontynentów'

  return `${numberFormatter.format(value)} ${suffix}`
}

interface FamilyViewState {
  completedValue: number
  achievements: AchievementItem[]
  summary: AchievementSummary
}

const familyState = computed<FamilyViewState | null>(() => {
  const state = data.value
  if (!state) return null

  if (activeFamily.value === 'distance') {
    return {
      completedValue: state.distance.completed_distance_km,
      achievements: state.distance.achievements,
      summary: state.distance.summary,
    }
  }

  if (activeFamily.value === 'airports') {
    return {
      completedValue: state.airports.completed_airports,
      achievements: state.airports.achievements,
      summary: state.airports.summary,
    }
  }

  if (activeFamily.value === 'countries') {
    return {
      completedValue: state.countries.completed_countries,
      achievements: state.countries.achievements,
      summary: state.countries.summary,
    }
  }

  if (activeFamily.value === 'continents') {
    return {
      completedValue: state.continents.completed_continents,
      achievements: state.continents.achievements,
      summary: state.continents.summary,
    }
  }

  if (activeFamily.value === 'flights') {
    return {
      completedValue: state.completed_flights,
      achievements: state.achievements,
      summary: state.summary,
    }
  }

  return null
})

const achievements = computed(() => familyState.value?.achievements ?? [])

const selectedAchievement = computed<AchievementItem | null>(() => {
  const key = selectedKey.value
  const state = familyState.value
  if (!state) return null

  if (key) {
    const selected = state.achievements.find((item) => item.key === key)
    if (selected) return selected
  }

  const lastEarnedKey = state.summary.last_earned?.key
  if (lastEarnedKey) {
    return state.achievements.find((item) => item.key === lastEarnedKey) ?? null
  }

  return state.achievements.find((item) => item.earned) ?? state.achievements[0] ?? null
})

const futureFamily = computed(
  () => achievementFamilies.find((item) => item.key === activeFamily.value) ?? achievementFamilies[0],
)

const progressPercent = computed(() => {
  const state = familyState.value
  if (!state) return 0

  const next = state.summary.next_threshold
  if (!next) return 100

  const previous = [...state.achievements]
    .reverse()
    .find((item) => item.threshold <= state.completedValue)?.threshold ?? 0

  const segment = next - previous
  if (segment <= 0) return 100

  return Math.max(0, Math.min(100, ((state.completedValue - previous) / segment) * 100))
})

const currentThreshold = computed(() => {
  const state = familyState.value
  if (!state) return null

  return [...state.achievements]
    .reverse()
    .find((item) => item.active)?.threshold ?? null
})

const currentFamilyIcon = computed(
  () => achievementFamilies.find((item) => item.key === activeFamily.value)?.icon
    ?? achievementFamilies[0].icon,
)

function formatDate(value: string | null): string {
  if (!value) return '—'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  const months = [
    'sty', 'lut', 'mar', 'kwi', 'maj', 'cze',
    'lip', 'sie', 'wrz', 'paź', 'lis', 'gru',
  ]

  return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`
}

function getBadgeName(threshold: number): string {
  if (activeFamily.value === 'distance') {
    return distanceBadgeNames[threshold] ?? `${numberFormatter.format(threshold)} km`
  }
  if (activeFamily.value === 'airports') {
    return airportBadgeNames[threshold] ?? `${numberFormatter.format(threshold)} lotnisk`
  }
  if (activeFamily.value === 'countries') {
    return countryBadgeNames[threshold] ?? `${numberFormatter.format(threshold)} państw`
  }
  if (activeFamily.value === 'continents') {
    return continentBadgeNames[threshold] ?? formatContinentLabel(threshold)
  }
  return flightBadgeNames[threshold] ?? `${threshold} lotów`
}

function getBadgeDescription(threshold: number): string {
  if (activeFamily.value === 'distance') {
    return distanceBadgeDescriptions[threshold] ?? 'Kolejny kamień milowy Twoich podróży lotniczych.'
  }
  if (activeFamily.value === 'airports') {
    return airportBadgeDescriptions[threshold] ?? 'Kolejny punkt na Twojej osobistej mapie podróży.'
  }
  if (activeFamily.value === 'countries') {
    return countryBadgeDescriptions[threshold] ?? 'Kolejny kraj na Twojej osobistej mapie świata.'
  }
  if (activeFamily.value === 'continents') {
    return continentBadgeDescriptions[threshold] ?? 'Kolejny kontynent na Twojej osobistej mapie świata.'
  }
  return 'Symbol Twojej pasji do podróżowania i odkrywania świata.'
}

function formatThreshold(threshold: number): string {
  if (activeFamily.value === 'distance') return `${numberFormatter.format(threshold)} km`
  if (activeFamily.value === 'airports') return `${numberFormatter.format(threshold)} lotnisk`
  if (activeFamily.value === 'countries') return `${numberFormatter.format(threshold)} państw`
  if (activeFamily.value === 'continents') return formatContinentLabel(threshold)
  return `${threshold} lotów`
}

function getBadgeImage(threshold: number): string | null {
  if (activeFamily.value === 'distance') return getDistanceBadgeImage(threshold)
  if (activeFamily.value === 'airports') return getAirportBadgeImage(threshold)
  if (activeFamily.value === 'countries') return getCountryBadgeImage(threshold)
  if (activeFamily.value === 'continents') return getContinentBadgeImage(threshold)
  return getFlightBadgeImage(threshold)
}

function formatCurrentValue(value: number): string {
  if (activeFamily.value === 'distance') return `${numberFormatter.format(value)} km`
  if (activeFamily.value === 'airports') return `${numberFormatter.format(value)} lotnisk`
  if (activeFamily.value === 'countries') return `${numberFormatter.format(value)} państw`
  if (activeFamily.value === 'continents') return formatContinentLabel(value)
  return `${numberFormatter.format(value)} lotów`
}

function currentValueCaption(): string {
  if (activeFamily.value === 'distance') return 'Twój łączny dystans przebyty w powietrzu'
  if (activeFamily.value === 'airports') return 'Liczba różnych odwiedzonych lotnisk'
  if (activeFamily.value === 'countries') return 'Liczba różnych odwiedzonych państw'
  if (activeFamily.value === 'continents') return 'Liczba odwiedzonych kontynentów'
  return 'Twoja łączna liczba odbytych lotów'
}

function selectFamily(key: string): void {
  activeFamily.value = key
  selectedKey.value = null
}

function selectAchievement(item: AchievementItem): void {
  selectedKey.value = item.key
}

async function exportCard(format: 'png' | 'jpg'): Promise<void> {
  const item = selectedAchievement.value
  const state = familyState.value
  if (!item || !item.earned || !state) return

  exporting.value = format
  try {
    await downloadAchievementCard(item, state.completedValue, props.nick, format)
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
      ?? response.achievements.find((item) => item.earned)?.key
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
  <div class="achievements-shell" role="dialog" aria-modal="true" aria-label="Osiągnięcia">
    <section class="achievements-panel">
      <button class="achievements-close" type="button" aria-label="Zamknij osiągnięcia" @click="emit('close')">×</button>

      <AchievementSidebar
        :families="achievementFamilies"
        :active-family="activeFamily"
        @select="selectFamily"
      />

      <main class="achievements-content">
        <AchievementHeader />

        <div v-if="loading" class="achievements-loading">Ładowanie osiągnięć…</div>
        <div v-else-if="error" class="achievements-error">{{ error }}</div>

        <template v-else-if="data && familyState && (activeFamily === 'flights' || activeFamily === 'distance' || activeFamily === 'airports' || activeFamily === 'countries' || activeFamily === 'continents')">
          <AchievementSummaryCards
            :earned-value="`${familyState.summary.earned_count} z ${familyState.achievements.length}`"
            :active-value="`${familyState.summary.active_count} z ${familyState.summary.earned_count}`"
            :last-earned-value="familyState.summary.last_earned ? formatThreshold(familyState.summary.last_earned.threshold) : '—'"
            :last-earned-note="formatDate(familyState.summary.last_earned?.earned_at ?? null)"
            :next-threshold-value="familyState.summary.next_threshold ? formatThreshold(familyState.summary.next_threshold) : 'Seria ukończona'"
            :next-threshold-note="familyState.summary.remaining_to_next !== null ? `Pozostało ${formatThreshold(familyState.summary.remaining_to_next)}` : ''"
          />

          <section class="achievement-workspace">
            <div class="achievement-collection">
              <div class="achievement-family-heading">
                <div v-if="activeFamily === 'flights'">
                  <h2>Loty</h2>
                  <p>Liczba odbytych lotów. Każdy lot to nowa historia, nowe miejsce i nowe możliwości.</p>
                </div>
                <div v-else-if="activeFamily === 'distance'">
                  <h2>Dystans</h2>
                  <p>Łączny dystans przebyty podczas wszystkich Twoich lotów. Każdy kilometr przybliża Cię do kolejnego podróżniczego kamienia milowego.</p>
                </div>
                <div v-else-if="activeFamily === 'airports'">
                  <h2>Lotniska</h2>
                  <p>Liczba różnych lotnisk, z których rozpoczynały się lub na których kończyły się Twoje loty. Każdy nowy port to kolejny punkt na osobistej mapie podróży.</p>
                </div>
                <div v-else-if="activeFamily === 'countries'">
                  <h2>Państwa</h2>
                  <p>Liczba różnych państw odwiedzonych podczas Twoich podróży lotniczych. Każdy kolejny kraj poszerza Twoją osobistą mapę świata.</p>
                </div>
                <div v-else>
                  <h2>Kontynenty</h2>
                  <p>Liczba kontynentów obecnych na Twojej mapie lotniczych podróży. Każda kolejna część świata zwiększa globalny zasięg Twoich wypraw.</p>
                </div>
              </div>

              <AchievementProgressPanel
                :current-value="formatCurrentValue(familyState.completedValue)"
                :current-value-caption="currentValueCaption()"
                :current-badge-label="currentThreshold ? formatThreshold(currentThreshold) : 'jeszcze żadna'"
                :next-badge-label="familyState.summary.next_threshold ? formatThreshold(familyState.summary.next_threshold) : 'ukończono serię'"
                :progress-value-label="familyState.summary.next_threshold ? `${numberFormatter.format(familyState.completedValue)} / ${numberFormatter.format(familyState.summary.next_threshold)}` : numberFormatter.format(familyState.completedValue)"
                :progress-percent="progressPercent"
              />

              <AchievementBadgeGrid
                :achievements="achievements"
                :selected-key="selectedAchievement?.key ?? null"
                :get-badge-name="getBadgeName"
                :get-badge-image="getBadgeImage"
                :format-threshold="formatThreshold"
                @select="selectAchievement"
              />
            </div>

            <AchievementDetailPanel
              :selected-achievement="selectedAchievement"
              :current-value-label="formatCurrentValue(familyState.completedValue)"
              :current-metric-icon="currentFamilyIcon"
              :detail-description="selectedAchievement ? getBadgeDescription(selectedAchievement.threshold) : ''"
              :exporting="exporting"
              :get-badge-name="getBadgeName"
              :get-badge-image="getBadgeImage"
              :format-threshold="formatThreshold"
              :format-date="formatDate"
              @export="exportCard"
            />
          </section>

          <AchievementLegend />
        </template>

        <AchievementFamilyPlaceholder
          v-else
          :label="futureFamily.label"
          :icon="futureFamily.icon"
          @back="activeFamily = 'flights'"
        />
      </main>
    </section>
  </div>
</template>
