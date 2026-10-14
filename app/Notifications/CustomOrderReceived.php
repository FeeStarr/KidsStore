<?php

namespace App\Notifications;

use App\Models\CustomOrder;
use App\Services\CustomOrderThumbnailService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomOrderReceived extends Notification
{
    use Queueable;

    public function __construct(
        public CustomOrder $customOrder,
        private CustomOrderThumbnailService $thumbnails = new CustomOrderThumbnailService,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->customOrder->loadMissing(['files', 'user']);

        // This notification goes to the customer AND to internal staff. They need
        // different wording and, critically, different links: the customer-facing route
        // aborts with 403 for anyone who does not own the order.
        $isInternal = $this->isInternal($notifiable);

        $message = (new MailMessage)
            ->subject($isInternal
? 'New Custom Request - '.$order->custom_order_number
            : 'Your Custom Request Has Been Received');

        // The reference image lives on a private disk, so it has to travel with the
        // mail. Symfony promotes any cid:-referenced attachment to an inline part.
        $thumbnail = $this->referenceThumbnailPath($order);

        $message->view('emails.custom-order-received', [
            'customOrder' => $order,
            'notifiable' => $notifiable,
            'isInternal' => $isInternal,
            'thumbnailCid' => $thumbnail ? 'cid:'.CustomOrderThumbnailService::CID_NAME : null,
        ]);

        if ($thumbnail) {
            $message->attach($thumbnail, [
                'as' => CustomOrderThumbnailService::CID_NAME,
                'mime' => 'image/jpeg',
            ]);
        }

        return $message;
    }

    private function isInternal(object $notifiable): bool
    {
        return method_exists($notifiable, 'hasAnyRole')
            && $notifiable->hasAnyRole(['superadmin', 'admin', 'staff']);
    }

    /**
     * Absolute path to a preview of the customer's reference design, or null when the
     * order has no usable image (PDF-only uploads, missing files, GD failures). The
     * email then simply renders without a thumbnail.
     */
    private function referenceThumbnailPath(CustomOrder $order): ?string
    {
        // Skip past anything un-previewable (e.g. a PDF first in the list) rather than
        // giving up on the whole order.
        foreach ($order->files as $file) {
            if ($file->file_type !== 'reference_image') {
                continue;
            }

            $thumbnail = $this->thumbnails->thumbnailFor($file);

            if ($thumbnail !== null) {
                return $thumbnail;
            }
        }

        return null;
    }
}
