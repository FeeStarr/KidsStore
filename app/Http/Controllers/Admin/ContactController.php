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
        $unread   = ContactMessage::where('read', false)->count();

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
        $messages = ContactMessage::latest()->paginate(20);

        return view('admin.contact.messages', compact('messages'));
    }

    public function showMessage(ContactMessage $message): View
    {
        if (! $message->read) {
            $message->update(['read' => true, 'read_at' => now()]);
        }

        return view('admin.contact.message', compact('message'));
    }

    public function destroyMessage(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return redirect()->route('admin.contact.messages')->with('success', 'Message deleted.');
    }
}
