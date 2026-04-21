{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>{{ $feedTitle }}</title>
    <link href="{{ $feedUrl }}" rel="self" type="application/atom+xml"/>
    <link href="{{ $siteUrl }}" rel="alternate" type="text/html"/>
    <id>{{ $feedUrl }}</id>
    <updated>{{ $events->first()?->occurred_at?->toAtomString() ?? now()->toAtomString() }}</updated>
    <author>
        <name>ClashMonitor</name>
        <uri>{{ url('/') }}</uri>
    </author>
    <icon>{{ url('/icon-192.png') }}</icon>
    <subtitle>Real-time conflict monitoring and OSINT intelligence.</subtitle>
@foreach($events as $event)
    <entry>
        <title>{{ e($event->title) }}</title>
        <link href="{{ url("/event/{$event->id}" . ($event->slug ? "-{$event->slug}" : '')) }}" rel="alternate" type="text/html"/>
        <id>urn:clashmonitor:event:{{ $event->id }}</id>
        <published>{{ $event->occurred_at?->toAtomString() ?? $event->created_at->toAtomString() }}</published>
        <updated>{{ $event->updated_at->toAtomString() }}</updated>
@if($event->summary)
        <summary type="text">{{ e(\Illuminate\Support\Str::limit($event->summary, 500)) }}</summary>
@endif
        <category term="{{ $event->category }}" label="{{ $event->category }}"/>
@if($event->country)
        <category term="{{ $event->country }}" label="{{ $event->country }}"/>
@endif
@if($event->source)
        <source>
            <title>{{ e($event->source->name) }}</title>
        </source>
@endif
    </entry>
@endforeach
</feed>
