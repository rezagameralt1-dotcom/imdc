<template>
  <div class="space-y-3">
    <h2 class="text-xl font-semibold">خانه</h2>
    <button class="px-3 py-1 bg-black text-white rounded" @click="check()">
      Health Check (API)
    </button>
    <p v-if="resp" class="text-sm opacity-80">API OK: {{ resp.time }}</p>
  </div>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { getHealth } from '../lib/api'

const resp = ref<{ok:boolean; time:string} | null>(null)
async function check(){
  resp.value = await getHealth()
}
</script>

