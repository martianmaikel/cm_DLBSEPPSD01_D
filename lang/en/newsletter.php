<?php

return [
    'confirm' => [
        'subject' => 'Confirm your ClashMonitor subscription',
        'heading' => 'One more step',
        'intro' => 'Thanks for subscribing to the ClashMonitor morning briefing. Please confirm your email address to activate your subscription.',
        'cta' => 'Confirm subscription',
        'fallback' => 'If the button does not work, copy this link into your browser:',
        'ignore' => 'If you did not request this, you can safely ignore this email.',
    ],
    'daily' => [
        'subject' => 'Morning Briefing · :date',
        'heading' => 'Your intel briefing',
        'keyDevelopments' => 'Key Developments',
        'topEvents' => 'Top Events (Last 24h)',
        'noEvents' => 'No corroborated events in the last 24 hours.',
        'viewDetails' => 'View details',
        'source' => 'Source',
        'stats' => [
            'events' => 'Events',
            'avgSeverity' => 'Avg severity',
            'newThreads' => 'New threads',
        ],
        'yourConflicts' => 'Your conflicts',
        'thread' => 'Thread',
        'eventsIn24h' => 'events last 24h',
        'noThreadEvents' => 'No new events in your subscribed conflicts today.',
    ],
    'critical' => [
        'subject' => '[ALERT] :thread — severity :severity',
        'heading' => 'Critical event',
        'why' => 'You are receiving this alert because you subscribed to this conflict and have critical alerts enabled. Adjust your preferences below.',
    ],
    'test' => [
        'subject' => '[TEST] ClashMonitor newsletter',
        'heading' => 'Test delivery',
        'body' => 'This is a test message from the ClashMonitor admin panel.',
    ],
    'footer' => [
        'legal' => 'You are receiving this email because you subscribed at clashmonitor.com.',
        'unsubscribe' => 'Unsubscribe',
        'preferences' => 'Manage preferences',
        'tagline' => 'Real-time conflict monitoring and OSINT',
    ],
];
