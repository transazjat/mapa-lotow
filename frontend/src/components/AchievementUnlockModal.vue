<script setup lang="ts">
import {
  computed,
  ref,
} from 'vue'

import type {
  AchievementItem,
} from '../types/achievement'

import {
  getFlightBadgeImage,
} from '../utils/achievementBadges'

import {
  downloadAchievementCard,
} from '../utils/achievementCard'

const props = defineProps<{
  achievements: AchievementItem[]
  completedFlights: number
  nick: string
}>()

const emit = defineEmits<{
  close: []
}>()

const exporting = ref<'png' | 'jpg' | null>(null)

const single = computed(
  () => props.achievements.length === 1
    ? props.achievements[0]
    : null,
)

async function exportCard(
  format: 'png' | 'jpg',
): Promise<void> {
  if (!single.value) {
    return
  }

  exporting.value = format

  try {
    await downloadAchievementCard(
      single.value,
      props.completedFlights,
      props.nick,
      format,
    )
  } finally {
    exporting.value = null
  }
}
</script>

<template>
  <div class="achievement-unlock-backdrop" role="dialog" aria-modal="true" aria-label="Nowe osiągnięcie">
    <section class="achievement-unlock-modal">
      <button class="achievement-unlock-close" type="button" aria-label="Zamknij" @click="emit('close')">×</button>

      <template v-if="single">
        <span class="achievement-unlock-kicker">Nowe osiągnięcie</span>
        <h2>{{ single.threshold }} LOTÓW</h2>
        <p>Osiągnąłeś kolejny lotniczy kamień milowy.</p>

        <img
          class="achievement-unlock-badge"
          :src="getFlightBadgeImage(single.threshold) ?? ''"
          :alt="`${single.threshold} lotów`"
        >

        <div class="achievement-unlock-note">
          Ta odznaka została dodana do Twojej kolekcji.
        </div>

        <div class="achievement-unlock-actions">
          <button type="button" class="achievement-unlock-primary" :disabled="exporting !== null" @click="exportCard('png')">
            {{ exporting === 'png' ? 'Tworzę kartę…' : 'Utwórz kartę PNG' }}
          </button>
          <button type="button" :disabled="exporting !== null" @click="exportCard('jpg')">
            Pobierz JPG
          </button>
          <button type="button" @click="emit('close')">
            Zamknij
          </button>
        </div>
      </template>

      <template v-else>
        <span class="achievement-unlock-kicker">Nowe osiągnięcia</span>
        <h2>Zdobyłeś {{ achievements.length }} odznak</h2>
        <p>Twoja historia lotnicza przekroczyła kilka kolejnych kamieni milowych.</p>

        <div class="achievement-unlock-grid">
          <article v-for="item in achievements" :key="item.key">
            <img :src="getFlightBadgeImage(item.threshold) ?? ''" :alt="`${item.threshold} lotów`">
            <strong>{{ item.threshold }} lotów</strong>
          </article>
        </div>

        <div class="achievement-unlock-actions achievement-unlock-actions--single">
          <button type="button" class="achievement-unlock-primary" @click="emit('close')">Zobacz kolekcję później</button>
        </div>
      </template>
    </section>
  </div>
</template>

<style scoped>
.achievement-unlock-backdrop{position:fixed;inset:0;z-index:160;display:grid;place-items:center;padding:24px;background:rgba(5,23,42,.62);backdrop-filter:blur(9px)}
.achievement-unlock-modal{position:relative;width:min(620px,calc(100vw - 40px));padding:36px 38px 30px;border:1px solid rgba(255,255,255,.8);border-radius:22px;background:radial-gradient(circle at 50% 0%,#edf7ff,#fff 48%,#f3f8fc);box-shadow:0 34px 100px rgba(4,25,49,.38);text-align:center;color:#113867}
.achievement-unlock-close{position:absolute;right:16px;top:14px;width:38px;height:38px;border:1px solid #d3e1ec;border-radius:9px;background:#f7fbfe;color:#57738f;cursor:pointer;font-size:26px}
.achievement-unlock-kicker{display:block;color:#6d88a3;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.achievement-unlock-modal h2{margin:8px 0 3px;color:#0d3466;font-size:38px;font-weight:800}.achievement-unlock-modal>p{margin:0;color:#72879a;font-size:14px}.achievement-unlock-badge{display:block;width:min(330px,75vw);margin:18px auto 5px;border-radius:50%;filter:drop-shadow(0 18px 24px rgba(19,52,84,.22))}.achievement-unlock-note{margin:8px auto 18px;color:#5e7893;font-size:12px}.achievement-unlock-actions{display:grid;grid-template-columns:1.35fr .9fr .8fr;gap:8px}.achievement-unlock-actions button{min-height:44px;border:1px solid #c8dae8;border-radius:9px;background:#f8fbfe;color:#234f7b;cursor:pointer;font-weight:650}.achievement-unlock-actions .achievement-unlock-primary{border-color:#165894;background:linear-gradient(#246eb1,#15528e);color:#fff}.achievement-unlock-actions--single{grid-template-columns:1fr}.achievement-unlock-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:10px;margin:24px 0}.achievement-unlock-grid article{padding:10px;border:1px solid #dce8f1;border-radius:10px;background:#fbfdff}.achievement-unlock-grid img{display:block;width:100%;border-radius:50%}.achievement-unlock-grid strong{display:block;margin-top:5px;font-size:11px}
@media(max-width:620px){.achievement-unlock-modal{padding:30px 18px 22px}.achievement-unlock-actions{grid-template-columns:1fr}.achievement-unlock-badge{width:250px}}
</style>
