<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\ContactPage;
use App\Notifications\ContactMessageNotification;
use App\Notifications\NotificationRecipients;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function show(): View
    {
        $contact = ContactPage::instance();

        return view('shop.contact', compact('contact'));
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255'],
            'subject_type'  => ['required', 'string', 'in:Request,Enquiry,Complaint,Feedback,Other'],
            'subject_other' => ['required_if:subject_type,Other', 'nullable', 'string', 'max:255'],
            'message'       => ['required', 'string', 'max:5000'],
        ]);

        $subject = $data['subject_type'] === 'Other'
            ? $data['subject_other']
            : $data['subject_type'];

        $contactMessage = ContactMessage::create([
            'name'    => $data['name'],
            'email'   => $data['email'],
            'subject' => $subject,
            'message' => $data['message'],
        ]);

        // Notify admin users and support staff
        try {
            foreach (NotificationRecipients::adminUsers() as $admin) {
                $admin->notify(new ContactMessageNotification($contactMessage));
            }
            foreach (NotificationRecipients::customerSupportStaff() as $staff) {
                $staff->notify(new ContactMessageNotification($contactMessage));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Contact message notification failed', ['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Thanks for reaching out! We\'ll be in touch soon.');
    }
}
