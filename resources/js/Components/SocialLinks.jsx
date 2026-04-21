const TelegramIcon = ({ className }) => (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
    </svg>
);

const XIcon = ({ className }) => (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
    </svg>
);

const FacebookIcon = ({ className }) => (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 1.09.044 1.613.115v3.146a10 10 0 0 0-.916-.036c-1.3 0-1.804.494-1.804 1.776v2.557h3.476l-.597 3.667h-2.879v8.073C19.253 22.668 23 18.068 23 12.5 23 6.69 18.31 2 12.5 2S2 6.69 2 12.5c0 4.87 3.44 8.937 8.02 9.907a12 12 0 0 0 1.08.284z" />
    </svg>
);

const BlueskyIcon = ({ className }) => (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M12 10.8c-1.087-2.114-4.046-6.053-6.798-7.995C2.566.944 1.561 1.266.902 1.565.139 1.908 0 3.08 0 3.768c0 .69.378 5.65.624 6.479.785 2.627 3.6 3.476 6.158 3.226-4.476.767-5.91 3.168-3.268 6.217 3.167 3.142 5.752.876 6.486-.946.56-1.388.816-2.86.816-3.476 0 .617.256 2.088.816 3.476.734 1.822 3.32 4.088 6.486.946 2.642-3.049 1.208-5.45-3.268-6.217 2.558.25 5.373-.6 6.158-3.226C21.622 9.418 22 4.458 22 3.768c0-.69-.139-1.861-.902-2.203-.659-.3-1.664-.62-4.3 1.24C14.046 4.747 11.087 8.686 10 10.8h2z" />
    </svg>
);

export default function SocialLinks({ className = '' }) {
    return (
        <div className={`flex items-center gap-3 ${className}`}>
            <a href="https://t.me/clashmonitor_en" target="_blank" rel="noopener noreferrer" className="text-text-dim hover:text-green-bright transition-colors" title="Telegram">
                <TelegramIcon className="w-3.5 h-3.5" />
            </a>
            <a href="https://bsky.app/profile/clashmonitor.bsky.social" target="_blank" rel="noopener noreferrer" className="text-text-dim hover:text-green-bright transition-colors" title="Bluesky">
                <BlueskyIcon className="w-3.5 h-3.5" />
            </a>
            <a href="https://x.com/clashmonitor_en" target="_blank" rel="noopener noreferrer" className="text-text-dim hover:text-green-bright transition-colors" title="X">
                <XIcon className="w-3.5 h-3.5" />
            </a>
            <a href="https://www.facebook.com/profile.php?id=61572054002388" target="_blank" rel="noopener noreferrer" className="text-text-dim hover:text-green-bright transition-colors" title="Facebook">
                <FacebookIcon className="w-3.5 h-3.5" />
            </a>
        </div>
    );
}
