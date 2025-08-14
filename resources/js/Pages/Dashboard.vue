<script setup>
import AppLayout from '../Layouts/AppLayout.vue'
import StatCard from '../Components/StatCard.vue'
const points = [12,18,17,21,19,24,28,26,32,30]
const max = Math.max(...points), min = Math.min(...points)
const width = 180, height = 60
const path = points.map((v,i)=>{
  const x = (i/(points.length-1))*width
  const y = height - ((v-min)/(max-min||1))*height
  return `${i?'L':'M'}${x},${y}`
}).join(' ')
</script>

<template>
  <AppLayout title="داشبورد">
    <div class="grid gap-4 md:grid-cols-3">
      <StatCard label="کاربران" :value="1254" hint="+4% نسبت به هفته قبل" />
      <StatCard label="سفارش‌ها" :value="342" hint="+2% امروز" />
      <StatCard label="درآمد (هزار تومان)" :value="86.5" hint="-1.1% دیروز" />
    </div>

    <div class="p-4 rounded-2xl border bg-white dark:bg-slate-900 shadow-soft">
      <div class="flex items-center justify-between mb-2">
        <h2 class="font-semibold">روند کلی</h2>
        <span class="text-xs opacity-60">۱۰ نقطه نمونه</span>
      </div>
      <svg :width="width" :height="height" viewBox="0 0 180 60" class="w-full max-w-xs">
        <path :d="path" fill="none" stroke="currentColor" stroke-width="2" class="text-brand-600"/>
      </svg>
    </div>
  </AppLayout>
</template>
