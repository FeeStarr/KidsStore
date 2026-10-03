<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\PolicyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

class HelpController extends Controller
{
    /**
     * Used when an admin has not saved custom help content yet.
     * Screenshots in public/images/help/ attach automatically: a section
     * numbered "N" picks up N.png (or N-anything.png), and a section titled
     * "Track Your Order" picks up track-your-order.png.
     */
    public const DEFAULT_TEXT = <<<'TXT'
HELP & GUIDE
1. Create Your Account
Tap Sign up at the top right, enter your name, email and password, then confirm with the 6-digit code we email you. An account keeps your orders, addresses and refunds in one place.
2. Browse and Search Products
Use the Shop link in the menu or the search bar to find dresses, shoes, bags and more. Filter by category, price or age from the shop page.
• Menu > Shop opens the full catalogue
• The search bar matches product names
• The Deals page lists everything on promo
3. Product Page, Sizes and Stock
Open a product to see photos, the price and every available variant. Pick a size or colour, check the stock badge, then press Add to cart. Greyed-out options are out of stock.
4. Your Cart and Coupon Codes
Click the bag icon to review your items and change quantities. Have a coupon? Type the code in the coupon box and press Apply - the discount shows straight away.
5. Checkout: Delivery or Pickup
Press Checkout and choose Delivery or Pickup. For delivery, select your location and type your full address; for pickup, choose a station near you. Your location decides the delivery fee.
6. Payment: Pay Now or Pay on Delivery
Pay Now completes payment instantly with Paystack and your order is confirmed right away. Pay on Delivery lets the agent collect payment when your parcel arrives - items are released only after you pay.
7. Understanding Order Status
• Pending payment - a Pay Now order waiting for payment
• Pending confirmation - our team is reviewing your order
• Confirmed and Processing - paid and being prepared
• Shipped and Out for delivery - on the way to you
• Delivered - handed over; you can now request a return
8. Track Your Order
From the menu choose Track Order, enter your order reference and email, then type the 6-digit code we send you. The tracking page shows live status and delivery details.
9. My Orders and Profile
Click your name, then My Orders for history, invoices, payment options and refund requests. My Profile stores your phone number, addresses and password.
10. Request a Return
On a delivered order press Request Return, choose the reason, add photos when asked and submit. Windows depend on the reason and are listed on the Return Policy page.
11. Place a Custom Frock Order
Menu > Custom Orders > New Order. Upload your measurements with the guide, pick fabric and style, then send it for a quote. Approve the quote to start production.
12. Explore Custom Creations
Menu > Custom Creations shows ready designs we can replicate. Open one and press Start Custom Order to begin.
Need More Help
Contact our team from the Contact page - we reply within one business day.
TXT;

    public function show(): View
    {
        $saved = Setting::get('help_guide', '');
        $text  = is_string($saved) && trim($saved) !== '' ? $saved : self::DEFAULT_TEXT;

        $doc    = PolicyFormatter::format($text);
        $photos = $this->screenshots();

        foreach ($doc['blocks'] as &$block) {
            if (($block['type'] ?? null) === 'heading') {
                $file = $this->matchScreenshot($photos, (string) $block['num'], (string) $block['title']);
                if ($file !== null) {
                    $block['image'] = $file;
                }
            }
        }
        unset($block);

        return view('shop.help.show', ['text' => $text] + $doc);
    }

    /**
     * Screenshots dropped into public/images/help, keyed by the slugified
     * filename (spaces, capitals and stray extensions all normalised):
     * "Sign up page.png" -> "sign-up-page", "show sign up button.jpg.png"
     * -> "show-sign-up-button".
     *
     * @return array<string, string>
     */
    private function screenshots(): array
    {
        $dir = public_path('images/help');

        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach ((array) glob($dir . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [] as $path) {
            $base = pathinfo($path, PATHINFO_FILENAME);
            while (str_contains($base, '.')) {
                $base = (string) pathinfo($base, PATHINFO_FILENAME);
            }

            $slug = Str::slug($base);
            if ($slug !== '') {
                $files[$slug] = basename($path);
            }
        }

        return $files;
    }

    /**
     * Number first ("1", "1-any"), then the heading slug ("track-your-order").
     *
     * @param  array<string, string>  $photos
     */
    private function matchScreenshot(array $photos, string $num, string $title): ?string
    {
        foreach ([$num, Str::slug($title)] as $candidate) {
            if ($candidate === '') {
                continue;
            }

            foreach ($photos as $base => $file) {
                // PHP casts numeric array keys to int; compare as strings.
                $base = (string) $base;

                if ($base === $candidate || str_starts_with($base, $candidate . '-')) {
                    return $file;
                }
            }
        }

        return null;
    }
}
