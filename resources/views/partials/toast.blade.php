<style>
.toast-wrap{position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem}
.toast{background:var(--surface-1,#111827);color:#fff;padding:.75rem 1rem;border-radius:.5rem;box-shadow:0 6px 20px rgba(0,0,0,.15)}
.toast.success{background:#16a34a}
.toast.error{background:#dc2626}
</style>
<div class="toast-wrap" id="toasts"></div>
<script>
(function(){
  const wrap = document.getElementById('toasts');
  function show(msg, type='success'){ if(!wrap) return;
    const el=document.createElement('div'); el.className='toast '+type; el.textContent=msg;
    wrap.appendChild(el); setTimeout(()=>{ el.style.opacity='0'; setTimeout(()=>el.remove(),300); }, 3500);
  }
  window.Toast = {show};
  @if(session('ok')) show(@json(session('ok')), 'success'); @endif
  @if($errors->any()) show('خطا در ورودی‌ها', 'error'); @endif

  // مینیمال ولیدیشن: هر فرم required را قبل از submit چک کن
  document.addEventListener('submit', function(e){
    const form=e.target; if(!(form instanceof HTMLFormElement)) return;
    const invalid=[...form.querySelectorAll('[required]')].find(i=>!i.value?.trim());
    if(invalid){ e.preventDefault(); show('لطفاً فیلدهای ضروری را پر کنید.', 'error'); invalid.focus(); }
  }, true);
})();
</script>

