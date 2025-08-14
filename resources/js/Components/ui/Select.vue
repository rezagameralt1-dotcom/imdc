<script setup>
defineProps({
  modelValue: [String, Number],
  options: { type: Array, default: () => [] },
  disabled: Boolean
})
defineEmits(['update:modelValue'])
</script>
<template>
  <select :value="modelValue" :disabled="disabled"
          @change="$emit('update:modelValue', $event.target.value)"
          class="w-full rounded-xl border px-3 py-2 text-sm bg-white dark:bg-slate-900
                 border-gray-300 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-600/30">
    <option v-for="o in options" :key="o.value ?? o" :value="o.value ?? o">
      {{ o.label ?? o }}
    </option>
  </select>
</template>
