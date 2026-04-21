<?php

return [
    'confirm' => [
        'subject' => 'Bestätigen Sie Ihr ClashMonitor Abonnement',
        'heading' => 'Nur noch ein Schritt',
        'intro' => 'Danke für Ihr Abonnement des ClashMonitor Morning Briefings. Bitte bestätigen Sie Ihre E-Mail-Adresse, um das Abonnement zu aktivieren.',
        'cta' => 'Abonnement bestätigen',
        'fallback' => 'Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:',
        'ignore' => 'Wenn Sie diese E-Mail nicht angefordert haben, können Sie sie einfach ignorieren.',
    ],
    'daily' => [
        'subject' => 'Morning Briefing · :date',
        'heading' => 'Dein heutiges Intel Briefing',
        'keyDevelopments' => 'Wichtige Entwicklungen',
        'topEvents' => 'Top-Ereignisse (letzte 24h)',
        'noEvents' => 'Keine bestätigten Ereignisse in den letzten 24 Stunden.',
        'viewDetails' => 'Details ansehen',
        'source' => 'Quelle',
        'stats' => [
            'events' => 'Ereignisse',
            'avgSeverity' => 'Ø Schweregrad',
            'newThreads' => 'Neue Threads',
        ],
        'yourConflicts' => 'Ihre Konflikte',
        'thread' => 'Thread',
        'eventsIn24h' => 'Ereignisse (24h)',
        'noThreadEvents' => 'Heute keine neuen Ereignisse in Ihren abonnierten Konflikten.',
    ],
    'critical' => [
        'subject' => '[ALERT] :thread — Schweregrad :severity',
        'heading' => 'Kritisches Ereignis',
        'why' => 'Sie erhalten diesen Alert, weil Sie diesen Konflikt abonniert haben und Critical Alerts aktiviert sind. Passen Sie Ihre Einstellungen unten an.',
    ],
    'test' => [
        'subject' => '[TEST] ClashMonitor Newsletter',
        'heading' => 'Testzustellung',
        'body' => 'Dies ist eine Testnachricht aus dem ClashMonitor Admin-Panel.',
    ],
    'footer' => [
        'legal' => 'Sie erhalten diese E-Mail, weil Sie sich auf clashmonitor.com angemeldet haben.',
        'unsubscribe' => 'Abmelden',
        'preferences' => 'Einstellungen verwalten',
        'tagline' => 'Echtzeit-Konfliktbeobachtung und OSINT',
    ],
];
