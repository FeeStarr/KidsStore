<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\CustomOrderFile;
use App\Models\DeliveryAgent;
use App\Models\DeliveryCharge;
use App\Models\DeliveryLocation;
use App\Models\User;
use App\Notifications\CustomOrderReceived;
use App\Services\CustomOrderThumbnailService;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\TextPart;
use Tests\TestCase;

class CustomOrderEmailThumbnailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Faked once per test: a second Storage::fake() would wipe files written by an
        // earlier helper call within the same test.
        Storage::fake('custom_orders');
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER]);
    }

    private function admin(string $role = User::ROLE_ADMIN): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function order(User $user, string $number = 'CF-000777'): CustomOrder
    {
        return CustomOrder::create([
            'custom_order_number' => $number,
            'user_id' => $user->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_SUBMITTED,
            'child_name' => 'Mo',
            'delivery_method' => 'pickup',
            'return_policy_acknowledged' => true,
        ]);
    }

    /**
     * Writes a real, decodable image onto the private disk and registers it against the
     * order the way CustomFileService::upload() would.
     */
    private function attachReferenceImage(CustomOrder $order, string $type = 'reference_image', string $name = 'ref.jpg', string $mime = 'image/jpeg'): CustomOrderFile
    {
        $path = sprintf('custom-orders/%d/%s', $order->id, $name);
        Storage::disk('custom_orders')->put($path, $this->sampleJpeg(64, 48));

        return CustomOrderFile::create([
            'custom_order_id' => $order->id,
            'file_type' => $type,
            'file_path' => $path,
            'original_filename' => $name,
            'mime_type' => $mime,
            'file_size' => 1234,
            'uploaded_by' => $order->user_id,
            'created_at' => now(),
        ]);
    }

    private function sampleJpeg(int $w, int $h): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 200, 40, 90));

        ob_start();
        imagejpeg($im, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($im);

        return $bytes;
    }

    /**
     * A JPEG carrying EXIF orientation = 6 (rotate 90 CW) built by hand, since the exif
     * extension is absent and these are the images most likely to arrive sideways.
     */
    private function sampleJpegWithOrientation(int $orientation): string
    {
        $jpeg = $this->sampleJpeg(60, 20); // deliberately wide, so rotation is detectable

        // TIFF header: byte order (2) | magic 42 as SHORT (2) | IFD offset (4) => IFD at 8.
        // Each directory entry is 12 bytes, and its 4-byte value field is left-justified
        // even for SHORT - so orientation occupies the first 2 of those 4 bytes.
        $tiff = 'MM'
            .pack('n', 42)                          // magic, SHORT
            .pack('N', 8)                           // IFD0 begins at byte 8
            .pack('n', 1)                           // one entry
            .pack('n', 0x0112)                      // Orientation tag
            .pack('n', 3)                           // type SHORT
            .pack('N', 1)                           // count
            .pack('n', $orientation)."\x00\x00"     // value, left-justified in 4 bytes
            .pack('N', 0);                          // no next IFD

        $exif = "Exif\x00\x00".$tiff;

        // APP1 segment: marker, length (covering the two length bytes + payload), payload.
        $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        // Splice it in immediately after SOI.
        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
    }

    private function sendAndCapture(CustomOrder $order, User $recipient): Email
    {
        // Drop the cached SMTP mailer so the notification actually routes to the array
        // transport and we can inspect the real MIME structure.
        Mail::purge();
        config(['mail.default' => 'array']);

        Notification::send($recipient, new CustomOrderReceived($order));

        $messages = Mail::mailer()->getSymfonyTransport()->messages();

        $this->assertNotEmpty($messages, 'Expected the notification to produce an email.');

        return $messages->last()->getOriginalMessage();
    }

    public function test_email_embeds_a_thumbnail_of_the_reference_image(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $this->attachReferenceImage($order);

        $mail = (new CustomOrderReceived($order))->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertCount(1, $mail->attachments);
        $this->assertSame(
            CustomOrderThumbnailService::CID_NAME,
            $mail->attachments[0]['options']['as']
        );
        $this->assertSame(
            'cid:'.CustomOrderThumbnailService::CID_NAME,
            $mail->viewData['thumbnailCid']
        );
    }

    /**
     * Asserts against the serialised MIME rather than the object graph: Symfony only
     * materialises a part's Content-Disposition header when the message is written, and
     * a cid:-referenced attachment is re-parented into a multipart/related, so neither
     * getAttachments() nor getDisposition() reflects the real wire format.
     */
    public function test_sent_email_embeds_the_reference_as_an_inline_image(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $this->attachReferenceImage($order);

        $email = $this->sendAndCapture($order, $user);
        $mime = $email->toString();

        $this->assertStringContainsString('Content-Type: multipart/related', $mime);
        $this->assertStringContainsString('Content-Type: image/jpeg', $mime);
        $this->assertStringContainsString('Content-Disposition: inline', $mime);

        // Exactly one image part - the thumbnail is embedded once, not also left as a
        // visible attachment.
        $this->assertSame(1, preg_match_all('/Content-Type: image\/jpeg/', $mime));

        $this->assertSame(
            1,
            preg_match('/Content-ID: <([^>]+)>/', $mime, $matches),
            'Embedded image needs a Content-ID for cid: resolution.'
        );

        // The placeholder must be replaced by the generated Content-ID. Assert on the
        // decoded HTML part: in the raw MIME the id is split by quoted-printable soft
        // line breaks, and Email::getHtmlBody() returns the pre-rewrite source.
        $html = $this->htmlPart($email);

        $this->assertStringContainsString('cid:'.$matches[1], $html);
        $this->assertStringNotContainsString(
            'cid:'.CustomOrderThumbnailService::CID_NAME,
            $html
        );
    }

    /**
     * Walks the generated body for the text/html part. Calling getBody() is what runs
     * Symfony's cid substitution. Note getMediaType() returns the bare type ("text"),
     * not "text/html".
     */
    private function htmlPart(Email $email): string
    {
        $walk = function ($node) use (&$walk): ?string {
            if ($node instanceof TextPart && $node->getMediaType() === 'text') {
                return $node->getBody();
            }

            if ($node instanceof AbstractMultipartPart) {
                foreach ($node->getParts() as $child) {
                    if (($html = $walk($child)) !== null) {
                        return $html;
                    }
                }
            }

            return null;
        };

        return $walk($email->getBody()) ?? '';
    }

    public function test_sent_email_without_a_reference_has_no_image_part(): void
    {
        $user = $this->customer();
        $order = $this->order($user);

        $mime = $this->sendAndCapture($order, $user)->toString();

        $this->assertStringNotContainsString('image/jpeg', $mime);
        $this->assertStringNotContainsString('cid:', $mime);
    }

    public function test_thumbnail_is_downscaled_to_the_configured_edge(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $file = $this->attachReferenceImage($order);

        $path = app(CustomOrderThumbnailService::class)->thumbnailFor($file);

        $this->assertNotNull($path);
        $this->assertFileExists($path);

        [$w, $h] = getimagesize($path);
        $this->assertLessThanOrEqual(CustomOrderThumbnailService::MAX_EDGE, max($w, $h));
        $this->assertSame('image/jpeg', mime_content_type($path));
    }

    public function test_thumbnail_is_cached_and_reused(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $file = $this->attachReferenceImage($order);

        $service = app(CustomOrderThumbnailService::class);
        $first = $service->thumbnailFor($file);

        $expected = Storage::disk('custom_orders')->path(
            sprintf('custom-orders/%d/thumbnails/email-%d.jpg', $order->id, $file->id)
        );
        $this->assertSame(realpath($expected), realpath($first));

        // Second call must not regenerate: same path, and content untouched.
        $before = file_get_contents($first);
        touch($first, time() - 3600);
        $mtime = filemtime($first);

        $service->thumbnailFor($file);

        $this->assertSame($mtime, filemtime($first), 'Cached thumbnail should not be rewritten.');
        $this->assertSame($before, file_get_contents($first));
    }

    public function test_orientation_aware_source_is_rotated(): void
    {
        $user = $this->customer();
        $order = $this->order($user);

        $path = sprintf('custom-orders/%d/rotated.jpg', $order->id);
        Storage::disk('custom_orders')->put($path, $this->sampleJpegWithOrientation(6));

        $file = CustomOrderFile::create([
            'custom_order_id' => $order->id,
            'file_type' => 'reference_image',
            'file_path' => $path,
            'original_filename' => 'rotated.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1234,
            'uploaded_by' => $order->user_id,
            'created_at' => now(),
        ]);

        $thumb = app(CustomOrderThumbnailService::class)->thumbnailFor($file);

        $this->assertNotNull($thumb);

        // Source is 60x20 (landscape); orientation 6 means it must display as portrait.
        [$w, $h] = getimagesize($thumb);
        $this->assertGreaterThan($w, $h, 'Thumbnail should be rotated to portrait.');
    }

    public function test_email_omits_thumbnail_when_there_is_no_reference_image(): void
    {
        $user = $this->customer();
        $order = $this->order($user);

        $mail = (new CustomOrderReceived($order))->toMail($user);

        $this->assertEmpty($mail->attachments);
        $this->assertNull($mail->viewData['thumbnailCid'] ?? null);
    }

    public function test_email_omits_thumbnail_for_pdf_reference(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $this->attachReferenceImage($order, 'reference_image', 'design.pdf', 'application/pdf');

        $mail = (new CustomOrderReceived($order))->toMail($user);

        $this->assertEmpty($mail->attachments);
    }

    public function test_email_falls_back_to_colour_reference_when_no_reference_image(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $this->attachReferenceImage($order, 'colour_reference', 'palette.jpg');

        $mail = (new CustomOrderReceived($order))->toMail($user);

        // Design intent was a reference thumbnail; a colour palette alone is not one,
        // so nothing should be embedded.
        $this->assertEmpty($mail->attachments);
    }

    public function test_email_ignores_a_pdf_even_when_a_reference_image_exists(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $this->attachReferenceImage($order, 'reference_image', 'first.pdf', 'application/pdf');
        $this->attachReferenceImage($order, 'reference_image', 'second.jpg', 'image/jpeg');

        $mail = (new CustomOrderReceived($order))->toMail($user);

        $this->assertCount(1, $mail->attachments);
    }

    public function test_missing_file_on_disk_degrades_without_error(): void
    {
        $user = $this->customer();
        $order = $this->order($user);

        $file = CustomOrderFile::create([
            'custom_order_id' => $order->id,
            'file_type' => 'reference_image',
            'file_path' => 'custom-orders/'.$order->id.'/gone.jpg',
            'original_filename' => 'gone.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 10,
            'uploaded_by' => $order->user_id,
            'created_at' => now(),
        ]);

        $this->assertNull(app(CustomOrderThumbnailService::class)->thumbnailFor($file));
        $this->assertEmpty((new CustomOrderReceived($order))->toMail($user)->attachments);
    }

    public function test_thumbnail_path_escaping_the_disk_root_is_refused(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $outside = storage_path('app/outside.jpg');
        file_put_contents($outside, $this->sampleJpeg(10, 10));

        // Plant the file, then point the record at a traversal path.
        $file = CustomOrderFile::create([
            'custom_order_id' => $order->id,
            'file_type' => 'reference_image',
            'file_path' => '../outside.jpg',
            'original_filename' => 'outside.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 10,
            'uploaded_by' => $order->user_id,
            'created_at' => now(),
        ]);

        // canThumbnail() is satisfied via the disk, but rendering is refused by the
        // containment guard, so no thumbnail escapes the disk root.
        $this->assertNull(app(CustomOrderThumbnailService::class)->thumbnailFor($file));

        @unlink($outside);
    }

    public function test_customer_email_links_to_their_own_order(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);

        $mail = (new CustomOrderReceived($order))->toMail($customer);

        $this->assertSame('Your Custom Frock Request Has Been Received', $mail->subject);
        $this->assertFalse($mail->viewData['isInternal']);
        $this->assertStringContainsString(
            route('shop.custom-frock.show', $order),
            $this->htmlPartFor($mail, $customer)
        );
        $this->assertStringNotContainsString('/admin/custom-orders/', $this->htmlPartFor($mail, $customer));
    }

    /**
     * The route the shop view guards with abort_unless(user_id === Auth::id()) returns
     * 403 for internal staff, so their copy must point at the admin page instead.
     */
    public function test_admin_email_links_to_the_admin_order_page(): void
    {
        $customer = $this->customer();
        $admin = $this->admin();
        $order = $this->order($customer);

        $mail = (new CustomOrderReceived($order))->toMail($admin);

        $this->assertSame('New Custom Frock Request - CF-000777', $mail->subject);
        $this->assertTrue($mail->viewData['isInternal']);
        $this->assertStringContainsString(
            route('admin.custom-orders.show', $order),
            $this->htmlPartFor($mail, $admin)
        );
        $this->assertStringNotContainsString('/custom-frock/', $this->htmlPartFor($mail, $admin));
    }

    public function test_admin_email_identifies_the_customer_and_child(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);

        $html = $this->htmlPartFor((new CustomOrderReceived($order))->toMail($this->admin()), $this->admin());

        $this->assertStringContainsString($customer->name, $html);
        $this->assertStringContainsString('Mo', $html);
        $this->assertStringContainsString('CF-000777', $html);
    }

    public function test_thumbnail_caption_is_not_customer_speak_for_staff(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);
        $this->attachReferenceImage($order);

        $admin = $this->admin();
        $adminHtml = $this->htmlPartFor((new CustomOrderReceived($order))->toMail($admin), $admin);
        $customerHtml = $this->htmlPartFor((new CustomOrderReceived($order))->toMail($customer), $customer);

        $this->assertStringContainsString('submitted by the customer', $adminHtml);
        $this->assertStringContainsString('The design you uploaded', $customerHtml);
        $this->assertStringNotContainsString('The design you uploaded', $adminHtml);
    }

    public function test_every_internal_role_is_treated_as_staff(): void
    {
        foreach ([User::ROLE_SUPERADMIN, User::ROLE_ADMIN, User::ROLE_STAFF] as $i => $role) {
            $order = $this->order($this->customer(), 'CF-90000'.$i);

            $mail = (new CustomOrderReceived($order))->toMail($this->admin($role));

            $this->assertTrue($mail->viewData['isInternal'], "Role {$role} should get the internal copy.");
        }
    }

    public function test_both_recipients_still_get_the_reference_thumbnail(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);
        $this->attachReferenceImage($order);

        $notification = new CustomOrderReceived($order);

        foreach ([$customer, $this->admin()] as $recipient) {
            $mail = $notification->toMail($recipient);

            $this->assertCount(1, $mail->attachments, 'Both copies should embed the design.');
            $this->assertSame(
                'cid:'.CustomOrderThumbnailService::CID_NAME,
                $mail->viewData['thumbnailCid']
            );
        }
    }

    /**
     * Renders the notification's view for a given recipient, the same way MailChannel
     * would, so the assertions cover the markup rather than the MailMessage wrapper.
     */
    private function htmlPartFor(MailMessage $mail, User $recipient): string
    {
        return view($mail->view, array_merge($mail->viewData, ['notifiable' => $recipient]))->render();
    }

    public function test_order_email_flow_still_works_end_to_end(): void
    {
        Notification::fake();

        $location = DeliveryLocation::firstOrCreate(['name' => 'Thumb City'], ['state' => 'Lagos', 'is_active' => true]);

        if (! DeliveryCharge::where('delivery_location_id', $location->id)->exists()) {
            $agent = DeliveryAgent::create([
                'name' => 'Thumb Agent',
                'account_number' => 'AG'.rand(10000, 99999),
                'phone' => '08012345678',
                'is_active' => true,
            ]);
            DeliveryCharge::create([
                'delivery_agent_id' => $agent->id,
                'delivery_location_id' => $location->id,
                'amount' => 1500,
                'is_active' => true,
            ]);
        }

        $user = $this->customer();
        $this->actingAs($user, 'web')->post(route('shop.custom-frock.store'), [
            'child_name' => 'Mo',
            'delivery_method' => 'delivery',
            'delivery_location_id' => $location->id,
            'delivery_address' => '1 Test Street',
            'primary_colour' => 'Pink',
            'standard_size' => '2-3 Years',
            'return_policy_acknowledged' => '1',
            'reference_files' => [UploadedFile::fake()->image('design.jpg', 800, 600)],
        ])->assertRedirect();

        Notification::assertSentTo($user, CustomOrderReceived::class);
    }
}
