@extends('layouts.admin')

@section('title', 'Custom Order ' . $customOrder->custom_order_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $customOrder->custom_order_number }}</h1>
        <span class="text-muted">{{ $customOrder->user?->name }} &middot; {{ $customOrder->getCustomizationValue('dress_style') ?: 'Custom Frock' }}</span>
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

{{-- Action Buttons --}}
<div class="card shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap gap-2">
        @if ($customOrder->status === 'submitted')
            <form method="POST" action="{{ route('admin.custom-orders.review', $customOrder) }}">@csrf
                <button class="btn btn-info"><i class="bi bi-eye me-1"></i> Mark Under Review</button>
            </form>
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#requestInfoModal"><i class="bi bi-chat-dots me-1"></i> Request Info</button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quoteModal"><i class="bi bi-file-earmark-text me-1"></i> Create Quote</button>
            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="bi bi-x-lg me-1"></i> Reject</button>
        @endif

        @if ($customOrder->status === 'under_review')
            <form method="POST" action="{{ route('admin.custom-orders.approve-for-quote', $customOrder) }}">@csrf
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Approve for Quote</button>
            </form>
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#requestInfoModal"><i class="bi bi-chat-dots me-1"></i> Request Info</button>
            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="bi bi-x-lg me-1"></i> Reject</button>
        @endif

        @if (in_array($customOrder->status, ['quote_pending', 'needs_revision']))
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quoteModal"><i class="bi bi-file-earmark-text me-1"></i> {{ $latestQuote ? 'Revise Quote' : 'Create Quote' }}</button>
        @endif

        @if ($customOrder->status === 'paid' || $customOrder->status === 'production_pending')
            <form method="POST" action="{{ route('admin.custom-orders.start-production', $customOrder) }}">@csrf
                <button class="btn btn-success"><i class="bi bi-play-circle me-1"></i> Start Production</button>
            </form>
        @endif

        @if ($customOrder->status === 'in_production')
            <form method="POST" action="{{ route('admin.custom-orders.submit-for-qc', $customOrder) }}">@csrf
                <button class="btn btn-info"><i class="bi bi-clipboard-check me-1"></i> Submit for QC</button>
            </form>
        @endif

        @if ($customOrder->status === 'quality_check')
            <form method="POST" action="{{ route('admin.custom-orders.quality-check', $customOrder) }}" class="d-inline">@csrf
                <input type="hidden" name="passed" value="1">
                <button class="btn btn-success"><i class="bi bi-check-circle me-1"></i> QC Passed</button>
            </form>
            <form method="POST" action="{{ route('admin.custom-orders.quality-check', $customOrder) }}" class="d-inline">@csrf
                <input type="hidden" name="passed" value="0">
                <button class="btn btn-warning"><i class="bi bi-arrow-counterclockwise me-1"></i> QC Failed</button>
            </form>
        @endif

        @if ($customOrder->status === 'ready_for_delivery' && $customOrder->delivery_method === 'delivery')
            <form method="POST" action="{{ route('admin.custom-orders.mark-shipped', $customOrder) }}">@csrf
                <button class="btn btn-success"><i class="bi bi-truck me-1"></i> Mark Shipped</button>
            </form>
        @endif

        @if (in_array($customOrder->status, ['ready_for_delivery', 'ready_for_pickup', 'shipped']))
            <form method="POST" action="{{ route('admin.custom-orders.complete', $customOrder) }}">@csrf
                <button class="btn btn-dark"><i class="bi bi-check2-all me-1"></i> Complete</button>
            </form>
        @endif
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        {{-- Customer Info --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header"><h6 class="mb-0">Customer & Child Information</h6></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-6">
                        <strong>Customer:</strong> {{ $customOrder->user?->name }}<br>
                        <strong>Email:</strong> {{ $customOrder->user?->email }}<br>
                        <strong>Phone:</strong> {{ $customOrder->user?->phone ?: '—' }}
                    </div>
                    <div class="col-sm-6">
                        <strong>Child:</strong> {{ $customOrder->child_name ?: '—' }}<br>
                        <strong>Age:</strong> {{ $customOrder->child_age ? $customOrder->child_age . ' years' : '—' }}<br>
                        <strong>Gender:</strong> {{ ucfirst($customOrder->child_gender ?: '—') }}
                    </div>
                </div>
                @if ($customOrder->delivery_address)
                    <hr>
                    <strong>Delivery Address:</strong> {{ $customOrder->delivery_address }}
                @endif
                @if ($customOrder->pickupStation)
                    <hr>
                    <strong>Pickup Station:</strong> {{ $customOrder->pickupStation->name }} — {{ $customOrder->pickupStation->address }}
                @endif
            </div>
        </div>

        {{-- Customizations --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header"><h6 class="mb-0">Customization Details</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach ($customOrder->customizations as $c)
                        <div class="col-sm-6">
                            <span class="text-muted small">{{ ucwords(str_replace('_', ' ', $c->attribute)) }}:</span><br>
                            <strong>{{ $c->value }}</strong>
                        </div>
                    @endforeach
                </div>
                @if ($customOrder->custom_colour_description)
                    <hr>
                    <strong>Custom Colour Description:</strong> {{ $customOrder->custom_colour_description }}
                @endif
                @if ($customOrder->customer_notes)
                    <hr>
                    <strong>Customer Notes:</strong> {{ $customOrder->customer_notes }}
                @endif
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

        {{-- Files --}}
        @if ($customOrder->files->isNotEmpty())
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">Uploaded Files</h6></div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($customOrder->files as $file)
                            @if (str_starts_with($file->mime_type, 'image/'))
                                <a href="{{ route('admin.custom-orders.file', [$customOrder, $file]) }}" target="_blank">
                                    <img src="{{ route('admin.custom-orders.file', [$customOrder, $file]) }}" class="img-thumbnail" style="max-height:120px;" alt="{{ $file->file_type }}">
                                </a>
                            @else
                                <a href="{{ route('admin.custom-orders.file', [$customOrder, $file]) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                                    <i class="bi bi-file-earmark me-1"></i> {{ $file->original_filename ?: $file->file_type }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Quote --}}
        @if ($latestQuote)
            <div class="card shadow-sm mb-4 {{ $approvedQuote ? 'border-success' : '' }}">
                <div class="card-header d-flex justify-content-between">
                    <h6 class="mb-0">Quote v{{ $latestQuote->version }}</h6>
                    <span class="badge bg-{{ $latestQuote->isApproved() ? 'success' : ($latestQuote->isExpired() ? 'warning' : 'secondary') }}">
                        {{ ucfirst($latestQuote->status) }}
                    </span>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        @foreach ($latestQuote->breakdown ?? [] as $item)
                            <tr>
                                <td>{{ $item['label'] }}</td>
                                <td class="text-end">₦{{ number_format($item['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="fw-bold border-top">
                            <td>Total</td>
                            <td class="text-end">₦{{ number_format($latestQuote->total, 2) }}</td>
                        </tr>
                    </table>
                    @if ($latestQuote->valid_until)
                        <small class="text-muted">Valid until: {{ $latestQuote->valid_until->format('d M Y') }}</small>
                    @endif
                    @if ($latestQuote->notes)
                        <div class="mt-2 small">{{ $latestQuote->notes }}</div>
                    @endif
                </div>
            </div>
        @endif

        {{-- QC Checklist --}}
        @if ($customOrder->status === 'quality_check' || $customOrder->qcChecks->isNotEmpty())
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">QC Checklist</h6></div>
                <div class="card-body">
                    @foreach ($customOrder->qcChecks as $check)
                        <div class="d-flex align-items-center mb-2">
                            <form method="POST" action="{{ route('admin.custom-orders.qc-check.update', [$customOrder, $check]) }}" class="d-flex align-items-center gap-2 flex-fill">
                                @csrf @method('PATCH')
                                <input type="hidden" name="passed" value="{{ $check->passed ? '0' : '1' }}">
                                <button type="submit" class="btn btn-sm {{ $check->passed ? 'btn-success' : ($check->passed === false ? 'btn-danger' : 'btn-outline-secondary') }} rounded-circle" style="width:24px;height:24px;">
                                    @if ($check->passed === true) <i class="bi bi-check"></i> @elseif ($check->passed === false) <i class="bi bi-x"></i> @endif
                                </button>
                                <span class="{{ $check->passed ? 'text-decoration-line-through text-muted' : '' }}">{{ $check->check_item }}</span>
                                @if ($check->checked_at)
                                    <small class="text-muted ms-auto">{{ $check->checked_at->diffForHumans() }}</small>
                                @endif
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Messages --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header"><h6 class="mb-0">Messages</h6></div>
            <div class="card-body">
                @forelse ($customOrder->messages->sortBy('created_at') as $msg)
                    <div class="mb-3 {{ $msg->sender_type === 'admin' ? 'text-end' : '' }}">
                        <div class="d-inline-block text-start {{ $msg->sender_type === 'admin' ? 'bg-primary text-white' : 'bg-light' }} rounded p-3" style="max-width:80%">
                            <small class="d-block mb-1 fw-bold">{{ $msg->sender_type === 'admin' ? 'Admin' : ($msg->sender?->name ?: 'Customer') }}</small>
                            {{ $msg->message }}
                            <small class="d-block mt-1 opacity-75">{{ $msg->created_at->diffForHumans() }}</small>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-3">No messages yet.</p>
                @endforelse

                <form method="POST" action="{{ route('admin.custom-orders.message', $customOrder) }}" class="mt-3">
                    @csrf
                    <div class="input-group">
                        <input type="text" name="message" class="form-control" placeholder="Type a message..." required maxlength="1000">
                        <button type="submit" class="btn btn-primary">Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">
        {{-- Status Timeline --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header"><h6 class="mb-0">Status History</h6></div>
            <div class="card-body">
                @forelse ($customOrder->statusHistory->sortByDesc('created_at') as $history)
                    <div class="d-flex mb-2">
                        <div class="me-3">
                            <div class="bg-primary rounded-circle" style="width:10px;height:10px;margin-top:5px;"></div>
                        </div>
                        <div>
                            <div class="small fw-bold">{{ \App\Models\CustomOrder::STATUS_LABELS[$history->new_status] ?? $history->new_status }}</div>
                            <small class="text-muted">{{ $history->created_at->diffForHumans() }} by {{ $history->changer?->name ?: 'System' }}</small>
                            @if ($history->reason)<div class="small text-muted">{{ $history->reason }}</div>@endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted small">No history yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Linked Order --}}
        @if ($customOrder->order)
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">Linked Order</h6></div>
                <div class="card-body">
                    <a href="{{ route('admin.orders.show', $customOrder->order) }}">{{ $customOrder->order->reference }}</a>
                    <span class="badge bg-{{ $customOrder->order->status === 'confirmed' ? 'success' : 'secondary' }} ms-2">{{ ucfirst($customOrder->order->status) }}</span>
                </div>
            </div>
        @endif

        {{-- Admin Notes --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header"><h6 class="mb-0">Admin Notes (Internal)</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.custom-orders.update-notes', $customOrder) }}">
                    @csrf @method('PATCH')
                    <textarea name="admin_notes" class="form-control" rows="3">{{ $customOrder->admin_notes }}</textarea>
                    <button type="submit" class="btn btn-sm btn-outline-primary mt-2">Save Notes</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Request Info Modal --}}
<div class="modal fade" id="requestInfoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.custom-orders.request-info', $customOrder) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Request More Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">What information do you need from the customer?</label>
                    <textarea name="message" class="form-control" rows="4" required maxlength="1000"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Send Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.custom-orders.reject', $customOrder) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Reason for rejection</label>
                    <textarea name="reason" class="form-control" rows="3" required maxlength="500"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Quote Modal --}}
<div class="modal fade" id="quoteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.custom-orders.quote', $customOrder) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ $latestQuote ? 'Revise Quote' : 'Create Quote' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Base Frock Price</label>
                            <input type="number" name="base_price" class="form-control" step="0.01" min="0" value="{{ $latestQuote?->base_price ?? 12000 }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fabric Cost</label>
                            <input type="number" name="fabric_cost" class="form-control" step="0.01" min="0" value="{{ $latestQuote?->fabric_cost ?? 0 }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Customization Cost</label>
                            <input type="number" name="customization_cost" class="form-control" step="0.01" min="0" value="{{ $latestQuote?->customization_cost ?? 0 }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Embellishment Cost</label>
                            <input type="number" name="embellishment_cost" class="form-control" step="0.01" min="0" value="{{ $latestQuote?->embellishment_cost ?? 0 }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Measurement Fee</label>
                            <input type="number" name="measurement_fee" class="form-control" step="0.01" min="0" value="{{ $latestQuote?->measurement_fee ?? 0 }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rush Fee</label>
                            <input type="number" name="rush_fee" class="form-control" step="0.01" min="0" value="{{ $latestQuote?->rush_fee ?? 0 }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Delivery Fee</label>
                            <input type="number" name="delivery_fee" class="form-control" step="0.01" min="0" value="{{ $latestQuote?->delivery_fee ?? 0 }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Discount</label>
                            <input type="number" name="discount" class="form-control" step="0.01" min="0" value="{{ $latestQuote?->discount ?? 0 }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Total</label>
                            <input type="number" name="total" id="quoteTotal" class="form-control fw-bold" step="0.01" min="0" value="{{ $latestQuote?->total ?? 0 }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Valid for (days)</label>
                            <input type="number" name="valid_days" class="form-control" min="1" max="90" value="7" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes (visible to customer)</label>
                            <textarea name="notes" class="form-control" rows="2" maxlength="1000">{{ $latestQuote?->notes }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Quote</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fields = ['base_price', 'fabric_cost', 'customization_cost', 'embellishment_cost', 'measurement_fee', 'rush_fee', 'delivery_fee', 'discount'];
    const totalInput = document.getElementById('quoteTotal');

    function recalc() {
        let total = 0;
        fields.forEach(f => {
            const el = document.querySelector(`[name="${f}"]`);
            if (el) total += parseFloat(el.value) || 0;
        });
        const discount = parseFloat(document.querySelector('[name="discount"]')?.value) || 0;
        total -= discount;
        totalInput.value = Math.max(0, total).toFixed(2);
    }

    fields.forEach(f => {
        const el = document.querySelector(`[name="${f}"]`);
        if (el) el.addEventListener('input', recalc);
    });
});
</script>
@endpush
