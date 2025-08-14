@extends('layouts.base')

@section('body')
    @includeIf('site.partials.nav')
    <main class="py-4 container">
        @includeWhen(View::exists('partials.flash'), 'partials.flash')
        @includeWhen(View::exists('partials.toast'), 'partials.toast')
        @yield('content')
    </main>
@endsection

