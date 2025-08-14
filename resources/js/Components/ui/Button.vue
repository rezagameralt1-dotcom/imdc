<script setup>
defineProps({
  as: { type: String, default: 'button' },
  variant: { type: String, default: 'primary' }, // primary | outline | ghost
  disabled: { type: Boolean, default: false },
})
</script>

<template>
  <component
    :is="as" :disabled="disabled"
    class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm transition
           focus:outline-none focus:ring-2 focus:ring-brand-600/30 disabled:opacity-50"
    :class="{
      'bg-brand-600 text-white hover:bg-brand-700': variant==='primary',
      'border border-gray-300 hover:bg-gray-100 dark:border-slate-700 dark:hover:bg-white/10': variant==='outline',
      'hover:bg-gray-100 dark:hover:bg-white/10': variant==='ghost',
    }">
    <slot />
  </component>
</template>
