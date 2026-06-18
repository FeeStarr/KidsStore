<?php
use Illuminate\Support\Facades\Mail;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Gmail SMTP Configuration Test ===\n\n";

echo "Configuration Details:\n";
echo "  MAIL_MAILER: " . config('mail.mailer') . "\n";
echo "  MAIL_HOST: " . config('mail.host') . "\n";
echo "  MAIL_PORT: " . config('mail.port') . "\n";
echo "  MAIL_ENCRYPTION: " . config('mail.encryption') . "\n";
echo "  MAIL_USERNAME: " . config('mail.username') . "\n";
echo "  MAIL_FROM_ADDRESS: " . config('mail.from.address') . "\n";
echo "  MAIL_FROM_NAME: " . config('mail.from.name') . "\n\n";

echo "Testing SMTP connection...\n";
try {
    // Try to send a test email
    Mail::raw('This is a test email to verify Gmail SMTP configuration.', function ($message) {
        $message->to('nafiyoza@gmail.com')
            ->subject('KidsStore - 2FA Email Test')
            ->from(config('mail.from.address'), config('mail.from.name'));
    });
    
    echo "✓ Email sent successfully!\n";
    echo "\nYou should receive the test email in nafiyoza@gmail.com inbox within a few seconds.\n";
    
} catch (\Exception $e) {
    echo "✗ Error sending email:\n";
    echo "  " . $e->getMessage() . "\n";
    
    if ($e->getPrevious()) {
        echo "\nDetailed error:\n";
        echo "  " . $e->getPrevious()->getMessage() . "\n";
    }
}

echo "\n";
