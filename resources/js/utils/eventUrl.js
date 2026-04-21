export function eventUrl(event) {
    const base = `/event/${event.id}`;
    return event.slug ? `${base}-${event.slug}` : base;
}
