<x-mail::message>
# New Contact Message

**From:** {{ $message->name }} ({{ $message->email }})
**Subject:** {{ $message->subject }}
**Date:** {{ $message->created_at->format('M d, Y h:i A') }}

---

{{ $message->message }}

<x-mail::button :url="url('/admin/contact/messages/' . $message->id)">
View Message
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
