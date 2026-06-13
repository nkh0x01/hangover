'use client';

import { useEffect, useState } from 'react';

export function LanguageToggle() {
  const [locale, setLocale] = useState<'ka' | 'en'>('ka');

  useEffect(() => {
    const stored = (typeof window !== 'undefined' && window.localStorage.getItem('locale')) as
      | 'ka'
      | 'en'
      | null;
    if (stored) setLocale(stored);
  }, []);

  function toggle() {
    const next = locale === 'ka' ? 'en' : 'ka';
    setLocale(next);
    if (typeof window !== 'undefined') {
      window.localStorage.setItem('locale', next);
      document.cookie = `locale=${next}; path=/; max-age=31536000`;
    }
  }

  return (
    <button
      onClick={toggle}
      className="inline-flex items-center gap-1.5 rounded-xl border border-ink-200 bg-white px-3 py-2 text-xs font-semibold text-ink-700 hover:bg-ink-50 transition"
      aria-label="Language"
      title="Change language"
    >
      <span className={locale === 'ka' ? 'text-ink-900' : 'text-ink-400'}>KA</span>
      <span className="text-ink-300">/</span>
      <span className={locale === 'en' ? 'text-ink-900' : 'text-ink-400'}>EN</span>
    </button>
  );
}
