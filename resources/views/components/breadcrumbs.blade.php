@props(['items' => []])
<nav class="text-sm text-gray-600 dark:text-gray-300 mb-4">
    <ol class="list-reset flex">
        <li><a href="{{ url('/') }}" class="hover:underline">{{ config('app.name') }}</a></li>
        @foreach ($items as $label => $url)
            <li><span class="mx-2">/</span></li>
            @if ($url)
                <li><a href="{{ $url }}" class="hover:underline">{{ $label }}</a></li>
            @else
                <li><span class="font-semibold">{{ $label }}</span></li>
            @endif
        @endforeach
    </ol>
</nav>

