import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import SocialLinks from '../Components/SocialLinks';
import SeoHead from '../Components/SeoHead';
import { useTheme } from '../Contexts/ThemeContext';

export default function AppLayout({ children, breadcrumbs = [], isDashboard = false }) {
    const { t, i18n } = useTranslation();
    const { highContrast, toggleHighContrast } = useTheme();
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    const toggleLanguage = () => {
        const newLang = i18n.language === 'en' ? 'de' : 'en';
        i18n.changeLanguage(newLang);
        localStorage.setItem('fw-lang', newLang);
    };

    return (
        <div className="min-h-screen bg-black text-text-primary font-sans">
            <SeoHead />
            {/* Header */}
            <header className="border-b border-border-mid px-4 md:px-6 py-3 h-16 flex items-center relative">
                <div className="w-full flex items-center justify-between">
                    <div className="flex items-center gap-4 md:gap-6">
                        <Link href="/" className="font-display text-xl md:text-2xl tracking-wider text-green-bright hover:text-green-neon transition-colors">
                            CLASH<span className="text-text-secondary">MONITOR</span>
                        </Link>

                        {/* Main navigation — desktop */}
                        <nav className="hidden md:flex items-center gap-4 font-mono text-xs tracking-widest uppercase">
                            <Link
                                href="/"
                                className="text-text-secondary hover:text-green-bright transition-colors"
                            >
                                {t('nav.events', 'Events')}
                            </Link>
                            <Link
                                href="/map/hotzones"
                                className="text-text-secondary hover:text-green-bright transition-colors"
                            >
                                {t('nav.hotzones', 'Hotzones')}
                            </Link>
                            <Link
                                href="/conflicts"
                                className="text-text-secondary hover:text-green-bright transition-colors"
                            >
                                {t('nav.conflicts', 'Conflicts')}
                            </Link>
                            <Link
                                href="/actors"
                                className="text-text-secondary hover:text-green-bright transition-colors"
                            >
                                {t('nav.actors', 'Actors')}
                            </Link>
                            <Link
                                href="/graph"
                                className="text-text-secondary hover:text-green-bright transition-colors"
                            >
                                {t('nav.graph', 'Graph')}
                            </Link>
                            <Link
                                href="/digest"
                                className="text-text-secondary hover:text-green-bright transition-colors"
                            >
                                {t('nav.digest', 'Digest')}
                            </Link>
                            <Link
                                href="/methodology"
                                className="text-text-secondary hover:text-green-bright transition-colors"
                            >
                                {t('nav.methodology', 'Methodology')}
                            </Link>
                        </nav>
                    </div>

                    <div className="flex items-center gap-3 md:gap-4">
                        {/* Breadcrumbs (non-dashboard pages) */}
                        {!isDashboard && breadcrumbs.length > 0 && (
                            <nav className="hidden md:flex items-center gap-2 font-mono text-xs tracking-widest uppercase text-text-muted">
                                {breadcrumbs.map((crumb, i) => (
                                    <span key={i} className="flex items-center gap-2">
                                        {i > 0 && <span className="text-border-mid">/</span>}
                                        {crumb.href ? (
                                            <Link href={crumb.href} className="hover:text-green-bright transition-colors">
                                                {crumb.label}
                                            </Link>
                                        ) : (
                                            <span className="text-text-secondary">{crumb.label}</span>
                                        )}
                                    </span>
                                ))}
                            </nav>
                        )}

                        {/* Social links — desktop */}
                        <SocialLinks className="hidden md:flex" />

                        {/* High Contrast Toggle */}
                        <button
                            onClick={toggleHighContrast}
                            className={`font-mono text-xs tracking-wider px-2 py-1 border rounded transition-colors ${
                                highContrast
                                    ? 'border-green-base text-green-bright bg-surface-2'
                                    : 'border-border-mid hover:border-green-base hover:text-green-bright'
                            }`}
                            aria-label={highContrast ? 'Switch to dark mode' : 'Switch to high contrast mode'}
                            title={highContrast ? 'Dark mode' : 'High contrast'}
                        >
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.3" className="inline-block">
                                <circle cx="8" cy="8" r="6" />
                                <path d="M8 2 L8 14" />
                                <path d="M8 2 A6 6 0 0 1 8 14" fill="currentColor" />
                            </svg>
                        </button>

                        {/* Language Switcher */}
                        <button
                            onClick={toggleLanguage}
                            className="font-mono text-xs tracking-wider px-3 py-1 border border-border-mid rounded hover:border-green-base hover:text-green-bright transition-colors"
                        >
                            {i18n.language === 'en' ? 'DE' : 'EN'}
                        </button>

                        {/* Mobile hamburger */}
                        <button
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="md:hidden flex flex-col justify-center items-center w-8 h-8 gap-1.5"
                            aria-label="Toggle menu"
                        >
                            <span className={`block w-5 h-px bg-text-secondary transition-all duration-200 ${mobileMenuOpen ? 'rotate-45 translate-y-[3.5px]' : ''}`} />
                            <span className={`block w-5 h-px bg-text-secondary transition-all duration-200 ${mobileMenuOpen ? 'opacity-0' : ''}`} />
                            <span className={`block w-5 h-px bg-text-secondary transition-all duration-200 ${mobileMenuOpen ? '-rotate-45 -translate-y-[3.5px]' : ''}`} />
                        </button>
                    </div>
                </div>

                {/* Mobile dropdown menu */}
                {mobileMenuOpen && (
                    <div className="absolute top-full left-0 right-0 bg-surface-1 border-b border-border-mid z-50 md:hidden">
                        <nav className="flex flex-col font-mono text-xs tracking-widest uppercase">
                            <Link
                                href="/"
                                onClick={() => setMobileMenuOpen(false)}
                                className="px-4 py-3 text-text-secondary hover:text-green-bright hover:bg-surface-2 transition-colors border-b border-border-subtle"
                            >
                                {t('nav.events', 'Events')}
                            </Link>
                            <Link
                                href="/map/hotzones"
                                onClick={() => setMobileMenuOpen(false)}
                                className="px-4 py-3 text-text-secondary hover:text-green-bright hover:bg-surface-2 transition-colors border-b border-border-subtle"
                            >
                                {t('nav.hotzones', 'Hotzones')}
                            </Link>
                            <Link
                                href="/conflicts"
                                onClick={() => setMobileMenuOpen(false)}
                                className="px-4 py-3 text-text-secondary hover:text-green-bright hover:bg-surface-2 transition-colors border-b border-border-subtle"
                            >
                                {t('nav.conflicts', 'Conflicts')}
                            </Link>
                            <Link
                                href="/actors"
                                onClick={() => setMobileMenuOpen(false)}
                                className="px-4 py-3 text-text-secondary hover:text-green-bright hover:bg-surface-2 transition-colors border-b border-border-subtle"
                            >
                                {t('nav.actors', 'Actors')}
                            </Link>
                            <Link
                                href="/graph"
                                onClick={() => setMobileMenuOpen(false)}
                                className="px-4 py-3 text-text-secondary hover:text-green-bright hover:bg-surface-2 transition-colors border-b border-border-subtle"
                            >
                                {t('nav.graph', 'Graph')}
                            </Link>
                            <Link
                                href="/digest"
                                onClick={() => setMobileMenuOpen(false)}
                                className="px-4 py-3 text-text-secondary hover:text-green-bright hover:bg-surface-2 transition-colors border-b border-border-subtle"
                            >
                                {t('nav.digest', 'Digest')}
                            </Link>
                            <Link
                                href="/methodology"
                                onClick={() => setMobileMenuOpen(false)}
                                className="px-4 py-3 text-text-secondary hover:text-green-bright hover:bg-surface-2 transition-colors border-b border-border-subtle"
                            >
                                {t('nav.methodology', 'Methodology')}
                            </Link>
                            <Link
                                href="/newsletter"
                                onClick={() => setMobileMenuOpen(false)}
                                className="px-4 py-3 flex items-center gap-2 text-green-bright hover:bg-surface-2 transition-colors border-b border-border-subtle"
                            >
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                {t('cta.morningBriefing')}
                            </Link>
                        </nav>
                        <div className="px-4 py-3">
                            <SocialLinks />
                        </div>
                    </div>
                )}
            </header>

            {/* Main Content */}
            {isDashboard ? (
                <main>{children}</main>
            ) : (
                <main className="max-w-7xl mx-auto px-4 md:px-6 py-4 md:py-8">
                    {children}
                </main>
            )}

            {/* Legal footer */}
            <footer className="border-t border-border-subtle px-4 py-3 flex items-center justify-center gap-3 font-mono text-[10px] text-text-dim tracking-wider">
                <Link href="/newsletter" className="hover:text-green-bright text-text-muted transition-colors">
                    {t('cta.morningBriefing')}
                </Link>
                <span className="text-border-subtle">·</span>
                <Link href="/impressum" className="hover:text-text-muted transition-colors">
                    {t('legal.impressum', 'Impressum')}
                </Link>
                <span className="text-border-subtle">·</span>
                <Link href="/datenschutz" className="hover:text-text-muted transition-colors">
                    {t('legal.privacy', 'Datenschutz')}
                </Link>
            </footer>
        </div>
    );
}
