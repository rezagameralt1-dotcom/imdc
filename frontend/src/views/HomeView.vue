<template>
  <section class="space-y-4">
    <h2 class="text-2xl font-semibold">{{ t('hello') }}</h2>
    <p class="text-sm text-gray-600">Vue 3 + Vite + Tailwind + Router + Pinia + i18n + Axios</p>
  </section>
</template>
<script setup>
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
</script>
