import { createRouter, createWebHistory } from 'vue-router'
import Home from '@/pages/Home.vue'

// Lazy-load Posts.vue WITHOUT top-level await to satisfy esbuild target
const PostsComp = () =>
  import('@/pages/Posts.vue').then(m => m.default).catch(() => {
    return { template: '<div class="p-6 text-sm text-gray-500">Posts page is not available.</div>' }
  })

const routes = [
  { path: '/', name: 'home', component: Home },
  { path: '/posts', name: 'posts', component: PostsComp }
]

export default createRouter({ history: createWebHistory(), routes })
