@component('emails.layouts.newsletter', [
    'locale' => $locale,
    'preheader' => $event['title'],
    'unsubscribeUrl' => $unsubscribe_url,
    'preferencesUrl' => $preferences_url,
])

{{-- Alert banner --}}
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 20px 0; background-color: #C0392B; border: 1px solid #E74C3C;">
    <tr>
        <td style="padding: 10px 16px;">
            <span style="font-family: 'Courier New', monospace; font-size: 11px; letter-spacing: 2.5px; color: #D4E8CF; text-transform: uppercase; font-weight: bold;">
                ▲ @lang('newsletter.critical.heading') · SEV {{ $event['severity'] }}
            </span>
        </td>
    </tr>
</table>

{{-- Thread context --}}
<p style="margin: 0 0 6px 0; font-family: 'Courier New', monospace; font-size: 10px; letter-spacing: 1.5px; color: #4A6344; text-transform: uppercase;">
    @lang('newsletter.daily.thread')
</p>
<h2 style="margin: 0 0 18px 0; font-family: 'Bebas Neue', 'Arial Narrow', Arial, sans-serif; font-size: 24px; letter-spacing: 1px; color: #6FD65A; font-weight: normal;">
    <a href="{{ $thread_url }}" style="color: #6FD65A; text-decoration: none;">{{ $thread_name }}</a>
</h2>

{{-- Event title --}}
<h1 style="margin: 0 0 12px 0; font-family: 'Bebas Neue', 'Arial Narrow', Arial, sans-serif; font-size: 26px; line-height: 1.2; letter-spacing: 0.5px; color: #D4E8CF; font-weight: normal;">
    {{ $event['title'] }}
</h1>

{{-- Meta row --}}
<p style="margin: 0 0 18px 0; font-family: 'Courier New', monospace; font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase;">
    <span style="color: #E74C3C; font-weight: bold;">SEV {{ $event['severity'] }}</span>
    <span style="color: #4A6344;"> · </span>
    <span style="color: #8AAD83;">CONF {{ $event['confidence'] }}</span>
    <span style="color: #4A6344;"> · </span>
    <span style="color: #52A844;">{{ strtoupper($event['status']) }}</span>
    @if(!empty($event['country']))
        <span style="color: #4A6344;"> · </span>
        <span style="color: #8AAD83;">{{ strtoupper($event['country']) }}</span>
    @endif
    @if(!empty($event['category']))
        <span style="color: #4A6344;"> · </span>
        <span style="color: #8AAD83;">{{ strtoupper(str_replace('_', ' ', $event['category'])) }}</span>
    @endif
</p>

{{-- Event summary --}}
@if(!empty($event['summary']))
    <p style="margin: 0 0 20px 0; font-size: 15px; line-height: 1.6; color: #D4E8CF;">
        {{ $event['summary'] }}
    </p>
@endif

{{-- Actions --}}
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 0 20px 0;">
    <tr>
        <td style="background-color: #3D7A32; border: 1px solid #52A844;">
            <a href="{{ $event_url }}" style="display: inline-block; padding: 10px 22px; font-family: 'Courier New', monospace; font-size: 12px; letter-spacing: 2px; color: #D4E8CF; text-transform: uppercase; text-decoration: none; font-weight: bold;">
                @lang('newsletter.daily.viewDetails')
            </a>
        </td>
        @if(!empty($event['source_url']))
            <td style="width: 12px;">&nbsp;</td>
            <td style="border: 1px solid #243320;">
                <a href="{{ $event['source_url'] }}" style="display: inline-block; padding: 10px 22px; font-family: 'Courier New', monospace; font-size: 12px; letter-spacing: 2px; color: #8AAD83; text-transform: uppercase; text-decoration: none;">
                    @lang('newsletter.daily.source')
                </a>
            </td>
        @endif
    </tr>
</table>

<p style="margin: 0; font-family: 'Courier New', monospace; font-size: 10px; letter-spacing: 1px; color: #4A6344;">
    @lang('newsletter.critical.why')
</p>

@endcomponent
