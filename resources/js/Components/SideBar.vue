<script setup>
import { Link } from '@inertiajs/vue3'
const nav = [
  { name: 'داشبورد', href: '/', icon: '🏠' },
  { name: 'تنظیمات', href: '/settings', icon: '⚙️' },
]
</script>

<template>
  <aside class="w-64 shrink-0 h-[calc(100dvh-56px)] sticky top-14 border-e bg-white dark:bg-slate-900">
    <nav class="p-3 space-y-1">
      <Link v-for="i in nav" :key="i.href" :href="i.href"
            class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-white/10">
        <span class="w-5 text-center">{{ i.icon }}</span>
        <span>{{ i.name }}</span>
      </Link>
    </nav>
  </aside>
</template>
