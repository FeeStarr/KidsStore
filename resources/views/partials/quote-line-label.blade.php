@php
    [$main, $desc] = array_pad(explode(' — ', (string) ($item['label'] ?? ''), 2), 2, null);
@endphp
{{ $main }}@if ($desc)<span class="text-muted small"> &mdash; {{ $desc }}</span>@endif