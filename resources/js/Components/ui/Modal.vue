<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  width: { type: String, default: 'max-w-lg' },
})
const emit = defineEmits(['update:modelValue'])
function close(){ emit('update:modelValue', false) }
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-[70]">
      <div class="absolute inset-0 bg-black/30" @click="close"></div>
      <div class="absolute inset-0 grid place-items-center p-4">
        <div :class="['w-full', width, 'bg-white dark:bg-slate-900 rounded-2xl shadow-soft border']">
          <div class="px-4 py-3 border-b flex items-center gap-2">
            <h3 class="font-semibold">{{ title }}</h3>
            <button class="ms-auto opacity-70 hover:opacity-100" @click="close">✕</button>
          </div>
          <div class="p-4">
            <slot />
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
