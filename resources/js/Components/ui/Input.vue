<script setup>
defineProps({
  modelValue: [String, Number],
  type: { type: String, default: 'text' },
  placeholder: String,
  disabled: Boolean
})
defineEmits(['update:modelValue'])
</script>
<template>
  <input
    :type="type" :value="modelValue" :placeholder="placeholder" :disabled="disabled"
    @input="$emit('update:modelValue', $event.target.value)"
    class="w-full rounded-xl border px-3 py-2 text-sm bg-white dark:bg-slate-900
           border-gray-300 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-600/30" />
</template>
