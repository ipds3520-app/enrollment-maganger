@if ($paginator->hasPages())
    <div class="pagination-wrap">
        @if (method_exists($paginator, 'firstItem') && $paginator->firstItem() !== null)
            <p class="pagination-summary">
                Menampilkan {{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }}
                dari {{ $paginator->total() }} data
            </p>
        @else
            <p class="pagination-summary">Halaman {{ $paginator->currentPage() }}</p>
        @endif

        <nav aria-label="Navigasi halaman">
            <ul class="pagination">
                @foreach ($paginator->linkCollection() as $link)
                    @php
                        $isPrevious = $loop->first;
                        $isNext = $loop->last;
                        $isGap = $link['label'] === '...';
                    @endphp

                    @if ($isGap)
                        <li class="page-item is-disabled">
                            <span class="page-ellipsis" aria-hidden="true">&hellip;</span>
                        </li>
                    @elseif ($link['active'])
                        <li class="page-item is-active">
                            <span class="page-link" aria-current="page">{{ $link['label'] }}</span>
                        </li>
                    @elseif ($link['url'] === null)
                        <li class="page-item is-disabled">
                            <span class="page-link" aria-hidden="true">{{ $isPrevious ? '‹' : '›' }}</span>
                        </li>
                    @else
                        <li class="page-item">
                            <a
                                class="page-link"
                                href="{{ $link['url'] }}"
                                @if ($isPrevious) rel="prev" @endif
                                @if ($isNext) rel="next" @endif
                            >
                                @if ($isPrevious)
                                    <span aria-hidden="true">&lsaquo;</span>
                                    <span class="sr-only">Halaman sebelumnya</span>
                                @elseif ($isNext)
                                    <span aria-hidden="true">&rsaquo;</span>
                                    <span class="sr-only">Halaman berikutnya</span>
                                @else
                                    {{ $link['label'] }}
                                @endif
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
    </div>
@endif
