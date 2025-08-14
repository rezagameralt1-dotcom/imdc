@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="mt-4 flex items-center justify-between">
        <div>
            @if ($paginator->onFirstPage())
                <span class="px-3 py-1 rounded bg-gray-200 dark:bg-gray-700 opacity-50">«</span>
            @else
                <a class="px-3 py-1 rounded bg-gray-200 dark:bg-gray-700 hover:bg-gray-300" href="{{ $paginator->previousPageUrl() }}" rel="prev">«</a>
            @endif
        </div>
        <div class="space-x-1">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-3 py-1">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="px-3 py-1 rounded bg-blue-600 text-white">{{ $page }}</span>
                        @else
                            <a class="px-3 py-1 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>
        <div>
            @if ($paginator->hasMorePages())
                <a class="px-3 py-1 rounded bg-gray-200 dark:bg-gray-700 hover:bg-gray-300" href="{{ $paginator->nextPageUrl() }}" rel="next">»</a>
            @else
                <span class="px-3 py-1 rounded bg-gray-200 dark:bg-gray-700 opacity-50">»</span>
            @endif
        </div>
    </nav>
@endif

