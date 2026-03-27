@component('mail::message')
# Hello {{ $user->name ?? 'Guest' }},

Your RSVP for **{{ $event->title }}** has been cancelled as requested.

**Event Details:**
* **Date:** {{ $event->starts_at->format('F j, Y') }}
* **Time:** {{ $event->starts_at->format('g:i A') }}
@if($event->venue)
* **Venue:** {{ $event->venue }}
@endif

@if($reason)
**Cancellation Reason:**
{{ $reason }}
@endif

If you would like to browse other upcoming events, please visit our events page.

@component('mail::button', ['url' => config('app.frontend_url') . '/events'])
Browse Events
@endcomponent

Regards,<br>
{{ config('app.name') }}
@endcomponent
