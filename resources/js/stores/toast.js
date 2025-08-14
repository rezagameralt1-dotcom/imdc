import { defineStore } from 'pinia'
let idSeq = 1
export const useToast = defineStore('toast', {
  state: () => ({ items: [] }),
  actions: {
    push({ title = '', message = '', type = 'info', timeout = 3000 } = {}) {
      const id = idSeq++
      this.items.push({ id, title, message, type })
      if (timeout) setTimeout(() => this.remove(id), timeout)
    },
    remove(id){ this.items = this.items.filter(t => t.id !== id) }
  }
})
