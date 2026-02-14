@props([
    'title',
    'subtitle' => null,
])

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 admin-page-header">
    <div>
        <div class="h4 mb-0">{{ $title }}</div>
        @if(!empty($subtitle))
            <div class="text-muted small">{{ $subtitle }}</div>
        @endif
    </div>

    @isset($actions)
        <div class="d-flex gap-2 align-items-center">
            {{ $actions }}
        </div>
    @endisset
</div>
