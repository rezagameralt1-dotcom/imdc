<script setup>
import { router, usePage } from '@inertiajs/vue3'
const page = usePage()
const current = () => page.props.app?.locale ?? 'en'
function setLang(locale){
  if (locale === current()) return
  router.get(`/locale/${locale}`, {}, { preserveScroll: true })
}
</script>

<template>
  <div class="inline-flex gap-2 items-center">
    <button class="px-2.5 py-1 rounded-lg border text-xs"
            :disabled="current()==='fa'"
            :class="current()==='fa' ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-100 dark:hover:bg-white/10'"
            @click="setLang('fa')">FA</button>
    <button class="px-2.5 py-1 rounded-lg border text-xs"
            :disabled="current()==='en'"
            :class="current()==='en' ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-100 dark:hover:bg-white/10'"
            @click="setLang('en')">EN</button>
  </div>
</template>
