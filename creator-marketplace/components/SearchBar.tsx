'use client';

import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { IconSearch } from './Icons';

export function SearchBar({ size = 'lg' }: { size?: 'lg' | 'md' }) {
  const router = useRouter();
  const [q, setQ] = useState('');

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const params = new URLSearchParams();
    if (q) params.set('q', q);
    router.push(`/marketplace${params.toString() ? `?${params}` : ''}`);
  };

  if (size === 'md') {
    return (
      <form onSubmit={onSubmit} className="relative">
        <IconSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400" />
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="მოძებნე კრეატორი, კატეგორია..."
          className="input pl-10"
        />
      </form>
    );
  }

  return (
    <form
      onSubmit={onSubmit}
      className="flex items-center gap-2 bg-white rounded-2xl border border-ink-200 shadow-soft p-2"
    >
      <div className="relative flex-1">
        <IconSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-ink-400" />
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="მოძებნე კრეატორი, კატეგორია, ქალაქი, ნიშა..."
          className="w-full bg-transparent text-base placeholder-ink-400 px-4 pl-12 py-3 focus:outline-none"
        />
      </div>
      <button type="submit" className="btn-primary px-6 py-3 text-base">
        ძებნა
      </button>
    </form>
  );
}
