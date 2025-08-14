<script setup>
defineProps({ modelValue: Boolean, label: String, disabled: Boolean })
defineEmits(['update:modelValue'])
</script>
<template>
  <label class="inline-flex items-center gap-2 select-none">
    <input type="checkbox" :checked="modelValue" :disabled="disabled"
           @change="$emit('update:modelValue', $event.target.checked)"
           class="rounded border-gray-300 text-brand-600 focus:ring-brand-600" />
    <span class="text-sm">{{ label }}</span>
  </label>
</template>
