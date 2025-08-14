<form action="{{ url('/locale/switch') }}" method="get" class="d-inline">
    <select name="locale" onchange="this.form.submit()" aria-label="Language">
        @php $current = app()->getLocale(); @endphp
        <option value="fa" @selected($current==='fa')>فارسی</option>
        <option value="en" @selected($current==='en')>English</option>
    </select>
</form>

