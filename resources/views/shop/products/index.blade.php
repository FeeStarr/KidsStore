@extends('layouts.shop', ['title' => 'Shop'])
@section('content')

@php
    $pageHeading = $activeCategory?->name ?: 'All Products';
@endphp

<div class="text-center mb-4">
    <h2 class="mb-1">{{ $pageHeading }}</h2>
</div>

<div class="row">
    <aside class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-uppercase text-muted small">Categories</h6>
                <ul class="list-unstyled mb-3">
                    <li><a href="{{ route('shop.products.index', request()->except(['category','page','q'])) }}" class="{{ request('category') ? '' : 'fw-bold' }}">All</a></li>
                    @foreach($categories as $c)
                        <li>
                            <a href="{{ route('shop.products.index', array_merge(request()->except(['page','category','q']), ['category' => $c->id])) }}"
                               class="{{ (int) request('category') === $c->id ? 'fw-bold text-primary' : 'text-dark' }}">
                                {{ $c->name }}
                            </a>
                            @if($c->children->count())
                                <ul class="list-unstyled ms-3">
                                    @foreach($c->children->filter(fn($sub) => $sub->is_active) as $sub)
                                        <li>
                                            <a href="{{ route('shop.products.index', array_merge(request()->except(['page','category','q']), ['category' => $sub->id])) }}"
                                               class="small {{ (int) request('category') === $sub->id ? 'fw-bold text-primary' : 'text-muted' }}">
                                                - {{ $sub->name }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <form>
                    @if(request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
                    <h6 class="text-uppercase text-muted small">Search</h6>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm mb-2" placeholder="Keyword">
                    @if($ageRanges->count())
                        <h6 class="text-uppercase text-muted small">Age Range</h6>
                        <select name="age_range" class="form-select form-select-sm mb-2" onchange="this.form.submit()">
                            <option value="">All ages</option>
                            @foreach($ageRanges as $age)
                                <option value="{{ $age->id }}" @selected((int) request('age_range') === (int) $age->id)>{{ $age->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <h6 class="text-uppercase text-muted small">Sort by</h6>
                    <select name="sort" class="form-select form-select-sm mb-2" onchange="this.form.submit()">
                        @php($sort = request('sort'))
                        <option value="" @selected(!$sort)>Newest</option>
                        <option value="name" @selected($sort==='name')>Name (A-Z)</option>
                        <option value="price_asc"  @selected($sort==='price_asc')>Price: Low to High</option>
                        <option value="price_desc" @selected($sort==='price_desc')>Price: High to Low</option>
                    </select>
                    <button class="btn btn-sm btn-primary w-100">Apply</button>
                </form>
            </div>
        </div>
    </aside>

    <div class="col-md-9">
        <div class="row g-3">
            @forelse($products as $product)
                <div class="col-6 col-md-4">@include('shop.partials.product-card', ['product' => $product])</div>
            @empty
                <div class="col-12 text-center text-muted py-5">No products match your filters.</div>
            @endforelse
        </div>

        <div class="mt-4">{{ $products->links() }}</div>
    </div>
</div>

@endsection
