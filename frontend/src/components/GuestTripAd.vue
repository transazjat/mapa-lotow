<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

interface Offer {
  id: string
  title: string
  days: number
  date_text: string
  status: 'zapisy' | 'potwierdzony' | 'promocja'
  image: string | null
  url: string
}

const offers = ref<Offer[]>([])
const activeIndex = ref(0)
const visible = ref(true)

let rotationTimer: ReturnType<typeof setInterval> | null = null
let transitionTimer: ReturnType<typeof setTimeout> | null = null

const currentOffer = computed(() => offers.value[activeIndex.value] ?? null)

const statusLabel = computed(() => {
  if (currentOffer.value?.status === 'potwierdzony') return 'Potwierdzony'
  if (currentOffer.value?.status === 'promocja') return 'Promocja'
  return 'Zapisy'
})


const currentImage = computed(() => {
  const image = currentOffer.value?.image

  if (!image) return null

  return image.replace(
    /-min(?=\.(?:jpe?g|png|webp)(?:\?|$))/i,
    '',
  )
})

function shuffle<T>(items: T[]): T[] {
  const result = [...items]

  for (let i = result.length - 1; i > 0; i -= 1) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[result[i], result[j]] = [result[j], result[i]]
  }

  return result
}

function setOffer(index: number): void {
  if (offers.value.length < 2) return

  visible.value = false

  if (transitionTimer) clearTimeout(transitionTimer)

  transitionTimer = setTimeout(() => {
    activeIndex.value = (index + offers.value.length) % offers.value.length
    visible.value = true
  }, 180)
}

function nextOffer(): void {
  setOffer(activeIndex.value + 1)
}

function previousOffer(): void {
  setOffer(activeIndex.value - 1)
}

function startRotation(): void {
  if (rotationTimer) clearInterval(rotationTimer)

  if (offers.value.length > 1) {
    rotationTimer = setInterval(nextOffer, 30_000)
  }
}

async function loadOffers(): Promise<void> {
  try {
    const response = await fetch('/api/transazja/offers', {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    })

    if (!response.ok) return

    const data = await response.json() as {
      offers?: Offer[]
    }

    offers.value = shuffle(
      (data.offers ?? []).filter(
        (offer) =>
          ['zapisy', 'potwierdzony', 'promocja'].includes(offer.status),
      ),
    )

    activeIndex.value = 0
    startRotation()
  } catch {
    offers.value = []
  }
}

onMounted(() => {
  void loadOffers()
})

onBeforeUnmount(() => {
  if (rotationTimer) clearInterval(rotationTimer)
  if (transitionTimer) clearTimeout(transitionTimer)
})
</script>

<template>
  <div v-if="currentOffer" class="guest-trip-ad">
    <a
      :href="currentOffer.url"
      class="guest-trip-ad__card"
      :class="{ 'guest-trip-ad__card--visible': visible }"
      target="_blank"
      rel="noopener noreferrer"
    >
      <div class="guest-trip-ad__media">
        <img
          v-if="currentImage"
          :src="currentImage"
          :alt="currentOffer.title"
        >
        <div v-else class="guest-trip-ad__fallback">✈</div>

        <span
          class="guest-trip-ad__status"
          :class="`guest-trip-ad__status--${currentOffer.status}`"
        >
          {{ statusLabel }}
        </span>
      </div>

      <div class="guest-trip-ad__content">
        <span class="guest-trip-ad__eyebrow">Polecany wyjazd TransAzji</span>
        <strong>{{ currentOffer.title }}, {{ currentOffer.days }} dni</strong>
        <span class="guest-trip-ad__date">{{ currentOffer.date_text }}</span>
        <span class="guest-trip-ad__cta">Zobacz szczegóły →</span>
      </div>
    </a>

    <div v-if="offers.length > 1" class="guest-trip-ad__controls">
      <button type="button" aria-label="Poprzedni wyjazd" @click="previousOffer">‹</button>

      <div class="guest-trip-ad__dots">
        <button
          v-for="(_, index) in offers"
          :key="index"
          type="button"
          :class="{ active: index === activeIndex }"
          :aria-label="`Pokaż wyjazd ${index + 1}`"
          @click="setOffer(index)"
        ></button>
      </div>

      <button type="button" aria-label="Następny wyjazd" @click="nextOffer">›</button>
    </div>
  </div>
</template>

<style scoped>
.guest-trip-ad {
  width: 100%;
}

.guest-trip-ad__card {
  display: grid;
  grid-template-columns: 180px minmax(0, 1fr);
  min-height: 150px;
  max-width: 660px;
  margin: 0 auto;
  overflow: hidden;
  border: 1px solid rgba(11, 45, 92, 0.16);
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.98);
  color: inherit;
  opacity: 0;
  text-decoration: none;
  box-shadow: 0 10px 26px rgba(15, 23, 42, 0.11);
  transition:
    opacity .18s ease;
}

.guest-trip-ad__card--visible {
  opacity: 1;
}

.guest-trip-ad__card:hover {
  transform: none;
  box-shadow: 0 10px 26px rgba(15, 23, 42, 0.11);
}

.guest-trip-ad__media {
  position: relative;
  min-height: 150px;
  overflow: hidden;
  background: #e8edf3;
}

.guest-trip-ad__media img {
  display: block;
  width: 100%;
  height: 100%;
  min-height: 150px;
  object-fit: cover;
}

.guest-trip-ad__fallback {
  display: grid;
  min-height: 150px;
  place-items: center;
  color: #0b2d5c;
  font-size: 54px;
}

.guest-trip-ad__status {
  position: absolute;
  left: 12px;
  top: 12px;
  padding: 5px 9px;
  border-radius: 999px;
  color: #fff;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: .04em;
  text-transform: uppercase;
}

.guest-trip-ad__status--potwierdzony { background: #179b67; }
.guest-trip-ad__status--promocja { background: #d97a19; }
.guest-trip-ad__status--zapisy { background: #0b6fb5; }

.guest-trip-ad__content {
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 18px 22px;
}

.guest-trip-ad__eyebrow {
  margin-bottom: 9px;
  color: #8995a2;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: .05em;
  text-transform: uppercase;
}

.guest-trip-ad__content strong {
  color: #0b2d5c;
  font-size: clamp(20px, 2vw, 27px);
  line-height: 1.05;
}

.guest-trip-ad__date {
  margin-top: 8px;
  color: #687483;
  font-size: 13px;
}

.guest-trip-ad__cta {
  align-self: flex-start;
  margin-top: 14px;
  padding: 8px 11px;
  border: 1px solid #cad4de;
  border-radius: 9px;
  color: #0b2d5c;
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
}

.guest-trip-ad__controls {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-top: 8px;
}

.guest-trip-ad__controls > button {
  width: 30px;
  height: 30px;
  border: 1px solid #d8dfe6;
  border-radius: 8px;
  background: rgba(255,255,255,.92);
  color: #687483;
  cursor: pointer;
  font-size: 18px;
}

.guest-trip-ad__dots {
  display: flex;
  gap: 5px;
}

.guest-trip-ad__dots button {
  width: 7px;
  height: 7px;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: #cbd3db;
  cursor: pointer;
}

.guest-trip-ad__dots button.active {
  width: 20px;
  background: #0b2d5c;
}

@media (max-width: 760px) {
  .guest-trip-ad__card {
    grid-template-columns: 1fr;
  }

  .guest-trip-ad__media,
  .guest-trip-ad__media img,
  .guest-trip-ad__fallback {
    min-height: 190px;
  }

  .guest-trip-ad__content {
    padding: 24px;
  }
}
</style>
