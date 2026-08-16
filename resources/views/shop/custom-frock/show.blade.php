@extends('layouts.shop')

@section('title', 'Custom Order ' . $customOrder->custom_order_number)

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8">
            {{-- Header --}}
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">{{ $customOrder->custom_order_number }}</h1>
                    <span class="text-muted">{{ $customOrder->getCustomizationValue('dress_style') ?: 'Custom Frock' }}</span>
                </div>
                <span class="badge bg-{{ match($customOrder->status) {
                    'draft' => 'secondary',
                    'submitted', 'under_review', 'needs_information' => 'info',
                    'quote_pending', 'quoted', 'needs_revision' => 'warning',
                    'customer_approved', 'payment_pending' => 'primary',
                    'paid', 'production_pending', 'in_production' => 'success',
                    'quality_check', 'rework_required' => 'info',
                    'ready_for_delivery', 'shipped', 'ready_for_pickup' => 'success',
                    'completed' => 'dark',
                    'cancelled', 'rejected' => 'danger',
                    'quote_expired' => 'warning',
                    default => 'secondary',
                } }} fs-6 px-3 py-2">
                    {{ $customOrder->status_label }}
                </span>
            </div>

            {{-- Child Info --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">Child Information</h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-4"><strong>Name:</strong> {{ $customOrder->child_name ?: '—' }}</div>
                        <div class="col-sm-4"><strong>Age:</strong> {{ $customOrder->child_age ? $customOrder->child_age . ' years' : '—' }}</div>
                        <div class="col-sm-4"><strong>Gender:</strong> {{ ucfirst($customOrder->child_gender ?: '—') }}</div>
                    </div>
                </div>
            </div>

            {{-- Customizations --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">Customization Details</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($customOrder->customizations as $c)
                            <div class="col-sm-6">
                                <span class="text-muted">{{ ucwords(str_replace('_', ' ', $c->attribute)) }}:</span>
                                <strong>{{ $c->value }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Measurements --}}
            @if ($customOrder->measurements->isNotEmpty())
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h6 class="mb-0">Measurements</h6></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            @foreach ($customOrder->measurements as $m)
                                <tr>
                                    <td class="text-muted">{{ ucwords(str_replace('_', ' ', $m->measurement_type)) }}</td>
                                    <td class="fw-bold">{{ $m->measurement_value }} {{ $m->measurement_unit }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            @endif

            {{-- Reference Files --}}
            @if ($customOrder->files->isNotEmpty())
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h6 class="mb-0">Reference Images</h6></div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($customOrder->files as $file)
                                @if (str_starts_with($file->mime_type, 'image/'))
                                    <a href="{{ route('shop.custom-frock.file', [$customOrder, $file]) }}" target="_blank">
                                        <img src="{{ route('shop.custom-frock.file', [$customOrder, $file]) }}" alt="Reference" class="img-thumbnail" style="max-height:120px;">
                                    </a>
                                @else
                                    <a href="{{ route('shop.custom-frock.file', [$customOrder, $file]) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                                        <i class="bi bi-file-earmark me-1"></i> {{ $file->original_filename ?: 'Document' }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Quote --}}
            @if ($approvedQuote || $latestQuote)
                @php $quote = $approvedQuote ?? $latestQuote; @endphp
                <div class="card shadow-sm mb-4 {{ $quote->isApproved() ? 'border-success' : '' }}">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Quotation (v{{ $quote->version }})</h6>
                        @if ($quote->isApproved())
                            <span class="badge bg-success">Approved</span>
                        @elseif ($quote->isExpired())
                            <span class="badge bg-warning">Expired</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            @foreach ($quote->breakdown ?? [] as $item)
                                <tr>
                                    <td>{{ $item['label'] }}</td>
                                    <td class="text-end {{ $item['amount'] < 0 ? 'text-success' : '' }}">
                                        {{ $item['amount'] < 0 ? '-' : '' }}₦{{ number_format(abs($item['amount']), 2) }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="fw-bold border-top">
                                <td>Total</td>
                                <td class="text-end">₦{{ number_format($quote->total, 2) }}</td>
                            </tr>
                        </table>
                        @if ($quote->valid_until)
                            <small class="text-muted">Valid until: {{ $quote->valid_until->format('d M Y') }}</small>
                        @endif
                        @if ($quote->notes)
                            <div class="mt-2 small text-muted">{{ $quote->notes }}</div>
                        @endif

                        @if ($customOrder->status === 'quoted' && !$quote->isExpired())
                            <div class="mt-3 d-flex gap-2">
                                <form method="POST" action="{{ route('shop.custom-frock.approve-quote', $customOrder) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-check-lg me-1"></i> Approve & Pay
                                    </button>
                                </form>
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#requestChangesModal">
                                    Request Changes
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Messages --}}
            @if ($customOrder->messages->isNotEmpty())
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h6 class="mb-0">Messages</h6></div>
                    <div class="card-body">
                        @foreach ($customOrder->messages->sortByDesc('created_at') as $msg)
                            <div class="mb-3 {{ $msg->sender_type === 'customer' ? 'text-end' : '' }}">
                                <div class="d-inline-block text-start {{ $msg->sender_type === 'customer' ? 'bg-primary text-white' : 'bg-light' }} rounded p-3" style="max-width:80%">
                                    <small class="d-block mb-1 fw-bold">{{ $msg->sender_type === 'customer' ? 'You' : 'KidsFlairr' }}</small>
                                    {{ $msg->message }}
                                    <small class="d-block mt-1 opacity-75">{{ $msg->created_at->diffForHumans() }}</small>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            {{-- Status Timeline --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">Status Timeline</h6></div>
                <div class="card-body">
                    @forelse ($customOrder->statusHistory->sortByDesc('created_at') as $history)
                        <div class="d-flex mb-3">
                            <div class="me-3">
                                <div class="bg-primary rounded-circle" style="width:12px;height:12px;margin-top:4px;"></div>
                                @if (!$loop->last)<div class="border-start ms-1" style="height:20px;"></div>@endif
                            </div>
                            <div>
                                <div class="fw-bold small">{{ \App\Models\CustomOrder::STATUS_LABELS[$history->new_status] ?? ucwords(str_replace('_', ' ', $history->new_status)) }}</div>
                                <small class="text-muted">{{ $history->created_at->diffForHumans() }}</small>
                                @if ($history->reason)
                                    <div class="small text-muted">{{ $history->reason }}</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No status updates yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Delivery Info --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">Delivery</h6></div>
                <div class="card-body">
                    <p class="mb-1"><strong>Method:</strong> {{ $customOrder->delivery_method === 'pickup' ? 'Pickup Station' : 'Home Delivery' }}</p>
                    @if ($customOrder->pickupStation)
                        <p class="mb-1"><strong>Station:</strong> {{ $customOrder->pickupStation->name }}</p>
                        <p class="small text-muted">{{ $customOrder->pickupStation->address }}</p>
                    @endif
                    @if ($customOrder->delivery_address)
                        <p class="mb-0"><strong>Address:</strong> {{ $customOrder->delivery_address }}</p>
                    @endif
                </div>
            </div>

            {{-- Actions --}}
            @if (in_array($customOrder->status, ['draft']))
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="POST" action="{{ route('shop.custom-frock.cancel', $customOrder) }}" onsubmit="return confirm('Cancel this custom order?')">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger w-100">Cancel Order</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Request Changes Modal --}}
<div class="modal fade" id="requestChangesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('shop.custom-frock.request-changes', $customOrder) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Request Changes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">What would you like us to change?</label>
                    <textarea name="message" class="form-control" rows="4" required maxlength="1000" placeholder="Describe the changes you'd like..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
