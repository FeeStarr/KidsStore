<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\ContactPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function edit(): View
    {
        $contact  = ContactPage::instance();
        $unread   = ContactMessage::new()->count();

        return view('admin.contact.edit', compact('contact', 'unread'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hero_title'    => ['required', 'string', 'max:255'],
            'hero_subtitle' => ['required', 'string', 'max:255'],
            'intro'         => ['nullable', 'string'],
            'email'         => ['nullable', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'address'       => ['nullable', 'string', 'max:255'],
            'hours'         => ['nullable', 'string', 'max:100'],
        ]);

        ContactPage::instance()->update($data);

        return back()->with('success', 'Contact page updated successfully.');
    }

    public function messages(Request $request): View
    {
        $query = ContactMessage::query();

        $filter = $request->get('filter', 'all');
        if ($filter === 'new') {
            $query->new();
        } elseif ($filter === 'spam') {
            $query->where('status', ContactMessage::STATUS_SPAM);
        } elseif ($filter === 'archived') {
            $query->where('status', ContactMessage::STATUS_ARCHIVED);
        } elseif ($filter === 'replied') {
            $query->where('status', ContactMessage::STATUS_REPLIED);
        }

        $messages = $query->latest()->paginate(20)->withQueryString();

        return view('admin.contact.messages', compact('messages', 'filter'));
    }

    public function showMessage(ContactMessage $message): View
    {
        if ($message->status === ContactMessage::STATUS_NEW) {
            $message->markAsRead();
        }

        return view('admin.contact.message', compact('message'));
    }

    public function markAsSpam(ContactMessage $message): RedirectResponse
    {
        $message->markAsSpam();

        return back()->with('success', 'Message marked as spam.');
    }

    public function markAsReplied(ContactMessage $message): RedirectResponse
    {
        $message->markAsReplied();

        return back()->with('success', 'Message marked as replied.');
    }

    public function archive(ContactMessage $message): RedirectResponse
    {
        $message->archive();

        return back()->with('success', 'Message archived.');
    }

    public function destroyMessage(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return redirect()->route('admin.contact.messages')->with('success', 'Message deleted.');
    }
}
