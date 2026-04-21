@component('emails.layouts.newsletter', [
    'locale' => $locale,
    'preheader' => 'Test delivery from ClashMonitor',
    'unsubscribeUrl' => $unsubscribeUrl,
])

<h1 style="margin: 0 0 16px 0; font-family: 'Bebas Neue', 'Arial Narrow', Arial, sans-serif; font-size: 28px; letter-spacing: 1.5px; color: #6FD65A; text-transform: uppercase; font-weight: normal;">
    @lang('newsletter.test.heading')
</h1>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 1.6; color: #D4E8CF;">
    @lang('newsletter.test.body')
</p>

<div style="margin: 24px 0; padding: 16px; background-color: #181D16; border-left: 3px solid #3D7A32;">
    <p style="margin: 0 0 8px 0; font-family: 'Courier New', monospace; font-size: 11px; letter-spacing: 1.5px; color: #4A6344; text-transform: uppercase;">
        Subscriber Info
    </p>
    <p style="margin: 0; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.6; color: #8AAD83;">
        Email: {{ $email }}<br>
        Timezone: {{ $timezone }}<br>
        Locale: {{ $locale }}<br>
        Sent at: {{ $sentAt }}
    </p>
</div>

@endcomponent
