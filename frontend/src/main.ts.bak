import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import './assets/style.css'
import Home from './pages/Home.vue'
import Posts from './pages/Posts.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: Home },
    { path: '/posts', component: Posts }
  ]
})

createApp(App).use(router).mount('#app')

