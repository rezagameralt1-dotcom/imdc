import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { createPinia } from 'pinia'

// تم ذخیره‌شده را اعمال کن
const saved = localStorage.getItem('imdc-theme')
if (saved) document.documentElement.classList.toggle('dark', saved === 'dark')

createInertiaApp({
  resolve: name => import(`./Pages/${name}.vue`).then(m => m.default),
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) })
    app.use(plugin).use(createPinia()).mount(el)
  },
})
