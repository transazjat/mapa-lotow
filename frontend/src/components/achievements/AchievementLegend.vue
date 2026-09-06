<script setup lang="ts">
import achievementHandwrittenNoteUrl from '../../assets/achievements/achievement-note-handwritten.png'
import { achievementIcons } from './achievementUi'
</script>

<template>
  <section class="achievement-legend">
    <div class="achievement-legend__list">
      <div class="achievement-legend__item">
        <span class="achievement-legend__symbol achievement-legend__symbol--active" v-html="achievementIcons.check"></span>
        <p>
          <strong>Aktywna odznaka</strong>
          <small>Zdobyta i aktualnie aktywna.</small>
        </p>
      </div>
      <div class="achievement-legend__item">
        <span class="achievement-legend__symbol achievement-legend__symbol--inactive" v-html="achievementIcons.minus"></span>
        <p>
          <strong>Nieaktywna odznaka</strong>
          <small>Zdobyta wcześniej, ale obecnie poniżej progu.</small>
        </p>
      </div>
      <div class="achievement-legend__item">
        <span class="achievement-legend__symbol achievement-legend__symbol--locked" v-html="achievementIcons.lock"></span>
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
      <span class="achievement-legend__note-plane" v-html="achievementIcons.notePlane"></span>
    </div>
  </section>
</template>
