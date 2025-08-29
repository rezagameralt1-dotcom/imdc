<template>
  <div class="min-h-screen bg-gray-50 text-gray-900">
    <header class="p-4 shadow bg-white sticky top-0">
      <nav class="max-w-5xl mx-auto flex items-center gap-4">
        <h1 class="font-bold">IMDC Frontend</h1>
        <a class="underline" href="/">Home</a>
        <a class="underline" href="/about">About</a>
      </nav>
    </header>
    <main class="max-w-5xl mx-auto p-6">
      <router-view />
    </main>
  </div>
</template>
<script setup>
// nothing here for now
</script>
