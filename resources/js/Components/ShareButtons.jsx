import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function ShareButtons({ url, title }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);

    const shareUrl = url || window.location.href;
    const shareTitle = title || document.title;
    const encodedUrl = encodeURIComponent(shareUrl);
    const encodedTitle = encodeURIComponent(shareTitle);

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(shareUrl);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Fallback for older browsers
            const input = document.createElement('input');
            input.value = shareUrl;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    const links = [
        {
            name: 'Telegram',
            href: `https://t.me/share/url?url=${encodedUrl}&text=${encodedTitle}`,
            icon: (
                <svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                </svg>
            ),
        },
        {
            name: 'X',
            href: `https://x.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}`,
            icon: (
                <svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                </svg>
            ),
        },
        {
            name: 'Facebook',
            href: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`,
            icon: (
                <svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
            ),
        },
    ];

    return (
        <div className="flex items-center gap-2">
            <span className="font-mono text-[10px] tracking-widest uppercase text-text-dim">
                {t('share.label', 'Share')}
            </span>
            {links.map(link => (
                <a
                    key={link.name}
                    href={link.href}
                    target="_blank"
                    rel="noopener noreferrer"
                    title={link.name}
                    className="w-7 h-7 flex items-center justify-center rounded border border-border-mid text-text-muted hover:text-green-bright hover:border-green-base transition-colors"
                >
                    {link.icon}
                </a>
            ))}
            <button
                onClick={copyLink}
                title={t('share.copyLink', 'Copy link')}
                className="w-7 h-7 flex items-center justify-center rounded border border-border-mid text-text-muted hover:text-green-bright hover:border-green-base transition-colors"
            >
                {copied ? (
                    <svg className="w-3.5 h-3.5 text-green-bright" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                ) : (
                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                )}
            </button>
        </div>
    );
}
