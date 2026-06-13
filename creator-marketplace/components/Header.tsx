'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useSession, signOut } from 'next-auth/react';
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
  const [userMenu, setUserMenu] = useState(false);
  const { data: session } = useSession();
  const user = session?.user;

  const dashHref =
    user?.role === 'ADMIN'
      ? '/admin'
      : user?.role === 'CREATOR'
        ? '/dashboard/creator'
        : '/dashboard/client';

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
          {user && (
            <Link href="/messages" className="btn-ghost hidden sm:inline-flex" aria-label="შეტყობინებები">
              <IconChat />
            </Link>
          )}
          {user ? (
            <div className="relative">
              <button
                onClick={() => setUserMenu((s) => !s)}
                className="flex items-center gap-2 rounded-xl border border-ink-200 bg-white pl-1 pr-3 py-1 hover:bg-ink-50 transition"
                aria-label="User menu"
              >
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={user.image ?? `https://i.pravatar.cc/100?u=${encodeURIComponent(user.email)}`}
                  alt=""
                  className="h-7 w-7 rounded-full object-cover"
                />
                <span className="text-sm font-medium text-ink-800 hidden sm:inline max-w-[100px] truncate">
                  {user.name?.split(' ')[0] ?? user.email}
                </span>
              </button>
              {userMenu && (
                <>
                  <div className="fixed inset-0" onClick={() => setUserMenu(false)} />
                  <div className="absolute right-0 mt-1 w-56 rounded-xl border border-ink-200 bg-white shadow-soft py-1 z-50">
                    <div className="px-3 py-2 border-b border-ink-100">
                      <p className="text-sm font-semibold truncate">{user.name}</p>
                      <p className="text-xs muted truncate">{user.email}</p>
                      <span className="chip-brand mt-1 text-[10px]">
                        {user.role === 'ADMIN' ? 'ადმინი' : user.role === 'CREATOR' ? 'კრეატორი' : 'ბიზნესი'}
                      </span>
                    </div>
                    <Link href={dashHref} className="block px-3 py-2 text-sm hover:bg-ink-50" onClick={() => setUserMenu(false)}>
                      📊 დაშბორდი
                    </Link>
                    <Link href="/messages" className="block px-3 py-2 text-sm hover:bg-ink-50" onClick={() => setUserMenu(false)}>
                      💬 შეტყობინებები
                    </Link>
                    {user.role === 'ADMIN' && (
                      <Link href="/admin" className="block px-3 py-2 text-sm hover:bg-ink-50" onClick={() => setUserMenu(false)}>
                        👮 ადმინ პანელი
                      </Link>
                    )}
                    <button
                      onClick={() => signOut({ callbackUrl: '/' })}
                      className="w-full text-left px-3 py-2 text-sm text-red-700 hover:bg-red-50 border-t border-ink-100"
                    >
                      ↳ გასვლა
                    </button>
                  </div>
                </>
              )}
            </div>
          ) : (
            <>
              <Link href="/auth/login" className="btn-ghost hidden md:inline-flex">შესვლა</Link>
              <Link href="/auth/register" className="btn-primary hidden md:inline-flex">რეგისტრაცია</Link>
            </>
          )}
          <button onClick={() => setOpen((s) => !s)} className="btn-ghost lg:hidden" aria-label="Menu">
            <IconMenu />
          </button>
        </div>
      </div>

      {open && (
        <div className="lg:hidden border-t border-ink-100 bg-white">
          <div className="container-page flex flex-col py-3 gap-2">
            {nav.map((n) => (
              <Link key={n.href} href={n.href} onClick={() => setOpen(false)} className="py-2 text-sm font-medium text-ink-700">
                {n.label}
              </Link>
            ))}
            {!user ? (
              <div className="flex gap-2 pt-2 border-t border-ink-100 mt-2">
                <Link href="/auth/login" className="btn-secondary flex-1">შესვლა</Link>
                <Link href="/auth/register" className="btn-primary flex-1">რეგისტრაცია</Link>
              </div>
            ) : (
              <div className="flex gap-2 pt-2 border-t border-ink-100 mt-2">
                <Link href={dashHref} className="btn-secondary flex-1">დაშბორდი</Link>
                <button onClick={() => signOut({ callbackUrl: '/' })} className="btn-ghost flex-1 text-red-700">გასვლა</button>
              </div>
            )}
          </div>
        </div>
      )}
    </header>
  );
}
