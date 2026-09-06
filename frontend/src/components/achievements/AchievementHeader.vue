<script setup lang="ts">
import achievementHeaderArtUrl from '../../assets/achievements/achievement-header-art.png'
</script>

<template>
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
</template>
