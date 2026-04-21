import { useState, useRef, useEffect } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

const LEVEL_COLORS = {
    emergency: 'bg-red-alert text-white',
    alert:     'bg-red-bright text-white',
    critical:  'bg-red-bright text-white',
    error:     'bg-red-bright/80 text-white',
    warning:   'bg-amber text-black',
    notice:    'bg-blue-intel text-white',
    info:      'bg-green-base text-white',
    debug:     'bg-text-dim text-white',
};

const LEVEL_TEXT = {
    emergency: 'text-red-alert',
    alert:     'text-red-alert',
    critical:  'text-red-bright',
    error:     'text-red-bright',
    warning:   'text-amber-bright',
    notice:    'text-blue-bright',
    info:      'text-green-bright',
    debug:     'text-text-muted',
};

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let val = bytes;
    while (val >= 1024 && i < units.length - 1) {
        val /= 1024;
        i++;
    }
    return `${val.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function LevelBadge({ level }) {
    const color = LEVEL_COLORS[level] || 'bg-surface-3 text-text-muted';
    return (
        <span className={`inline-block font-mono text-[10px] tracking-widest uppercase px-2 py-0.5 rounded ${color}`}>
            {level}
        </span>
    );
}

function LogEntry({ entry, isExpanded, onToggle }) {
    const textColor = LEVEL_TEXT[entry.level] || 'text-text-secondary';

    return (
        <div
            className="border-b border-border-subtle hover:bg-surface-2/50 transition-colors cursor-pointer"
            onClick={onToggle}
        >
            <div className="flex items-start gap-3 px-4 py-2.5">
                <span className="font-mono text-[11px] text-text-dim whitespace-nowrap shrink-0 pt-0.5">
                    {entry.timestamp}
                </span>
                <span className="shrink-0 pt-0.5">
                    <LevelBadge level={entry.level} />
                </span>
                <span className={`font-mono text-xs leading-relaxed break-all ${textColor}`}>
                    {entry.message}
                </span>
            </div>
            {isExpanded && entry.stack && (
                <div className="px-4 pb-3 pl-12">
                    <pre className="font-mono text-[11px] text-text-dim leading-relaxed whitespace-pre-wrap break-all bg-surface-3 rounded p-3 max-h-80 overflow-auto">
                        {entry.stack}
                    </pre>
                </div>
            )}
        </div>
    );
}

export default function Logs({ files = [], selectedFile, entries = [], fileSizeBytes = 0, filters = {} }) {
    const [expandedIdx, setExpandedIdx] = useState(null);
    const [searchInput, setSearchInput] = useState(filters.search || '');
    const [autoScroll, setAutoScroll] = useState(true);
    const scrollRef = useRef(null);

    const breadcrumbs = [
        { label: 'Dashboard', href: '/admin' },
        { label: 'Logs' },
    ];

    useEffect(() => {
        if (autoScroll && scrollRef.current) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
        }
    }, [entries, autoScroll]);

    function selectFile(file) {
        router.get('/admin/logs', { file, level: filters.level, search: filters.search }, {
            preserveState: false,
        });
    }

    function selectLevel(level) {
        const newLevel = filters.level === level ? null : level;
        router.get('/admin/logs', { file: selectedFile, level: newLevel, search: filters.search }, {
            preserveState: false,
        });
    }

    function submitSearch(e) {
        e.preventDefault();
        router.get('/admin/logs', { file: selectedFile, level: filters.level, search: searchInput || null }, {
            preserveState: false,
        });
    }

    function clearSearch() {
        setSearchInput('');
        router.get('/admin/logs', { file: selectedFile, level: filters.level }, {
            preserveState: false,
        });
    }

    function refreshLogs() {
        router.reload({ preserveScroll: true });
    }

    // Count entries by level
    const levelCounts = {};
    entries.forEach(e => {
        levelCounts[e.level] = (levelCounts[e.level] || 0) + 1;
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-5">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <h1 className="font-display text-3xl tracking-wider text-green-bright">
                        LOGS
                    </h1>
                    <div className="flex items-center gap-3">
                        {selectedFile && (
                            <>
                                <a
                                    href={`/admin/logs/download?file=${encodeURIComponent(selectedFile)}`}
                                    className="font-mono text-xs tracking-widest uppercase px-3 py-1.5 border border-border-mid text-text-secondary hover:border-border-active hover:text-green-bright transition-colors rounded"
                                >
                                    Download
                                </a>
                                <button
                                    onClick={() => {
                                        if (window.confirm(`Clear ${selectedFile}? This cannot be undone.`)) {
                                            router.post('/admin/logs/clear', { file: selectedFile });
                                        }
                                    }}
                                    className="font-mono text-xs tracking-widest uppercase px-3 py-1.5 border border-red-bright/30 text-red-bright hover:border-red-bright hover:bg-red-bright/10 transition-colors rounded"
                                >
                                    Clear
                                </button>
                            </>
                        )}
                        <button
                            onClick={refreshLogs}
                            className="font-mono text-xs tracking-widest uppercase px-3 py-1.5 border border-border-mid text-text-secondary hover:border-border-active hover:text-green-bright transition-colors rounded"
                        >
                            Refresh
                        </button>
                    </div>
                </div>

                {/* File selector + search */}
                <div className="flex flex-wrap items-center gap-4">
                    {/* File dropdown */}
                    <div className="flex items-center gap-2">
                        <label className="font-mono text-xs text-text-muted tracking-widest uppercase">File</label>
                        <select
                            value={selectedFile || ''}
                            onChange={e => selectFile(e.target.value)}
                            className="bg-surface-2 border border-border-mid text-text-primary font-mono text-xs rounded px-3 py-1.5 focus:border-green-base focus:outline-none"
                        >
                            {files.length === 0 && <option value="">No log files</option>}
                            {files.map(f => (
                                <option key={f} value={f}>{f}</option>
                            ))}
                        </select>
                        {fileSizeBytes > 0 && (
                            <span className="font-mono text-[11px] text-text-dim">
                                {formatBytes(fileSizeBytes)}
                            </span>
                        )}
                    </div>

                    {/* Search */}
                    <form onSubmit={submitSearch} className="flex items-center gap-2 flex-1 max-w-sm">
                        <input
                            type="text"
                            value={searchInput}
                            onChange={e => setSearchInput(e.target.value)}
                            placeholder="Search logs..."
                            className="bg-surface-2 border border-border-mid text-text-primary font-mono text-xs rounded px-3 py-1.5 flex-1 focus:border-green-base focus:outline-none placeholder:text-text-dim"
                        />
                        {filters.search && (
                            <button
                                type="button"
                                onClick={clearSearch}
                                className="font-mono text-xs text-text-muted hover:text-text-secondary transition-colors"
                            >
                                ×
                            </button>
                        )}
                    </form>

                    {/* Auto-scroll toggle */}
                    <label className="flex items-center gap-1.5 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={autoScroll}
                            onChange={e => setAutoScroll(e.target.checked)}
                            className="accent-green-base"
                        />
                        <span className="font-mono text-xs text-text-muted">Auto-scroll</span>
                    </label>
                </div>

                {/* Level filters */}
                <div className="flex flex-wrap items-center gap-2">
                    {LEVELS.map(level => {
                        const count = levelCounts[level] || 0;
                        const isActive = filters.level === level;
                        return (
                            <button
                                key={level}
                                onClick={() => selectLevel(level)}
                                className={`font-mono text-[11px] tracking-widest uppercase px-3 py-1 border rounded transition-colors ${
                                    isActive
                                        ? 'border-green-base text-green-bright bg-green-dim'
                                        : count > 0
                                            ? 'border-border-mid text-text-secondary hover:border-border-active'
                                            : 'border-border-subtle text-text-dim'
                                }`}
                            >
                                {level} {count > 0 && <span className="ml-1 opacity-60">{count}</span>}
                            </button>
                        );
                    })}
                </div>

                {/* Summary bar */}
                <div className="flex items-center gap-4 font-mono text-xs text-text-dim">
                    <span>{entries.length} entries</span>
                    {entries.length >= 500 && (
                        <span className="text-amber">Showing last 500 entries</span>
                    )}
                    {levelCounts.error > 0 && (
                        <span className="text-red-bright">{levelCounts.error} errors</span>
                    )}
                    {levelCounts.warning > 0 && (
                        <span className="text-amber-bright">{levelCounts.warning} warnings</span>
                    )}
                </div>

                {/* Log entries */}
                <div
                    ref={scrollRef}
                    className="bg-surface-1 border border-border-mid rounded overflow-auto"
                    style={{ maxHeight: 'calc(100vh - 400px)', minHeight: '300px' }}
                >
                    {entries.length === 0 ? (
                        <div className="px-4 py-12 text-center font-mono text-sm text-text-muted">
                            {files.length === 0
                                ? 'No log files found in storage/logs'
                                : selectedFile
                                    ? 'No log entries' + (filters.level || filters.search ? ' matching filters' : '')
                                    : 'Select a log file'
                            }
                        </div>
                    ) : (
                        entries.map((entry, idx) => (
                            <LogEntry
                                key={idx}
                                entry={entry}
                                isExpanded={expandedIdx === idx}
                                onToggle={() => setExpandedIdx(expandedIdx === idx ? null : idx)}
                            />
                        ))
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
