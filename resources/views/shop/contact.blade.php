@extends('layouts.shop', ['title' => 'Contact Us'])

@section('content')

{{-- Hero --}}
<div class="hero mb-5">
    <div class="row align-items-center position-relative" style="z-index:1;">
        <div class="col-md-8">
            <span class="badge bg-white text-dark mb-3 px-3 py-2" style="border-radius:50px;">
                <i class="bi bi-chat-heart-fill text-danger"></i> Say Hello
            </span>
            <h1 class="fw-bold mb-3">{{ $contact->hero_title }}</h1>
            <p class="lead mb-0 opacity-90">{{ $contact->hero_subtitle }}</p>
        </div>
        <div class="col-md-4 text-center d-none d-md-block">
            <span class="floaty" style="font-size:7rem;">&#128140;</span>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">

    {{-- Contact Form --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100" style="border-radius:1.25rem;">
            <div class="card-body p-4 p-md-5">
                <h4 class="mb-1" style="font-family:'Fredoka',sans-serif;">Send Us a Message</h4>
                @if($contact->intro)
                    <p class="text-muted mb-4">{{ $contact->intro }}</p>
                @endif

                @if(session('success'))
                    <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
                        <i class="bi bi-check-circle-fill"></i>
                        {{ session('success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('shop.contact.send') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Your Name</label>
                            <input type="text" name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" placeholder="Jane Smith" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email') }}" placeholder="jane@example.com" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subject</label>
                            <select name="subject_type" id="subject_type"
                                    class="form-select @error('subject_type') is-invalid @enderror" required>
                                <option value=""{{ old('subject_type') === '' ? ' selected' : '' }}>Select subject</option>
                                <option value="Request"{{ old('subject_type') === 'Request' ? ' selected' : '' }}>Request</option>
                                <option value="Enquiry"{{ old('subject_type') === 'Enquiry' ? ' selected' : '' }}>Enquiry</option>
                                <option value="Complaint"{{ old('subject_type') === 'Complaint' ? ' selected' : '' }}>Complaint</option>
                                <option value="Feedback"{{ old('subject_type') === 'Feedback' ? ' selected' : '' }}>Feedback</option>
                                <option value="Other"{{ old('subject_type') === 'Other' ? ' selected' : '' }}>Other</option>
                            </select>
                            @error('subject_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12" id="subject-other-group" style="{{ old('subject_type') === 'Other' ? '' : 'display:none;' }}">
                            <label class="form-label">Other Subject</label>
                            <input type="text" name="subject_other"
                                   class="form-control @error('subject_other') is-invalid @enderror"
                                   value="{{ old('subject_other') }}" placeholder="Please describe the subject">
                            @error('subject_other')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Message</label>
                            <textarea name="message" rows="5"
                                      class="form-control @error('message') is-invalid @enderror"
                                      placeholder="Tell us what's on your mind..." required>{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-send-fill"></i> Send Message
                            </button>
                        </div>
                    </div>
                </form>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var subjectType = document.getElementById('subject_type');
                        var otherGroup = document.getElementById('subject-other-group');

                        function toggleOtherSubject() {
                            if (!subjectType) {
                                return;
                            }
                            otherGroup.style.display = subjectType.value === 'Other' ? '' : 'none';
                        }

                        if (subjectType) {
                            subjectType.addEventListener('change', toggleOtherSubject);
                            toggleOtherSubject();
                        }
                    });
                </script>
            </div>
        </div>
    </div>

    {{-- Contact Details --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100" style="border-radius:1.25rem;">
            <div class="card-body p-4 p-md-5">
                <h4 class="mb-4" style="font-family:'Fredoka',sans-serif;">Find Us</h4>

                <div class="d-flex flex-column gap-4">
                    @if($contact->email)
                    <div class="d-flex align-items-start gap-3">
                        <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                              style="width:48px;height:48px;background:var(--kid-pink);color:#fff;font-size:1.3rem;margin-top:2px;">
                            <i class="bi bi-envelope-fill"></i>
                        </span>
                        <div>
                            <div class="fw-semibold">Email</div>
                            <a href="mailto:{{ $contact->email }}" class="text-decoration-none text-muted">{{ $contact->email }}</a>
                        </div>
                    </div>
                    @endif

                    @if($contact->phone)
                    <div class="d-flex align-items-start gap-3">
                        <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                              style="width:48px;height:48px;background:var(--kid-blue);color:#fff;font-size:1.3rem;margin-top:2px;">
                            <i class="bi bi-telephone-fill"></i>
                        </span>
                        <div>
                            <div class="fw-semibold">Phone</div>
                            <a href="tel:{{ preg_replace('/\s+/', '', $contact->phone) }}" class="text-decoration-none text-muted">{{ $contact->phone }}</a>
                        </div>
                    </div>
                    @endif

                    @if($contact->address)
                    <div class="d-flex align-items-start gap-3">
                        <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                              style="width:48px;height:48px;background:var(--kid-green);color:#fff;font-size:1.3rem;margin-top:2px;">
                            <i class="bi bi-geo-alt-fill"></i>
                        </span>
                        <div>
                            <div class="fw-semibold">Address</div>
                            <span class="text-muted">{{ $contact->address }}</span>
                        </div>
                    </div>
                    @endif

                    @if($contact->hours)
                    <div class="d-flex align-items-start gap-3">
                        <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                              style="width:48px;height:48px;background:var(--kid-purple);color:#fff;font-size:1.3rem;margin-top:2px;">
                            <i class="bi bi-clock-fill"></i>
                        </span>
                        <div>
                            <div class="fw-semibold">Business Hours</div>
                            <span class="text-muted">{{ $contact->hours }}</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
