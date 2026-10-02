<x-mail::message>
# {{ $notice->title }}

{{ $notice->body }}

@if ($notice->url !== null)
<x-mail::button :url="url($notice->url)">
{{ $notice->actionLabel ?? 'Open Clash Commons' }}
</x-mail::button>
@endif

<x-mail::subcopy>
[Unsubscribe from non-security emails]({{ $unsubscribeUrl }}).
You can also [change your email preferences]({{ route('settings.notifications.edit') }}).
Security emails stay on.
</x-mail::subcopy>

Clash Commons
</x-mail::message>
