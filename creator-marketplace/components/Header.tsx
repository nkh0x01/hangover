'use client';

import Link from 'next/link';
import { useState } from 'react';
import { IconMenu, IconChat } from './Icons';
import { LanguageToggle } from './LanguageToggle';

const nav = [
  { href: '/marketplace', label: 'კრეატორები' },
  { href: '/marketplace?for=business', label: 'ბიზნესისთვის' },
  { href: '/auth/register/creator', label: 'გახდი კრეატორი' },
  { href: '/about', label: 'ჩვენ შესახებ' },
];

export function Header() {
  const [open, setOpen] = useState(false);
  return (
    <header className="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-ink-100">
      <div className="container-page flex h-16 items-center justify-between gap-4">
        <Link href="/" className="flex items-center gap-2">
          <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white font-bold">
            კ
          </span>
          <span className="text-lg font-extrabold tracking-tight text-ink-900">
            კრეატორები<span className="text-brand-600">.</span>ge
          </span>
        </Link>

        <nav className="hidden lg:flex items-center gap-7 text-sm font-medium text-ink-700">
          {nav.map((n) => (
            <Link key={n.href} href={n.href} className="hover:text-ink-900 transition">
              {n.label}
            </Link>
          ))}
        </nav>

        <div className="flex items-center gap-2">
          <LanguageToggle />
          <Link href="/messages" className="btn-ghost hidden sm:inline-flex" aria-label="შეტყობინებები">
            <IconChat />
          </Link>
          <Link href="/auth/login" className="btn-ghost hidden md:inline-flex">
            შესვლა
          </Link>
          <Link href="/auth/register" className="btn-primary hidden md:inline-flex">
            რეგისტრაცია
          </Link>
          <button
            onClick={() => setOpen((s) => !s)}
            className="btn-ghost lg:hidden"
            aria-label="Menu"
          >
            <IconMenu />
          </button>
        </div>
      </div>

      {open && (
        <div className="lg:hidden border-t border-ink-100 bg-white">
          <div className="container-page flex flex-col py-3 gap-2">
            {nav.map((n) => (
              <Link
                key={n.href}
                href={n.href}
                onClick={() => setOpen(false)}
                className="py-2 text-sm font-medium text-ink-700"
              >
                {n.label}
              </Link>
            ))}
            <div className="flex gap-2 pt-2 border-t border-ink-100 mt-2">
              <Link href="/auth/login" className="btn-secondary flex-1">შესვლა</Link>
              <Link href="/auth/register" className="btn-primary flex-1">რეგისტრაცია</Link>
            </div>
          </div>
        </div>
      )}
    </header>
  );
}
