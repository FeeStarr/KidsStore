@extends('layouts.admin', ['title' => 'Message from ' . $message->name])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Message</h3>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.contact.messages') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Messages
        </a>
        <form action="{{ route('admin.contact.messages.destroy', $message) }}" method="POST"
              onsubmit="return confirm('Delete this message?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
        </form>
    </div>
</div>

<div class="card">
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
        </dl>

        <hr>

        <p class="mb-0" style="white-space:pre-wrap;">{{ $message->message }}</p>
    </div>
</div>
@endsection
