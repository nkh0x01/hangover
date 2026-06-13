'use client';

import Link from 'next/link';
import { useState, Suspense } from 'react';
import { signIn } from 'next-auth/react';
import { useRouter, useSearchParams } from 'next/navigation';

export default function LoginPage() {
  return (
    <Suspense fallback={<section className="container-page py-16 muted">იტვირთება...</section>}>
      <LoginInner />
    </Suspense>
  );
}

function LoginInner() {
  const router = useRouter();
  const params = useSearchParams();
  const callbackUrl = params.get('callbackUrl') ?? '/dashboard/client';
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    const res = await signIn('credentials', {
      email: email.trim().toLowerCase(),
      password,
      redirect: false,
      callbackUrl,
    });
    setBusy(false);
    if (res?.error) {
      setError('ელ-ფოსტა ან პაროლი არასწორია.');
      return;
    }
    router.push(res?.url ?? callbackUrl);
    router.refresh();
  }

  function fillDemo(role: 'admin' | 'creator' | 'client') {
    if (role === 'admin') {
      setEmail('admin@kreatorebi.ge');
      setPassword('admin1234');
    } else if (role === 'creator') {
      setEmail('nino-beridze@kreatorebi.ge');
      setPassword('creator123');
    } else {
      setEmail('tata@mera.ge');
      setPassword('client123');
    }
  }

  return (
    <section className="container-page py-16 max-w-md">
      <div className="text-center mb-8">
        <h1 className="text-2xl font-extrabold tracking-tight text-ink-900">
          შესვლა ანგარიშში
        </h1>
        <p className="muted mt-1 text-sm">
          არ გაქვს ანგარიში?{' '}
          <Link href="/auth/register" className="link">დარეგისტრირდი</Link>
        </p>
      </div>

      <form onSubmit={onSubmit} className="card p-6 space-y-4">
        {error && (
          <div className="rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-900">
            ❌ {error}
          </div>
        )}
        <div>
          <label className="label">ელ-ფოსტა</label>
          <input
            type="email"
            className="input"
            placeholder="you@example.com"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </div>
        <div>
          <div className="flex items-center justify-between">
            <label className="label !mb-0">პაროლი</label>
            <Link href="#" className="text-xs link">პაროლის აღდგენა</Link>
          </div>
          <input
            type="password"
            className="input mt-1.5"
            placeholder="••••••••"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </div>
        <button type="submit" disabled={busy} className="btn-primary w-full py-3 text-base">
          {busy ? 'შემოწმება...' : 'შესვლა'}
        </button>

        <div className="relative my-2">
          <hr className="border-ink-200" />
          <span className="absolute inset-x-0 -top-2.5 text-center">
            <span className="bg-white px-2 text-xs muted">დემო ანგარიშები</span>
          </span>
        </div>
        <div className="grid grid-cols-3 gap-2">
          <button type="button" onClick={() => fillDemo('admin')} className="btn-secondary text-xs">
            👮 ადმინ
          </button>
          <button type="button" onClick={() => fillDemo('creator')} className="btn-secondary text-xs">
            🎬 კრეატორი
          </button>
          <button type="button" onClick={() => fillDemo('client')} className="btn-secondary text-xs">
            🏢 ბიზნესი
          </button>
        </div>
      </form>
    </section>
  );
}
