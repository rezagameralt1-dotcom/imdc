<script setup>
import NavBar from '../Components/NavBar.vue'
import SideBar from '../Components/SideBar.vue'
import ToastHost from '../Components/ui/ToastHost.vue'
defineProps({ title: { type: String, default: 'IMDC' }})
</script>

<template>
  <div>
    <NavBar :title="title" />
    <div class="container flex gap-6 py-6">
      <SideBar />
      <main class="flex-1 space-y-6">
        <slot />
      </main>
    </div>
    <ToastHost />
  </div>
</template>
