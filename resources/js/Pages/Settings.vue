<script setup>
import AppLayout from '../Layouts/AppLayout.vue'
import Button from '../Components/ui/Button.vue'
import Input from '../Components/ui/Input.vue'
import Select from '../Components/ui/Select.vue'
import Checkbox from '../Components/ui/Checkbox.vue'
import Modal from '../Components/ui/Modal.vue'
import { ref } from 'vue'
import { useToast } from '@/stores/toast'

const toast = useToast()
const form = ref({ name: 'IMDC', timezone: 'Asia/Tehran', news: true })
const open = ref(false)

function save() {
  // اینجا بعداً به API وصل می‌کنیم
  toast.push({ title: 'ذخیره شد', message: 'تنظیمات با موفقیت ذخیره شد.', type: 'success' })
}
</script>

<template>
  <AppLayout title="تنظیمات">
    <div class="space-y-4">
      <div class="p-4 rounded-2xl border bg-white dark:bg-slate-900 shadow-soft">
        <h2 class="font-semibold mb-3">ترجیح‌ها</h2>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <div class="text-sm mb-1">نام پروژه</div>
            <Input v-model="form.name" placeholder="Project name" />
          </div>

          <div>
            <div class="text-sm mb-1">منطقه زمانی</div>
            <Select v-model="form.timezone" :options="[
              {label:'Asia/Tehran', value:'Asia/Tehran'},
              {label:'UTC', value:'UTC'},
              {label:'Europe/Berlin', value:'Europe/Berlin'},
            ]"/>
          </div>

          <div class="sm:col-span-2">
            <Checkbox v-model="form.news" label="دریافت خبرنامه" />
          </div>
        </div>

        <div class="mt-4 flex gap-3">
          <Button @click="save()">ذخیره تغییرات</Button>
          <Button variant="outline" @click="open=true">نمایش راهنما</Button>
        </div>
      </div>
    </div>

    <Modal v-model="open" title="راهنمای تنظیمات">
      <p class="text-sm opacity-80">
        این یک مودال نمونه است. بعداً محتوای راهنمای واقعی را جایگزین می‌کنیم.
      </p>
    </Modal>
  </AppLayout>
</template>
