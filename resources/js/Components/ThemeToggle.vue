<script setup>
import { ref, onMounted } from 'vue'
const dark = ref(false)
onMounted(() => {
  const saved = localStorage.getItem('imdc-theme')
  if (saved) document.documentElement.classList.toggle('dark', saved === 'dark')
  dark.value = document.documentElement.classList.contains('dark')
})
function toggle(){
  dark.value = !dark.value
  document.documentElement.classList.toggle('dark', dark.value)
  localStorage.setItem('imdc-theme', dark.value ? 'dark' : 'light')
}
</script>

<template>
  <button @click="toggle"
          class="px-3 py-1 rounded-xl border text-sm hover:bg-gray-100 dark:hover:bg-white/10">
    {{ dark ? '🌙' : '☀️' }}
  </button>
</template>
