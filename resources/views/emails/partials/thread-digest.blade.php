{{-- Thread digest section: name, summary, top N events --}}
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 28px 0; background-color: #111510; border: 1px solid #243320;">
    <tr>
        <td style="padding: 18px 20px 4px 20px;">
            <div style="font-family: 'Courier New', monospace; font-size: 10px; letter-spacing: 1.5px; color: #4A6344; text-transform: uppercase; margin-bottom: 6px;">
                @lang('newsletter.daily.thread') · {{ $digest['event_count_24h'] ?? 0 }} @lang('newsletter.daily.eventsIn24h')
            </div>
            <h3 style="margin: 0 0 8px 0; font-family: 'Bebas Neue', 'Arial Narrow', Arial, sans-serif; font-size: 22px; letter-spacing: 1px; color: #6FD65A; font-weight: normal;">
                <a href="{{ $digest['url'] }}" style="color: #6FD65A; text-decoration: none;">{{ $digest['name'] }}</a>
            </h3>
            @if(!empty($digest['summary']))
                <p style="margin: 0 0 14px 0; font-size: 13px; line-height: 1.55; color: #8AAD83;">
                    {{ \Illuminate\Support\Str::limit($digest['summary'], 200) }}
                </p>
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding: 0 20px 18px 20px;">
            @foreach($digest['events'] as $event)
                @include('emails.partials.event-card', ['event' => $event])
            @endforeach
        </td>
    </tr>
</table>
