@extends('layouts.app')

@section('content')
  <h1>Settings</h1>
  <p class="lead">پیکربندی سیستم</p>

  <div class="panel">
    <form onsubmit="return false;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <label>Site Title</label>
          <input class="input" value="DigitalCity" />
        </div>
        <div>
          <label>Primary Color</label>
          <select class="select">
            <option>Blue (default)</option>
            <option>Green</option>
            <option>Purple</option>
          </select>
        </div>
      </div>
      <div class="kit" style="margin-top:12px">
        <button class="btn">Save</button>
        <button class="btn secondary">Cancel</button>
      </div>
    </form>
  </div>
@endsection
---

