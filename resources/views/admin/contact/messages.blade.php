@extends('layouts.admin', ['title' => 'Contact Messages'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Contact Messages</h3>
    <a href="{{ route('admin.contact.edit') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-pencil"></i> Edit Page
    </a>
</div>

@if($messages->isEmpty())
    <div class="card"><div class="card-body text-center text-muted py-5">No messages yet.</div></div>
@else
<div class="card">
    <div class="card-body p-0">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:1rem;"></th>
                    <th>From</th>
                    <th>Subject</th>
                    <th>Received</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($messages as $msg)
                <tr class="{{ $msg->read ? '' : 'fw-semibold' }}">
                    <td>
                        @if(! $msg->read)
                            <span class="badge rounded-pill bg-danger" title="Unread">&nbsp;</span>
                        @endif
                    </td>
                    <td>
                        <div>{{ $msg->name }}</div>
                        <small class="text-muted">{{ $msg->email }}</small>
                    </td>
                    <td>{{ $msg->subject ?: '—' }}</td>
                    <td><small class="text-muted">{{ $msg->created_at->diffForHumans() }}</small></td>
                    <td class="text-end">
                        <a href="{{ route('admin.contact.messages.show', $msg) }}" class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-eye"></i> Read
                        </a>
                        <form action="{{ route('admin.contact.messages.destroy', $msg) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Delete this message?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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
