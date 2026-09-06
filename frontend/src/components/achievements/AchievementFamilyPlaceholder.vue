<script setup lang="ts">
defineProps<{
  label: string
  icon: string
}>()

const emit = defineEmits<{
  back: []
}>()
</script>

<template>
  <section class="achievement-family-future">
    <div class="achievement-family-future__symbol" v-html="icon"></div>
    <span>Rodzina osiągnięć</span>
    <h2>{{ label }}</h2>
    <p>Ta rodzina odznak jest już przewidziana w układzie systemu i zostanie opracowana w kolejnym etapie.</p>
    <button type="button" @click="emit('back')">← Wróć do odznak za loty</button>
  </section>
</template>
