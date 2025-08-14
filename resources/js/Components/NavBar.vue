<script setup>
import ThemeToggle from './ThemeToggle.vue'
import LangSwitch from './LangSwitch.vue'
defineProps({ title: { type: String, default: 'IMDC' }})
</script>

<template>
  <header class="sticky top-0 z-40 bg-white/80 backdrop-blur border-b dark:bg-slate-900/80">
    <div class="container h-14 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="size-8 rounded-xl bg-brand-600 text-white grid place-items-center shadow-soft">I</div>
        <h1 class="font-bold text-lg">{{ title }}</h1>
      </div>
      <div class="flex items-center gap-3">
        <LangSwitch />
        <ThemeToggle />
      </div>
    </div>
  </header>
</template>
