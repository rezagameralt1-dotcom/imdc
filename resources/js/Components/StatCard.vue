<script setup>
defineProps({ label: String, value: [String, Number], hint: { type: String, default: '' } })
</script>
<template>
  <div class="p-4 rounded-2xl border bg-white dark:bg-slate-900 shadow-soft">
    <div class="text-sm opacity-70 mb-1">{{ label }}</div>
    <div class="text-2xl font-semibold">{{ value }}</div>
    <div v-if="hint" class="text-xs opacity-60 mt-1">{{ hint }}</div>
  </div>
</template>
