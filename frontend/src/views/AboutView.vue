<template>
  <section class="space-y-2">
    <h2 class="text-xl font-semibold">{{ t('about') }}</h2>
    <p class="text-gray-700">IMDC Level 9 — Frontend Skeleton</p>
  </section>
</template>
<script setup>
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
</script>
