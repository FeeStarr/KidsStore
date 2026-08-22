@extends('layouts.shop')

@section('title', 'Cookie Policy')

@section('content')
<div class="container py-5" style="max-width:800px;">
    <h2 class="fw-bold mb-4">Cookie Policy</h2>
    <p class="text-muted mb-4">Last updated: {{ date('F d, Y') }}</p>

    <h5 class="fw-bold mt-4">What Are Cookies</h5>
    <p>Cookies are small text files stored on your device when you visit a website. They help the site remember your preferences and keep your session secure.</p>

    <h5 class="fw-bold mt-4">How We Use Cookies</h5>
    <p>KidsFlairr uses only the cookies necessary to make the website work properly. We do not use advertising or tracking cookies at this time.</p>

    <h5 class="fw-bold mt-4">Cookies We Use</h5>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Cookie</th>
                    <th>Purpose</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>session</code></td>
                    <td>Keeps you logged in and maintains your shopping cart</td>
                    <td>Session</td>
                </tr>
                <tr>
                    <td><code>XSRF-TOKEN</code></td>
                    <td>Protects against cross-site request forgery (security)</td>
                    <td>Session</td>
                </tr>
                <tr>
                    <td><code>kidsflairr_cookies_accepted</code></td>
                    <td>Remembers that you accepted our cookie notice</td>
                    <td>1 year</td>
                </tr>
                <tr>
                    <td><code>kidsflairr_pwa_installed</code></td>
                    <td>Remembers that you installed our app</td>
                    <td>Persistent</td>
                </tr>
                <tr>
                    <td><code>kidsflairr_pwa_dismissed</code></td>
                    <td>Remembers that you dismissed the install prompt</td>
                    <td>Persistent</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h5 class="fw-bold mt-4">Managing Cookies</h5>
    <p>You can control cookies through your browser settings. Blocking essential cookies may prevent the site from working correctly (e.g., login, cart, checkout).</p>

    <h5 class="fw-bold mt-4">Changes to This Policy</h5>
    <p>We may update this policy from time to time. Any changes will be posted on this page.</p>

    <h5 class="fw-bold mt-4">Contact Us</h5>
    <p>If you have questions about our use of cookies, please <a href="{{ route('shop.contact') }}">contact us</a>.</p>
</div>
@endsection
