@extends('layouts.admin', ['title' => 'Suppliers'])
@section('content')
<h3 class="mb-3">Suppliers</h3>

<a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary mb-3"><i class="bi bi-plus-lg"></i> New Supplier</a>

<div class="card">
    <div class="card-body">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Social</th>
                    <th class="text-end">Active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($suppliers as $s)
                    <tr>
                        <td>{{ $s->name }}</td>
                        <td>{{ $s->contact_name }}</td>
                        <td>{{ $s->phone }}</td>
                        <td>{{ $s->email }}</td>
                        <td class="small">
                            @if($s->social_whatsapp)<a href="https://wa.me/{{ ltrim(preg_replace('/[^0-9]/', '', $s->social_whatsapp), '+') }}" target="_blank" class="text-success me-1" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>@endif
                            @if($s->social_instagram)<a href="https://instagram.com/{{ ltrim($s->social_instagram, '@') }}" target="_blank" class="text-danger me-1" title="Instagram"><i class="bi bi-instagram"></i></a>@endif
                            @if($s->social_facebook)<a href="https://facebook.com/{{ ltrim($s->social_facebook, '/') }}" target="_blank" class="text-primary me-1" title="Facebook"><i class="bi bi-facebook"></i></a>@endif
                            @if($s->social_tiktok)<a href="https://tiktok.com/@{{ ltrim($s->social_tiktok, '@') }}" target="_blank" class="text-dark me-1" title="TikTok"><i class="bi bi-tiktok"></i></a>@endif
                            @if($s->social_twitter)<a href="https://x.com/{{ ltrim($s->social_twitter, '@') }}" target="_blank" class="text-info me-1" title="Twitter/X"><i class="bi bi-twitter-x"></i></a>@endif
                            @if($s->social_website)<a href="{{ $s->social_website }}" target="_blank" class="text-secondary me-1" title="Website"><i class="bi bi-globe"></i></a>@endif
                        </td>
                        <td class="text-end">{{ $s->is_active ? 'Yes' : 'No' }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.suppliers.edit', $s) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="post" action="{{ route('admin.suppliers.destroy', $s) }}" style="display:inline" onsubmit="return confirm('Delete supplier?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
