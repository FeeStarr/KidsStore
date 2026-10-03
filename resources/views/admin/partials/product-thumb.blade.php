@php
    $thumbImg = $product->primaryImage?->url ?? $product->catalog_image;
@endphp
@if($thumbImg)
    <img src="{{ $thumbImg }}" alt="{{ $product->name }}" class="rounded border flex-shrink-0"
         style="width:44px;height:44px;object-fit:cover;" loading="lazy" decoding="async">
@else
    <span class="rounded border bg-light d-inline-flex align-items-center justify-content-center text-muted flex-shrink-0"
          style="width:44px;height:44px;"><i class="bi bi-image"></i></span>
@endif
