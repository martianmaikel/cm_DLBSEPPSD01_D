@component('emails.layouts.newsletter', [
    'locale' => $locale,
    'preheader' => $preheader,
    'unsubscribeUrl' => $unsubscribe_url,
    'preferencesUrl' => $preferences_url,
])

<h1 style="margin: 0 0 8px 0; font-family: 'Bebas Neue', 'Arial Narrow', Arial, sans-serif; font-size: 32px; line-height: 1.1; letter-spacing: 1.5px; color: #6FD65A; text-transform: uppercase; font-weight: normal;">
    @lang('newsletter.daily.heading')
</h1>

@if($include_global && !empty($briefing['date']))
    <p style="margin: 0 0 24px 0; font-family: 'Courier New', monospace; font-size: 11px; letter-spacing: 1.5px; color: #4A6344; text-transform: uppercase;">
        {{ $briefing['date'] }}
    </p>
@endif

@if($include_global)
    {{-- Briefing summary section --}}
    @if(!empty($briefing['summary']))
        <div style="margin: 0 0 28px 0; padding: 18px 20px; background-color: #181D16; border-left: 3px solid #3D7A32;">
            @if(!empty($briefing['title']))
                <p style="margin: 0 0 10px 0; font-family: 'Courier New', monospace; font-size: 11px; letter-spacing: 1.5px; color: #52A844; text-transform: uppercase; font-weight: bold;">
                    {{ $briefing['title'] }}
                </p>
            @endif
            <p style="margin: 0; font-size: 14px; line-height: 1.65; color: #D4E8CF;">
                {!! nl2br(e($briefing['summary'])) !!}
            </p>
        </div>
    @endif

    {{-- Statistics strip --}}
    @if(!empty($briefing['statistics']))
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 28px 0;">
            <tr>
                @if(isset($briefing['statistics']['total_events']))
                    <td align="center" width="33%" style="padding: 12px 8px; background-color: #111510; border: 1px solid #243320;">
                        <div style="font-family: 'Bebas Neue', 'Arial Narrow', Arial, sans-serif; font-size: 26px; color: #6FD65A; font-weight: normal;">{{ $briefing['statistics']['total_events'] }}</div>
                        <div style="font-family: 'Courier New', monospace; font-size: 9px; letter-spacing: 1.5px; color: #4A6344; text-transform: uppercase; margin-top: 2px;">@lang('newsletter.daily.stats.events')</div>
                    </td>
                @endif
                @if(isset($briefing['statistics']['avg_severity']))
                    <td align="center" width="33%" style="padding: 12px 8px; background-color: #111510; border: 1px solid #243320;">
                        <div style="font-family: 'Bebas Neue', 'Arial Narrow', Arial, sans-serif; font-size: 26px; color: #C97B1A; font-weight: normal;">{{ $briefing['statistics']['avg_severity'] }}</div>
                        <div style="font-family: 'Courier New', monospace; font-size: 9px; letter-spacing: 1.5px; color: #4A6344; text-transform: uppercase; margin-top: 2px;">@lang('newsletter.daily.stats.avgSeverity')</div>
                    </td>
                @endif
                @if(isset($briefing['statistics']['new_threads']))
                    <td align="center" width="33%" style="padding: 12px 8px; background-color: #111510; border: 1px solid #243320;">
                        <div style="font-family: 'Bebas Neue', 'Arial Narrow', Arial, sans-serif; font-size: 26px; color: #52A844; font-weight: normal;">{{ $briefing['statistics']['new_threads'] }}</div>
                        <div style="font-family: 'Courier New', monospace; font-size: 9px; letter-spacing: 1.5px; color: #4A6344; text-transform: uppercase; margin-top: 2px;">@lang('newsletter.daily.stats.newThreads')</div>
                    </td>
                @endif
            </tr>
        </table>
    @endif

    {{-- Key developments --}}
    @if(!empty($briefing['key_developments']))
        <h2 style="margin: 0 0 12px 0; font-family: 'Courier New', monospace; font-size: 12px; letter-spacing: 2px; color: #52A844; text-transform: uppercase;">
            @lang('newsletter.daily.keyDevelopments')
        </h2>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 28px 0;">
            @foreach($briefing['key_developments'] as $development)
                <tr>
                    <td style="padding: 10px 14px; {{ !$loop->last ? 'border-bottom: 1px solid #1E2B1A;' : '' }}">
                        @if(is_string($development))
                            <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #8AAD83;">{{ $development }}</p>
                        @else
                            <p style="margin: 0 0 3px 0; font-size: 13px; line-height: 1.4; color: #D4E8CF; font-weight: bold;">{{ $development['title'] ?? '' }}</p>
                            @if(!empty($development['description']))
                                <p style="margin: 0; font-size: 12px; line-height: 1.55; color: #8AAD83;">{{ $development['description'] }}</p>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- Affiliate slot (sponsored) --}}
    @include('emails.partials.affiliate-slot', ['affiliate' => $affiliate ?? null, 'locale' => $locale])

    {{-- Top events --}}
    @if(!empty($events))
        <h2 style="margin: 0 0 14px 0; font-family: 'Courier New', monospace; font-size: 12px; letter-spacing: 2px; color: #52A844; text-transform: uppercase;">
            @lang('newsletter.daily.topEvents')
        </h2>
        @foreach($events as $event)
            @include('emails.partials.event-card', ['event' => $event])
        @endforeach
    @else
        <p style="margin: 0 0 16px 0; font-size: 13px; line-height: 1.5; color: #4A6344; font-style: italic;">
            @lang('newsletter.daily.noEvents')
        </p>
    @endif
@else
    {{-- Threads-only mode: still show affiliate before thread digests --}}
    @include('emails.partials.affiliate-slot', ['affiliate' => $affiliate ?? null, 'locale' => $locale])
@endif

{{-- Thread digests (per-conflict sections) --}}
@if(!empty($thread_digests))
    @if($include_global)
        <hr style="margin: 32px 0; border: none; border-top: 1px solid #243320;">
    @endif
    <h2 style="margin: 0 0 18px 0; font-family: 'Courier New', monospace; font-size: 12px; letter-spacing: 2px; color: #52A844; text-transform: uppercase;">
        @lang('newsletter.daily.yourConflicts')
    </h2>
    @foreach($thread_digests as $digest)
        @include('emails.partials.thread-digest', ['digest' => $digest])
    @endforeach
@elseif(!$include_global)
    {{-- User wanted only thread digests but no subscribed threads had events today --}}
    <p style="margin: 0 0 16px 0; font-size: 13px; line-height: 1.5; color: #4A6344; font-style: italic;">
        @lang('newsletter.daily.noThreadEvents')
    </p>
@endif

@endcomponent
