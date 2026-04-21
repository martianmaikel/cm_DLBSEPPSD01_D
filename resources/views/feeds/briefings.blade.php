{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>{{ $feedTitle }}</title>
    <link href="{{ $feedUrl }}" rel="self" type="application/atom+xml" />
    <link href="{{ $siteUrl }}" rel="alternate" type="text/html" />
    <id>{{ $feedUrl }}</id>
    <updated>{{ $briefings->first()?->updated_at?->toAtomString() ?? now()->toAtomString() }}</updated>
    <author>
        <name>ClashMonitor</name>
        <uri>{{ url('/') }}</uri>
    </author>
    <icon>{{ url('/icon-192.png') }}</icon>
    <subtitle>Daily intelligence briefings from ClashMonitor.</subtitle>
    @foreach ($briefings as $briefing)
        <entry>
            <title>Daily Intelligence Briefing — {{ $briefing->briefing_date->format('M j, Y') }}</title>
            <link href="{{ url("/briefing/{$briefing->briefing_date->format('Y-m-d')}") }}" rel="alternate"
                type="text/html" />
            <id>urn:clashmonitor:briefing:{{ $briefing->briefing_date->format('Y-m-d') }}</id>
            <published>{{ $briefing->generated_at?->toAtomString() ?? $briefing->created_at->toAtomString() }}
            </published>
            <updated>{{ $briefing->updated_at->toAtomString() }}</updated>
            @if ($briefing->summary_en)
                <summary type="text">{{ e(\Illuminate\Support\Str::limit($briefing->summary_en, 500)) }}</summary>
            @endif
        </entry>
    @endforeach
</feed>
