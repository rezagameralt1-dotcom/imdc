import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createI18n } from 'vue-i18n'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import './assets/main.css'

const routes = [
  { path: '/', name: 'home', component: () => import('./views/HomeView.vue') },
  { path: '/about', name: 'about', component: () => import('./views/AboutView.vue') },
]
const router = createRouter({ history: createWebHistory(), routes })

const i18n = createI18n({
  legacy: false,
  locale: 'fa',
  fallbackLocale: 'en',
  messages: {
    fa: { hello: 'سلام IMDC 👋', about: 'درباره پروژه' },
    en: { hello: 'Hello IMDC 👋', about: 'About project' },
  },
})

createApp(App).use(createPinia()).use(router).use(i18n).mount('#app')
