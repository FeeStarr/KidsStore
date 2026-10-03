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
     * "Sign Up" picks up sign-up.png / sign-up-page.png / sign up button.png
     * (several screenshots per section are allowed).
     */
    public const DEFAULT_TEXT = <<<'TXT'
HELP & GUIDE
1. Sign Up
Tap Sign up at the top right of any page, enter your name, email and password, then create your profile. A free account keeps your orders, addresses and returns in one place.
• The Sign up button sits beside Login in the menu
• Use an email you can open right away - we send a code to it
• Signing up takes less than a minute
2. Email Verification
After signing up we email you a 6-digit verification code. Enter it on the verification screen to activate your account. The code expires quickly - press Resend if you need a new one.
3. Login
Tap Login, enter your email and password, and you are back in. Two-factor accounts also enter a 6-digit code, and the same screen lets you reset a forgotten password.
4. Profile Page
Click your name, then My Profile, to update your phone number, change your password and manage delivery addresses. Set a default address so checkout fills it in automatically.
5. Shop Page
Open the Shop from the menu to browse everything. Filter by category, pick an age range, sort by price or use the search bar to find a specific item.
• Categories narrow the list to dresses, shoes, bags and more
• Age range shows sizes that fit your child
• The search bar matches product names instantly
• Press Add to cart on a product page to keep an item for checkout
6. Cart Page
Click the bag icon to review what you picked. Change quantities, remove items and watch the subtotal update. Have a coupon? Type the code in the coupon box and press Apply - the discount shows straight away.
7. Checkout
Press Checkout and choose Delivery or Pickup. For delivery, select your location and type your full address; for pickup, choose a station near you. Your location decides the delivery fee, then pick Pay Now or Pay on Delivery.
8. My Orders Page
Click your name, then My Orders, for the full history of your orders. Open an order to see its items, status, payment options and the Request Return button once it is delivered.
9. Custom Frock Pages
Menu > Custom Orders shows every custom request you have made. Start a new order, upload your measurements with the guide, pick fabric and style, then approve the quote to begin production. Follow each order on its status timeline.
10. Contact Page
Menu > Contact sends a message straight to our team - we reply within one business day. Include your order reference if your question is about an existing order.
11. Track Order Page
From the menu choose Track Order, enter your order reference and email, then type the 6-digit code we send you. The tracking page shows live status and delivery details without needing an account.
Need More Help
This guide covers everyday shopping with us. If something is still unclear, contact our team from the Contact page.
TXT;

    public function show(): View
    {
        $saved = Setting::get('help_guide', '');
        $text  = is_string($saved) && trim($saved) !== '' ? $saved : self::DEFAULT_TEXT;

        $doc    = PolicyFormatter::format($text);
        $photos = $this->screenshots();

        foreach ($doc['blocks'] as &$block) {
            if (($block['type'] ?? null) === 'heading') {
                $images = $this->matchScreenshots($photos, (string) $block['num'], (string) $block['title']);
                if ($images !== []) {
                    $block['images'] = $images;
                }
            }
        }
        unset($block);

        return view('shop.help.show', ['text' => $text] + $doc);
    }

    /**
     * Screenshots dropped into public/images/help, keyed by the slugified
     * filename (spaces, capitals and stray extensions all normalised):
     * "Sign up page.png" -> "sign-up-page", "sign up button.png"
     * -> "sign-up-button".
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
     * Number first ("1", "1-anything"), then the heading slug. A section may
     * claim several screenshots (e.g. "Sign Up" owns both sign-up-page.png
     * and sign up button.png); matches come back in filename order.
     *
     * @param  array<string, string>  $photos
     * @return array<int, string>
     */
    private function matchScreenshots(array $photos, string $num, string $title): array
    {
        foreach (array_values(array_filter([$num, Str::slug($title)])) as $candidate) {
            $found = [];

            foreach ($photos as $base => $file) {
                // PHP casts numeric array keys to int; compare as strings.
                if ($this->screenshotMatches((string) $base, $candidate)) {
                    $found[(string) $base] = $file;
                }
            }

            if ($found !== []) {
                ksort($found);

                return array_values($found);
            }
        }

        return [];
    }

    /**
     * "Sign Up" claims sign-up.png, sign-up-page.png and "sign up button.png";
     * "Checkout" claims "Check out page.png" too. Several per section, in
     * filename order.
     */
    private function screenshotMatches(string $base, string $candidate): bool
    {
        if (
            $base === $candidate
            || str_starts_with($base, $candidate . '-')
            || str_starts_with($candidate, $base . '-')
        ) {
            return true;
        }

        // Ignore hyphen placement: "check-out-page" vs "checkout".
        $flatBase     = str_replace('-', '', $base);
        $flatCandidate = str_replace('-', '', $candidate);

        if ($flatBase === $flatCandidate) {
            return true;
        }

        return strlen($flatCandidate) >= 4
            && (str_starts_with($flatBase, $flatCandidate) || str_starts_with($flatCandidate, $flatBase));
    }
}
