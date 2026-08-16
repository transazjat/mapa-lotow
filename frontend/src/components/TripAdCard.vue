<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  onMounted,
  ref,
} from 'vue'

import {
  getTransAzjaOffers,
} from '../services/api'

import type {
  TransAzjaOffer,
} from '../services/api'


const ROTATION_MS =
  30_000


const emit = defineEmits<{
  availability: [
    available: boolean,
  ]
}>()


const offers =
  ref<TransAzjaOffer[]>(
    [],
  )


const activeIndex =
  ref(
    0,
  )


const visible =
  ref(
    true,
  )


let rotationTimer:
  ReturnType<
    typeof setInterval
  >
  | null =
  null


let transitionTimer:
  ReturnType<
    typeof setTimeout
  >
  | null =
  null


const currentOffer =
  computed(
    () =>
      offers.value[
        activeIndex.value
      ] ??
      null,
  )


const statusLabel =
  computed(
    () => {
      const status =
        currentOffer.value
          ?.status

      if (
        status ===
        'potwierdzony'
      ) {
        return 'Potwierdzony'
      }

      if (
        status ===
        'promocja'
      ) {
        return 'Promocja'
      }

      return 'Zapisy'
    },
  )


function shuffle<T>(
  input: T[],
): T[] {
  const result =
    [...input]

  for (
    let i =
      result.length - 1;
    i > 0;
    i -= 1
  ) {
    const j =
      Math.floor(
        Math.random() *
        (i + 1),
      )

    ;[
      result[i],
      result[j],
    ] = [
      result[j],
      result[i],
    ]
  }

  return result
}


function rotateOffer(): void {
  if (
    offers.value.length <=
    1
  ) {
    return
  }

  visible.value =
    false

  if (
    transitionTimer
  ) {
    clearTimeout(
      transitionTimer,
    )
  }

  transitionTimer =
    setTimeout(
      () => {
        activeIndex.value =
          (
            activeIndex.value +
            1
          ) %
          offers.value.length

        visible.value =
          true
      },
      220,
    )
}


function showOffer(
  index: number,
): void {
  if (
    index < 0 ||
    index >= offers.value.length
  ) {
    return
  }

  visible.value =
    false

  if (
    transitionTimer
  ) {
    clearTimeout(
      transitionTimer,
    )
  }

  transitionTimer =
    setTimeout(
      () => {
        activeIndex.value =
          index

        visible.value =
          true
      },
      180,
    )
}


function showPreviousOffer(): void {
  if (
    offers.value.length <=
    1
  ) {
    return
  }

  showOffer(
    (
      activeIndex.value -
      1 +
      offers.value.length
    ) %
    offers.value.length,
  )
}


function showNextOffer(): void {
  if (
    offers.value.length <=
    1
  ) {
    return
  }

  showOffer(
    (
      activeIndex.value +
      1
    ) %
    offers.value.length,
  )
}


function startRotation(): void {
  if (
    rotationTimer
  ) {
    clearInterval(
      rotationTimer,
    )
  }

  if (
    offers.value.length <=
    1
  ) {
    return
  }

  rotationTimer =
    setInterval(
      rotateOffer,
      ROTATION_MS,
    )
}


async function loadOffers(): Promise<void> {
  try {
    const loaded =
      await getTransAzjaOffers()

    offers.value =
      shuffle(
        loaded.filter(
          (
            offer,
          ) =>
            [
              'zapisy',
              'potwierdzony',
              'promocja',
            ].includes(
              offer.status,
            ),
        ),
      )

    activeIndex.value =
      0

    emit(
      'availability',
      offers.value.length >
        0,
    )

    startRotation()
  } catch {
    offers.value =
      []

    emit(
      'availability',
      false,
    )
  }
}


onMounted(
  () => {
    void loadOffers()
  },
)


onBeforeUnmount(
  () => {
    if (
      rotationTimer
    ) {
      clearInterval(
        rotationTimer,
      )
    }

    if (
      transitionTimer
    ) {
      clearTimeout(
        transitionTimer,
      )
    }
  },
)
</script>


<template>
  <div
    v-if="currentOffer"
    class="trip-ad-shell"
    aria-live="polite"
  >
    <a
      :href="currentOffer.url"
      class="trip-ad"
      :class="{
        'trip-ad--visible':
          visible,
      }"
      target="_blank"
      rel="noopener noreferrer"
      :aria-label="`
        ${statusLabel}:
        ${currentOffer.title},
        ${currentOffer.days} dni,
        ${currentOffer.date_text}.
        Otwórz ofertę TransAzji.
      `"
    >
      <div class="trip-ad__media">
        <img
          v-if="currentOffer.image"
          :src="currentOffer.image"
          :alt="currentOffer.title"
          class="trip-ad__image"
        >

        <div
          v-else
          class="trip-ad__image-fallback"
          aria-hidden="true"
        >
          <span>✈</span>
        </div>

        <span
          class="trip-ad__status"
          :class="`
            trip-ad__status--${currentOffer.status}
          `"
        >
          {{ statusLabel }}
        </span>
      </div>

      <div class="trip-ad__content">
        <div class="trip-ad__eyebrow">
          Polecany wyjazd TransAzji
        </div>

        <div class="trip-ad__title">
          {{ currentOffer.title }},
          {{ currentOffer.days }}
          dni
        </div>

        <div class="trip-ad__date">
          {{ currentOffer.date_text }}
        </div>

        <div class="trip-ad__link">
          Zobacz szczegóły
          <span aria-hidden="true">
            →
          </span>
        </div>
      </div>
    </a>

    <div
      v-if="offers.length > 1"
      class="trip-ad-navigation"
      aria-label="Nawigacja reklam"
    >
      <button
        type="button"
        class="trip-ad-navigation__arrow"
        aria-label="Poprzednia reklama"
        title="Poprzednia reklama"
        @click="showPreviousOffer"
      >
        ‹
      </button>

      <div class="trip-ad-dots">
        <button
          v-for="(_, index) in offers"
          :key="index"
          type="button"
          class="trip-ad-dot"
          :class="{
            active:
              index ===
              activeIndex,
          }"
          :aria-label="`Pokaż reklamę ${index + 1}`"
          @click="showOffer(index)"
        />
      </div>

      <button
        type="button"
        class="trip-ad-navigation__arrow"
        aria-label="Następna reklama"
        title="Następna reklama"
        @click="showNextOffer"
      >
        ›
      </button>
    </div>
  </div>
</template>


<style scoped>
.trip-ad-shell {
  width: 100%;
}


.trip-ad {
  display: grid;
  grid-template-columns:
    148px minmax(0, 1fr);
  min-height: 118px;
  overflow: hidden;
  border:
    1px solid
    rgba(
      11,
      45,
      92,
      0.17
    );
  border-radius: 14px;
  background:
    rgba(
      255,
      255,
      255,
      0.97
    );
  box-shadow:
    0 10px 28px
    rgba(
      15,
      23,
      42,
      0.15
    );
  color: inherit;
  opacity: 0;
  text-decoration: none;
  transform:
    translateY(3px);
  backdrop-filter: blur(10px);
  transition:
    opacity 0.22s ease,
    transform 0.22s ease,
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}


.trip-ad--visible {
  opacity: 1;
  transform:
    translateY(0);
}


.trip-ad:hover {
  border-color:
    rgba(
      11,
      45,
      92,
      0.34
    );
  box-shadow:
    0 13px 34px
    rgba(
      15,
      23,
      42,
      0.19
    );
}


.trip-ad__media {
  position: relative;
  min-height: 118px;
  overflow: hidden;
  background: #e8edf3;
}


.trip-ad__image {
  display: block;
  width: 100%;
  height: 100%;
  min-height: 118px;
  object-fit: cover;
}


.trip-ad__image-fallback {
  display: flex;
  width: 100%;
  height: 100%;
  min-height: 118px;
  align-items: center;
  justify-content: center;
  background:
    linear-gradient(
      145deg,
      #edf2f7,
      #d9e4ee
    );
  color: #0b2d5c;
}


.trip-ad__image-fallback span {
  font-size: 30px;
  transform: rotate(-8deg);
}


.trip-ad__status {
  position: absolute;
  top: 8px;
  left: 8px;
  max-width: calc(100% - 16px);
  padding: 5px 8px;
  border-radius: 5px;
  background: #4ea92e;
  box-shadow:
    0 1px 4px
    rgba(
      0,
      0,
      0,
      0.18
    );
  color: #fff;
  font-size: 8.5px;
  font-weight: 800;
  letter-spacing: 0.04em;
  line-height: 1;
  text-transform: uppercase;
}


.trip-ad__status--potwierdzony {
  background: #198754;
}


.trip-ad__status--promocja {
  background: #d97706;
}


.trip-ad__content {
  display: flex;
  min-width: 0;
  flex-direction: column;
  align-items: flex-start;
  padding:
    10px 11px 10px;
}


.trip-ad__eyebrow {
  margin-bottom: 4px;
  color: #8a94a2;
  font-size: 8px;
  font-weight: 700;
  letter-spacing: 0.045em;
  line-height: 1.15;
  text-transform: uppercase;
}


.trip-ad__title {
  width: 100%;
  overflow: hidden;
  color: #0b2d5c;
  font-size: 16px;
  font-weight: 800;
  line-height: 1.1;
  text-overflow: ellipsis;
  white-space: nowrap;
}


.trip-ad__date {
  display: -webkit-box;
  width: 100%;
  overflow: hidden;
  margin-top: 7px;
  color: #586474;
  font-size: 12px;
  font-weight: 400;
  line-height: 1.25;
  text-align: center;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}


.trip-ad__link {
  display: inline-flex;
  min-height: 29px;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: auto;
  padding: 6px 10px;
  border:
    1px solid
    rgba(
      11,
      45,
      92,
      0.18
    );
  border-radius: 7px;
  background:
    rgba(
      11,
      45,
      92,
      0.055
    );
  color: #0b2d5c;
  font-size: 9px;
  font-weight: 750;
  letter-spacing: 0.02em;
  line-height: 1;
  text-transform: uppercase;
  box-shadow: none;
  transition:
    background 0.15s ease,
    border-color 0.15s ease;
}


.trip-ad:hover
.trip-ad__link {
  border-color:
    rgba(
      11,
      45,
      92,
      0.30
    );
  background:
    rgba(
      11,
      45,
      92,
      0.09
    );
}


.trip-ad__link span {
  font-size: 13px;
  transition:
    transform 0.15s ease;
}


.trip-ad:hover
.trip-ad__link span {
  transform:
    translateX(2px);
}


.trip-ad-navigation {
  display: flex;
  min-height: 24px;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding-top: 4px;
}


.trip-ad-dots {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
}


.trip-ad-dot {
  width: 7px;
  height: 7px;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.88);
  box-shadow:
    0 0 0 1px
    rgba(
      11,
      45,
      92,
      0.22
    );
  cursor: pointer;
  transition:
    width 0.18s ease,
    background 0.18s ease,
    box-shadow 0.18s ease;
}


.trip-ad-dot:hover {
  background: #fff;
  box-shadow:
    0 0 0 1px
    rgba(
      11,
      45,
      92,
      0.40
    );
}


.trip-ad-dot.active {
  width: 18px;
  background: #0b2d5c;
}


.trip-ad-navigation__arrow {
  display: inline-flex;
  width: 22px;
  height: 22px;
  align-items: center;
  justify-content: center;
  padding: 0 0 2px;
  border:
    1px solid
    rgba(
      11,
      45,
      92,
      0.16
    );
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.86);
  color: #6c7785;
  cursor: pointer;
  font-size: 18px;
  font-weight: 500;
  line-height: 1;
  transition:
    color 0.15s ease,
    background 0.15s ease,
    border-color 0.15s ease;
}


.trip-ad-navigation__arrow:hover {
  border-color:
    rgba(
      11,
      45,
      92,
      0.32
    );
  background: #fff;
  color: #0b2d5c;
}


@media (max-width: 560px) {
  .trip-ad {
    grid-template-columns:
      120px minmax(0, 1fr);
  }

  .trip-ad__title {
    font-size: 14px;
  }

  .trip-ad__date {
    font-size: 11px;
  }
}
</style>
