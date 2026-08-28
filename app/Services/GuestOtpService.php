<?php

namespace App\Services;

use App\Models\GuestOtp;
use App\Notifications\GuestOtpNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GuestOtpService
{
    private int $maxAttempts = 5;
    private int $throttleSeconds = 60;
    private int $otpLength = 6;
    private int $expiryMinutes = 10;

    /**
     * Generate and send a 6-digit OTP to the given email.
     */
    public function sendOtp(string $email): bool
    {
        $throttleKey = 'guest_otp_throttle:' . strtolower($email);

        if (Cache::has($throttleKey)) {
            return false;
        }

        // Invalidate any previous unverified OTPs for this email
        GuestOtp::where('email', $email)
            ->where('verified', false)
            ->update(['expires_at' => now()]);

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = GuestOtp::create([
            'email'      => $email,
            'code'       => $code,
            'expires_at' => now()->addMinutes($this->expiryMinutes),
        ]);

        Cache::put($throttleKey, true, $this->throttleSeconds);

        // Dispatch notification (email)
        try {
            \Illuminate\Support\Facades\Notification::route('mail', $email)
                ->notify(new GuestOtpNotification($code, $this->expiryMinutes));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Guest OTP email failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Verify the OTP code for the given email.
     */
    public function verify(string $email, string $code): bool
    {
        $otp = GuestOtp::validForEmail($email)
            ->latest()
            ->first();

        if (! $otp) {
            return false;
        }

        if ($otp->attempts >= $this->maxAttempts) {
            return false;
        }

        if ($otp->code !== $code) {
            $otp->increment('attempts');
            return false;
        }

        $otp->update(['verified' => true]);
        return true;
    }

    /**
     * Check if an email has a verified OTP session.
     */
    public function isVerified(string $email): bool
    {
        return GuestOtp::where('email', $email)
            ->where('verified', true)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Clean up expired OTPs.
     */
    public function cleanup(): int
    {
        return GuestOtp::where('expires_at', '<', now())->delete();
    }
}
