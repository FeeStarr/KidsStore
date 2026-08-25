@extends('layouts.admin', ['title' => 'Contact Messages'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Contact Messages</h3>
    <a href="{{ route('admin.contact.edit') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-pencil"></i> Edit Page
    </a>
</div>

{{-- Filter Tabs --}}
<div class="mb-3">
    <div class="btn-group btn-group-sm">
        <a href="{{ route('admin.contact.messages') }}" class="btn btn-{{ $filter === 'all' ? 'primary' : 'outline-primary' }}">All</a>
        <a href="{{ route('admin.contact.messages', ['filter' => 'new']) }}" class="btn btn-{{ $filter === 'new' ? 'primary' : 'outline-primary' }}">
            New @if($filter !== 'new' && \App\Models\ContactMessage::new()->count() > 0)
                <span class="badge bg-danger ms-1">{{ \App\Models\ContactMessage::new()->count() }}</span>
            @endif
        </a>
        <a href="{{ route('admin.contact.messages', ['filter' => 'replied']) }}" class="btn btn-{{ $filter === 'replied' ? 'primary' : 'outline-primary' }}">Replied</a>
        <a href="{{ route('admin.contact.messages', ['filter' => 'spam']) }}" class="btn btn-{{ $filter === 'spam' ? 'danger' : 'outline-danger' }}">Spam</a>
        <a href="{{ route('admin.contact.messages', ['filter' => 'archived']) }}" class="btn btn-{{ $filter === 'archived' ? 'secondary' : 'outline-secondary' }}">Archived</a>
    </div>
</div>

@if($messages->isEmpty())
    <div class="card"><div class="card-body text-center text-muted py-5">
        @if($filter === 'spam')
            No spam messages.
        @elseif($filter === 'new')
            No new messages.
        @else
            No messages yet.
        @endif
    </div></div>
@else
<div class="card">
    <div class="card-body p-0">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:1rem;"></th>
                    <th>From</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Received</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($messages as $msg)
                @php
                    $statusBadge = match($msg->status) {
                        'new' => 'primary',
                        'read' => 'secondary',
                        'replied' => 'success',
                        'spam' => 'danger',
                        'archived' => 'dark',
                        default => 'secondary',
                    };
                    $isSpam = $msg->status === 'spam';
                @endphp
                <tr class="{{ $msg->status === 'new' ? 'fw-semibold' : '' }} {{ $isSpam ? 'table-danger' : '' }}">
                    <td>
                        @if($msg->status === 'new')
                            <span class="badge rounded-pill bg-danger" title="New">&nbsp;</span>
                        @endif
                    </td>
                    <td>
                        <div>{{ $msg->name }}</div>
                        <small class="text-muted">{{ $msg->email }}</small>
                        @if($msg->ip_address)
                            <br><small class="text-muted" title="IP Address"><i class="bi bi-globe"></i> {{ $msg->ip_address }}</small>
                        @endif
                    </td>
                    <td>{{ $msg->subject ?: '-' }}</td>
                    <td><span class="badge bg-{{ $statusBadge }}">{{ \App\Models\ContactMessage::STATUS_LABELS[$msg->status] ?? $msg->status }}</span></td>
                    <td><small class="text-muted">{{ $msg->created_at->diffForHumans() }}</small></td>
                    <td class="text-end">
                        <a href="{{ route('admin.contact.messages.show', $msg) }}" class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-eye"></i>
                        </a>
                        @if($msg->status !== 'spam')
                            <form action="{{ route('admin.contact.messages.spam', $msg) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Mark as spam?')">
                                @csrf
                                <button class="btn btn-sm btn-outline-warning" title="Mark as Spam"><i class="bi bi-shield-exclamation"></i></button>
                            </form>
                        @endif
                        @if($msg->status !== 'archived')
                            <form action="{{ route('admin.contact.messages.archive', $msg) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Archive this message?')">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary" title="Archive"><i class="bi bi-archive"></i></button>
                            </form>
                        @endif
                        <form action="{{ route('admin.contact.messages.destroy', $msg) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Delete this message?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $messages->links() }}</div>
@endif
@endsection
