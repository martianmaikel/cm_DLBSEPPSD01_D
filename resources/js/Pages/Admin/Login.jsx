import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Login({ errors = {} }) {
    const { t } = useTranslation();
    const { data, setData, post, processing } = useForm({ token: '' });
    const [showToken, setShowToken] = useState(false);

    function handleSubmit(e) {
        e.preventDefault();
        post('/admin/login');
    }

    return (
        <div className="min-h-screen bg-black flex items-center justify-center p-4">
            <div className="w-full max-w-sm">
                {/* Logo */}
                <div className="text-center mb-8">
                    <div className="font-display text-4xl tracking-wider text-green-bright mb-1">
                        CLASH<span className="text-text-secondary">MONITOR</span>
                    </div>
                    <div className="font-mono text-xs tracking-widest uppercase text-text-muted">
                        {t('admin.login')}
                    </div>
                </div>

                {/* Form card */}
                <form
                    onSubmit={handleSubmit}
                    className="bg-surface-1 border border-border-mid rounded p-6 space-y-4"
                >
                    <div>
                        <label className="block font-mono text-xs tracking-widest uppercase text-text-muted mb-2">
                            Token
                        </label>
                        <div className="relative">
                            <input
                                type={showToken ? 'text' : 'password'}
                                value={data.token}
                                onChange={e => setData('token', e.target.value)}
                                placeholder={t('admin.tokenPlaceholder')}
                                autoComplete="current-password"
                                className="w-full bg-surface-2 border border-border-mid rounded px-4 py-3 font-mono text-sm text-text-primary placeholder-text-dim focus:outline-none focus:border-green-base transition-colors pr-12"
                            />
                            <button
                                type="button"
                                onClick={() => setShowToken(v => !v)}
                                className="absolute right-3 top-1/2 -translate-y-1/2 font-mono text-xs text-text-muted hover:text-text-secondary transition-colors"
                            >
                                {showToken ? 'hide' : 'show'}
                            </button>
                        </div>
                        {errors.token && (
                            <p className="mt-2 font-mono text-xs text-red-bright">{errors.token}</p>
                        )}
                        {errors.message && (
                            <p className="mt-2 font-mono text-xs text-red-bright">{errors.message}</p>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={processing || !data.token}
                        className="w-full font-mono text-xs tracking-widest uppercase py-3 border border-green-base text-green-bright hover:bg-green-dim hover:border-green-bright disabled:opacity-40 disabled:cursor-not-allowed transition-colors rounded"
                    >
                        {processing ? t('common.loading') : t('admin.authenticate')}
                    </button>
                </form>

                {/* Security note */}
                <p className="text-center font-mono text-xs text-text-dim mt-4">
                    Restricted access · All actions are logged
                </p>
            </div>
        </div>
    );
}
