<template>
  <div class="space-y-6">
    <h2 class="text-xl font-semibold">پست‌ها</h2>
    <div class="grid gap-4 md:grid-cols-2">
      <article v-for="p in items" :key="p.id" class="p-4 border rounded bg-white">
        <h3 class="font-bold">{{ p.title }}</h3>
        <p class="text-sm opacity-80 line-clamp-3" v-html="p.body"></p>
      </article>
    </div>
    <div class="flex gap-2">
      <button :disabled="page<=1" @click="load(page-1)" class="px-3 py-1 border rounded">قبلی</button>
      <button @click="load(page+1)" class="px-3 py-1 border rounded">بعدی</button>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getPosts } from '../lib/api'

const items = ref<any[]>([])
const page = ref(1)
async function load(p=1){
  const data = await getPosts(p)
  // Laravel Resource collection structure: { data: [], links, meta }
  items.value = data.data ?? []
  page.value = p
}
onMounted(()=> load())
</script>

