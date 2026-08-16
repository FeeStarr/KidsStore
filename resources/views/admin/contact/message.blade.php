@extends('layouts.admin', ['title' => 'Message from ' . $message->name])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1">Message</h3>
        <span class="badge bg-{{ match($message->status) {
            'new' => 'primary',
            'read' => 'secondary',
            'replied' => 'success',
            'spam' => 'danger',
            'archived' => 'dark',
            default => 'secondary',
        } }}">{{ \App\Models\ContactMessage::STATUS_LABELS[$message->status] ?? $message->status }}</span>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.contact.messages') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        @if($message->status !== 'replied')
            <form action="{{ route('admin.contact.messages.replied', $message) }}" method="POST" class="d-inline">
                @csrf
                <button class="btn btn-sm btn-outline-success"><i class="bi bi-reply"></i> Mark Replied</button>
            </form>
        @endif
        @if($message->status !== 'spam')
            <form action="{{ route('admin.contact.messages.spam', $message) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Mark as spam?')">
                @csrf
                <button class="btn btn-sm btn-outline-warning"><i class="bi bi-shield-exclamation"></i> Spam</button>
            </form>
        @endif
        @if($message->status !== 'archived')
            <form action="{{ route('admin.contact.messages.archive', $message) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Archive?')">
                @csrf
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-archive"></i> Archive</button>
            </form>
        @endif
        <form action="{{ route('admin.contact.messages.destroy', $message) }}" method="POST"
              onsubmit="return confirm('Delete this message?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
        </form>
    </div>
</div>

<div class="card {{ $message->status === 'spam' ? 'border-danger' : '' }}">
    <div class="card-body">
        <dl class="row mb-4">
            <dt class="col-sm-2">From</dt>
            <dd class="col-sm-10">{{ $message->name }} &lt;<a href="mailto:{{ $message->email }}">{{ $message->email }}</a>&gt;</dd>

            @if($message->subject)
            <dt class="col-sm-2">Subject</dt>
            <dd class="col-sm-10">{{ $message->subject }}</dd>
            @endif

            <dt class="col-sm-2">Received</dt>
            <dd class="col-sm-10">{{ $message->created_at->format('d M Y, g:i a') }}</dd>

            @if($message->ip_address)
            <dt class="col-sm-2">IP Address</dt>
            <dd class="col-sm-10"><code>{{ $message->ip_address }}</code></dd>
            @endif

            @if($message->read_at)
            <dt class="col-sm-2">Read at</dt>
            <dd class="col-sm-10">{{ $message->read_at->format('d M Y, g:i a') }}</dd>
            @endif
        </dl>

        <hr>

        <p class="mb-0" style="white-space:pre-wrap;">{{ $message->message }}</p>
    </div>
</div>
@endsection
