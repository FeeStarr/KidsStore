@extends('layouts.shop')

@section('title', $customOrder->lifecycle_label . ' ' . $customOrder->custom_order_number)

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
                    {{ $customOrder->customer_status_label }}
                </span>
            </div>

            {{-- Needs Information: action card --}}
            @if ($customOrder->status === 'needs_information')
                <div class="alert alert-warning d-flex justify-content-between align-items-center gap-2 flex-wrap mb-4">
                    <div>
                        <i class="bi bi-exclamation-circle me-1"></i>
                        <strong>Action needed:</strong> KidsFlairr requested more information about your order. Please reply in the Messages box below with the details.
                    </div>
                    <form method="POST" action="{{ route('shop.custom-frock.confirm-info', $customOrder) }}">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm flex-shrink-0">I've Provided the Requested Information</button>
                    </form>
                </div>
            @endif

            {{-- Child Info --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">Child Information</h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-4"><strong>Name:</strong> {{ $customOrder->child_name ?: '-' }}</div>
                        <div class="col-sm-4"><strong>Age:</strong> {{ $customOrder->child_age ? $customOrder->child_age . ' years' : '-' }}</div>
                        <div class="col-sm-4"><strong>Gender:</strong> {{ ucfirst($customOrder->child_gender ?: '-') }}</div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-sm-4"><strong>Category:</strong> {{ $customOrder->category_label ?: '-' }}</div>
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

            {{-- Sizing confirmation: what the customer agrees to, and the evidence
                 that agreement is recorded as. --}}
            @if ($customOrder->sizing_confirmed_at)
                <div class="card shadow-sm mb-4 border-success">
                    <div class="card-header bg-success bg-opacity-10">
                        <h6 class="mb-0"><i class="bi bi-check-circle me-1"></i>Sizing confirmed</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">
                            Confirmed on {{ $customOrder->sizing_confirmed_at->format('j M Y, g:i a') }}. We have recorded
                            the size, the fitting and the measurements you agreed to, so nothing can change them later.
                        </p>
                        <div class="small">
                            <div><strong>Cut to:</strong>
                                @if ($customOrder->productionBasis() === \App\Models\CustomOrder::SIZING_MEASUREMENTS)
                                    Your child's measurements
                                @elseif ($customOrder->selected_chart_size)
                                    Standard size {{ $customOrder->selected_chart_size }}
                                    {{ $customOrder->selected_chart_fit
                                        ? (\App\Models\SizeChart::FIT_LABELS[$customOrder->selected_chart_fit] ?? $customOrder->selected_chart_fit)
                                        : \App\Models\SizeChartEntry::FIT_UNASSIGNED_LABEL }}
                                @else
                                    Not yet chosen
                                @endif
                            </div>
                            @if ($customOrder->selected_chart_size)
                                <div><strong>Age band:</strong> {{ $customOrder->selected_chart_size }}</div>
                            @endif
                            @if ($customOrder->selected_chart_fit)
                                <div><strong>Fitting:</strong> {{ \App\Models\SizeChart::FIT_LABELS[$customOrder->selected_chart_fit] ?? $customOrder->selected_chart_fit }}</div>
                            @elseif ($customOrder->selected_chart_size)
                                <div><strong>Fitting:</strong> {{ \App\Models\SizeChartEntry::FIT_UNASSIGNED_LABEL }}</div>
                            @endif
                            @if ($customOrder->productionBasis() !== \App\Models\CustomOrder::SIZING_MEASUREMENTS)
                                <div class="text-muted mt-1">
                                    A standard fitting is a starting point for the cut, not a guarantee of an exact fit.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @elseif (! $customOrder->production_started_at && in_array($customOrder->status, ['submitted', 'under_review', 'quote_pending', 'quoted', 'customer_approved', 'payment_pending', 'paid'], true))
                <div class="card shadow-sm mb-4 border-warning">
                    <div class="card-header bg-warning bg-opacity-10">
                        <h6 class="mb-0"><i class="bi bi-rulers me-1"></i>Please confirm the sizing</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">
                            Before we cut your frock, please check the size below and confirm it. We record your
                            confirmation along with the measurements you saw, so the size we cut to is the size you agreed to.
                        </p>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <div class="small text-muted">What we will cut it to</div>
                                <div class="fw-bold">
                                    @if ($customOrder->productionBasis() === \App\Models\CustomOrder::SIZING_MEASUREMENTS)
                                        Your child's measurements
                                    @elseif ($customOrder->selected_chart_size)
                                        Standard size {{ $customOrder->selected_chart_size }}
                                        {{ $customOrder->selected_chart_fit
                                            ? (\App\Models\SizeChart::FIT_LABELS[$customOrder->selected_chart_fit] ?? $customOrder->selected_chart_fit)
                                            : \App\Models\SizeChartEntry::FIT_UNASSIGNED_LABEL }}
                                    @else
                                        Not yet chosen
                                    @endif
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="small text-muted">Age band and fitting</div>
                                <div class="fw-bold">
                                    @if ($customOrder->selected_chart_size)
                                        {{ $customOrder->selected_chart_size }}
                                        {{ $customOrder->selected_chart_fit
                                            ? (\App\Models\SizeChart::FIT_LABELS[$customOrder->selected_chart_fit] ?? $customOrder->selected_chart_fit)
                                            : \App\Models\SizeChartEntry::FIT_UNASSIGNED_LABEL }}
                                    @else
                                        &mdash;
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($customOrder->productionBasis() === \App\Models\CustomOrder::SIZING_MEASUREMENTS)
                            <div class="alert alert-info small mb-3">
                                <i class="bi bi-rulers me-1"></i>
                                We will cut this frock to the measurements you gave us, so those are the figures it is
                                made to &mdash; not the size on the chart.
                            </div>
                        @else
                            <div class="alert alert-warning small mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                A standard fitting gives us a <strong>starting point</strong> to cut from, not a guarantee of an exact fit.
                                If your child's measurements fall outside the range below, send them to us
                                and we will cut to your measurements instead.
                            </div>
                        @endif

                        @if ($sizingFigures->isNotEmpty())
                            <div class="mb-3">
                                <div class="small fw-semibold mb-1">
                                    @if ($customOrder->productionBasis() === \App\Models\CustomOrder::SIZING_MEASUREMENTS)
                                        The size we would otherwise have used
                                    @else
                                        The measurements for this size
                                    @endif
                                </div>
                                <table class="table table-sm table-bordered mb-2">
                                    <thead>
                                        <tr>
                                            <th>Measurement</th>
                                            <th>Range</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($sizingFigures as $figure)
                                            <tr>
                                                <td>{{ $figure['label'] }}</td>
                                                <td>
                                                    @if ($figure['min'] !== null && $figure['max'] !== null)
                                                        {{ $figure['min'] }} &ndash; {{ $figure['max'] }} {{ $figure['unit'] }}
                                                    @elseif ($figure['min'] !== null)
                                                        from {{ $figure['min'] }} {{ $figure['unit'] }}
                                                    @elseif ($figure['max'] !== null)
                                                        up to {{ $figure['max'] }} {{ $figure['unit'] }}
                                                    @else
                                                        &mdash;
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @foreach ($sizingFigures as $figure)
                                    @if ($figure['guide'])
                                        <div class="small text-muted mb-1">
                                            <strong>{{ $figure['guide']->name }}:</strong> {{ $figure['guide']->description }}
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        @if ($customOrder->measurements->isNotEmpty())
                            <div class="mb-3">
                                <div class="small fw-semibold mb-1">We will cut it to these measurements</div>
                                <table class="table table-sm table-bordered mb-0">
                                    <tbody>
                                        @foreach ($customOrder->measurements as $m)
                                            <tr>
                                                <td>{{ ucwords(str_replace('_', ' ', $m->measurement_type)) }}</td>
                                                <td class="fw-bold">{{ $m->measurement_value }} {{ $m->measurement_unit }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('shop.custom-frock.confirm-sizing', $customOrder) }}">
                            @csrf

                            @if ($customOrder->requires_measurement_confirmation)
                                <div class="alert alert-warning small">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    This design is cut to fit closely, so we need your child's <strong>current</strong>
                                    measurements to check against the size above.
                                </div>

                                @if ($customOrder->measurements->isNotEmpty())
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="reuse_production_measurements" value="1"
                                               id="reuseProductionMeasurements">
                                        <label class="form-check-label small" for="reuseProductionMeasurements">
                                            The measurements I gave above are my child's current measurements, and you may
                                            use them to check against the size.
                                        </label>
                                    </div>
                                    <div class="small text-muted mb-2">
                                        If they are not current, enter today's measurements below instead.
                                    </div>
                                @endif

                                <div class="row g-2 mb-3">
                                    @foreach ($comparisonFields as $field)
                                        <div class="col-sm-6">
                                            <label class="form-label small mb-0" for="cm_{{ $field->name }}">{{ $field->label }}</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="0.1" min="1" max="500" id="cm_{{ $field->name }}"
                                                       class="form-control" name="comparison_measurements[{{ $field->name }}][value]"
                                                       value="{{ old('comparison_measurements.'.$field->name.'.value') }}">
                                                <span class="input-group-text">cm</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="sizing_policy_acknowledged" value="1"
                                       id="sizingPolicyAcknowledged" required>
                                <label class="form-check-label small" for="sizingPolicyAcknowledged">
                                    I have checked the size and measurements above and they are right for my child.
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="sizing_confirmed" value="1"
                                       id="sizingConfirmed" required>
                                <label class="form-check-label small" for="sizingConfirmed">
                                    @if ($customOrder->productionBasis() === \App\Models\CustomOrder::SIZING_MEASUREMENTS)
                                        I confirm the sizing for this order, and I understand the frock will be cut to
                                        the measurements I gave you.
                                    @else
                                        I confirm the sizing for this order, and I understand the frock will be cut
                                        from this size as a starting point.
                                    @endif
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Confirm sizing
                            </button>
                        </form>
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

            {{-- Customer Notes --}}
            @if ($customOrder->customer_notes)
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h6 class="mb-0">Your Notes</h6></div>
                    <div class="card-body">
                        <p class="mb-0">{{ $customOrder->customer_notes }}</p>
                    </div>
                </div>
            @endif

            {{-- Customer Visible Notes --}}
            @if ($customOrder->customer_visible_notes)
                <div class="card shadow-sm mb-4 border-info">
                    <div class="card-header bg-info bg-opacity-10"><h6 class="mb-0 text-info"><i class="bi bi-info-circle me-1"></i> Note from KidsFlairr</h6></div>
                    <div class="card-body">
                        <p class="mb-0">{{ $customOrder->customer_visible_notes }}</p>
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
                                    <td>@include('partials.quote-line-label')</td>
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
                            <small class="text-muted">Valid until: {{ $quote->valid_until->format('d M Y g:i A') }}</small>
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
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">Messages</h6></div>
                <div class="card-body">
                    @forelse ($customOrder->messages->sortByDesc('created_at') as $msg)
                        <div class="mb-3 {{ $msg->sender_type === 'customer' ? 'text-end' : '' }}">
                            <div class="d-inline-block text-start {{ $msg->sender_type === 'customer' ? 'bg-primary text-white' : 'bg-light' }} rounded p-3" style="max-width:80%">
                                <small class="d-block mb-1 fw-bold">{{ $msg->sender_type === 'customer' ? 'You' : 'KidsFlairr' }}</small>
                                @if ($msg->message !== '')
                                    <div>{{ $msg->message }}</div>
                                @endif
                                @if ($msg->file)
                                    @if (str_starts_with($msg->file->mime_type ?? '', 'image/'))
                                        <a href="{{ route('shop.custom-frock.file', [$customOrder, $msg->file]) }}" target="_blank" class="d-inline-block mt-1">
                                            <img src="{{ route('shop.custom-frock.file', [$customOrder, $msg->file]) }}" alt="Attachment" class="img-thumbnail" style="max-height:120px;" loading="lazy">
                                        </a>
                                    @else
                                        <a href="{{ route('shop.custom-frock.file', [$customOrder, $msg->file]) }}" target="_blank"
                                           class="btn btn-sm {{ $msg->sender_type === 'customer' ? 'btn-light text-dark' : 'btn-outline-secondary' }} mt-1">
                                            <i class="bi bi-paperclip me-1"></i>{{ $msg->file->original_filename ?: 'Attachment' }}
                                        </a>
                                    @endif
                                @endif
                                <small class="d-block mt-1 opacity-75">{{ $msg->created_at->diffForHumans() }}</small>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No messages yet. Ask us anything about your order &mdash; we'll reply here.</p>
                    @endforelse

                    <form method="POST" action="{{ route('shop.custom-frock.messages', $customOrder) }}" enctype="multipart/form-data" class="mt-3">
                        @csrf
                        <div class="mb-2">
                            <textarea name="message" class="form-control" rows="2" maxlength="1000" placeholder="Write a reply...">{{ old('message') }}</textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <input type="file" name="attachment" class="form-control form-control-sm" style="max-width:60%;" accept=".jpg,.jpeg,.png,.webp,.pdf">
                            <button type="submit" class="btn btn-primary btn-sm flex-shrink-0"><i class="bi bi-send me-1"></i>Send</button>
                        </div>
                        <small class="text-muted d-block mt-1">jpg, png, webp or pdf &mdash; max {{ \App\Services\CustomFileService::MESSAGE_MAX_MB }}MB</small>
                    </form>
                </div>
            </div>
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
                                <div class="fw-bold small">{{ \App\Models\CustomOrder::CUSTOMER_STATUS_LABELS[$history->new_status] ?? ucwords(str_replace('_', ' ', $history->new_status)) }}</div>
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
                    @if ($customOrder->deliveryLocation)
                        <p class="mb-1"><strong>Location:</strong> {{ $customOrder->deliveryLocation->name }}{{ $customOrder->deliveryLocation->state ? ', ' . $customOrder->deliveryLocation->state : '' }}</p>
                    @endif
                    @if ($customOrder->delivery_address)
                        <p class="mb-1"><strong>Address:</strong> {{ $customOrder->delivery_address }}</p>
                    @endif
                    {{-- No delivery price here. Delivery is quoted, and the quote is the
                         only figure the customer is charged. --}}
                </div>
            </div>

            {{-- Payment Info --}}
            @if ($customOrder->total_amount > 0)
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h6 class="mb-0">Payment</h6></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total</span>
                            <strong>₦{{ number_format($customOrder->total_amount, 2) }}</strong>
                        </div>
                        @if ($customOrder->amount_paid > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Paid</span>
                                <span class="text-success">₦{{ number_format($customOrder->amount_paid, 2) }}</span>
                            </div>
                        @endif
                        @php $outstanding = max(0, $customOrder->total_amount - $customOrder->amount_paid); @endphp
                        @if ($outstanding > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Outstanding</span>
                                <span class="text-danger fw-bold">₦{{ number_format($outstanding, 2) }}</span>
                            </div>
                        @endif
                        <div class="mt-2">
                            <span class="badge {{ $customOrder->payment_status === 'paid' ? 'text-bg-success' : 'text-bg-warning text-dark' }}">
                                {{ ucfirst($customOrder->payment_status) }}
                            </span>
                        </div>
                        @php $linkedOrder = $customOrder->order; @endphp
                        @if ($customOrder->isStrandedAfterApproval())
                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Your approval needs confirming and no order has been created yet,
                                so there is nothing to pay. Please contact our support team and we
                                will sort it out for you.
                            </div>
                        @elseif ($linkedOrder)
                            <p class="small text-muted mt-3 mb-1">
                                Payment is handled on order
                                <a href="{{ route('shop.account.orders.show', $linkedOrder) }}">{{ $linkedOrder->reference }}</a>.
                            </p>
                            @if ($linkedOrder->isPayNowEligible())
                                <a href="{{ route('shop.account.orders.show', $linkedOrder) }}" class="btn btn-primary w-100">
                                    <i class="bi bi-credit-card me-1"></i>Pay now
                                </a>
                            @elseif ($linkedOrder->status === \App\Models\Order::STATUS_DELIVERED)
                                {{-- A delivered frock can be returned or refunded. The request
                                     lives with the order, so the action is there too. --}}
                                <a href="{{ route('shop.account.orders.show', $linkedOrder) }}" class="btn btn-outline-warning w-100">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Request a return or refund
                                </a>
                            @endif
                        @endif
                    </div>
                </div>
            @endif

            {{-- Actions --}}
            @if ($customOrder->isCancellableByCustomer())
                <div class="card shadow-sm mb-4 border-danger">
                    <div class="card-header text-danger"><h6 class="mb-0">Changed your mind?</h6></div>
                    <div class="card-body">
                        <p class="small text-muted">
                            You can cancel this request until it has been paid for. Please tell us why.
                        </p>
                        <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#cancelRequestModal">
                            <i class="bi bi-x-circle me-1"></i>Cancel this request
                        </button>
                    </div>
                </div>

                <div class="modal fade" id="cancelRequestModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('shop.custom-frock.cancel', $customOrder) }}">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title">Cancel this request</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <label class="form-label" for="cancelReason">Why are you cancelling?</label>
                                    <textarea name="reason" id="cancelReason" class="form-control" rows="3"
                                              required maxlength="500"
                                              placeholder="e.g. The size does not suit her any more"></textarea>
                                    <small class="text-muted">A sentence is plenty. It helps us do better next time.</small>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep my request</button>
                                    <button type="submit" class="btn btn-danger">Cancel request</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Create Similar Order --}}
            @if ($customOrder->status === 'completed')
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <a href="{{ route('shop.custom-frock.create-similar', $customOrder) }}" class="btn btn-outline-primary w-100">
                            <i class="bi bi-arrow-repeat me-1"></i> Create Similar Order
                        </a>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.querySelector('form[action*="/messages"] input[name="attachment"]');
    if (!input) return;
    input.addEventListener('change', function () {
        const max = {{ \App\Services\CustomFileService::MESSAGE_MAX_MB }} * 1024 * 1024;
        const file = this.files[0];
        if (file && file.size > max) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Image too large',
                    text: 'Maximum size is {{ \App\Services\CustomFileService::MESSAGE_MAX_MB }}MB. Please choose a smaller image.'
                });
            }
            this.value = '';
        }
    });
});
</script>
@endsection
