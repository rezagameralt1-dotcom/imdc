<template>
  <main class="min-h-screen">
    <header class="bg-white border-b">
      <div class="max-w-5xl mx-auto p-4 flex items-center gap-4">
        <h1 class="text-2xl font-bold">DigitalCity</h1>
        <nav class="flex gap-4">
          <RouterLink to="/">خانه</RouterLink>
          <RouterLink to="/posts">پست‌ها</RouterLink>
        </nav>
      </div>
    </header>
    <section class="max-w-5xl mx-auto p-6">
      <RouterView/>
    </section>
  </main>
</template>
<script setup lang="ts"></script>

