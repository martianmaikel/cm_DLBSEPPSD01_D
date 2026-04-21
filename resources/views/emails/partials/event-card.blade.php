@php
    $sev = (int) ($event['severity'] ?? 0);
    $conf = (int) ($event['confidence'] ?? 0);
    $sevColor = $sev >= 7 ? '#E74C3C' : ($sev >= 4 ? '#C97B1A' : '#52A844');
    $statusColor = match($event['status'] ?? '') {
        'confirmed'    => '#52A844',
        'corroborated' => '#C97B1A',
        'disputed'     => '#E74C3C',
        default        => '#8AAD83',
    };
@endphp
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 16px 0; background-color: #181D16; border: 1px solid #243320; border-left: 3px solid {{ $sevColor }};">
    <tr>
        <td style="padding: 14px 16px;">
            <!-- Meta row: severity + status + country + category -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 8px;">
                <tr>
                    <td style="font-family: 'Courier New', monospace; font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase;">
                        <span style="color: {{ $sevColor }}; font-weight: bold;">SEV {{ $sev }}</span>
                        <span style="color: #4A6344;"> · </span>
                        <span style="color: #8AAD83;">CONF {{ $conf }}</span>
                        <span style="color: #4A6344;"> · </span>
                        <span style="color: {{ $statusColor }};">{{ strtoupper($event['status'] ?? '—') }}</span>
                        @if(!empty($event['country']))
                            <span style="color: #4A6344;"> · </span>
                            <span style="color: #8AAD83;">{{ strtoupper($event['country']) }}</span>
                        @endif
                        @if(!empty($event['category']))
                            <span style="color: #4A6344;"> · </span>
                            <span style="color: #8AAD83;">{{ strtoupper(str_replace('_', ' ', $event['category'])) }}</span>
                        @endif
                    </td>
                </tr>
            </table>

            <!-- Title -->
            <p style="margin: 0 0 8px 0; font-size: 15px; line-height: 1.4; color: #D4E8CF; font-weight: 600;">
                {{ $event['title'] }}
            </p>

            <!-- Summary -->
            @if(!empty($event['summary']))
                <p style="margin: 0 0 10px 0; font-size: 13px; line-height: 1.5; color: #8AAD83;">
                    {{ \Illuminate\Support\Str::limit($event['summary'], 220) }}
                </p>
            @endif

            <!-- Actions -->
            <p style="margin: 0; font-family: 'Courier New', monospace; font-size: 11px; letter-spacing: 1px;">
                <a href="{{ $event['event_url'] }}" style="color: #52A844; text-decoration: underline;">
                    @lang('newsletter.daily.viewDetails')
                </a>
                @if(!empty($event['source_url']))
                    <span style="color: #4A6344;"> · </span>
                    <a href="{{ $event['source_url'] }}" style="color: #52A844; text-decoration: underline;">
                        @lang('newsletter.daily.source')
                    </a>
                @endif
            </p>
        </td>
    </tr>
</table>
