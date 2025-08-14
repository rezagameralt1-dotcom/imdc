<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}"
      dir="{{ app()->getLocale()==='fa' ? 'rtl' : 'ltr' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title inertia>{{ config('app.name','IMDC') }}</title>
  @vite(['resources/css/app.css','resources/js/app.js'])
  @inertiaHead
</head>
<body class="antialiased bg-gray-50 text-gray-900">
  @inertia
</body>
</html>
