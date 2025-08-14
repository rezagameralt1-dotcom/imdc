<script setup>
import { useToast } from '@/stores/toast'
const toast = useToast()
</script>

<template>
  <div class="fixed z-[60] bottom-4 end-4 space-y-3 w-[min(92vw,360px)]">
    <div v-for="t in toast.items" :key="t.id"
         class="p-3 rounded-xl shadow-soft border bg-white dark:bg-slate-900 dark:text-slate-200">
      <div class="flex items-start gap-2">
        <div class="font-semibold" v-if="t.title">{{ t.title }}</div>
        <button class="ms-auto opacity-60 hover:opacity-100" @click="toast.remove(t.id)">✕</button>
      </div>
      <div class="text-sm opacity-80" v-if="t.message">{{ t.message }}</div>
    </div>
  </div>
</template>
