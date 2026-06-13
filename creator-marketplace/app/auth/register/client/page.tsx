'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';

const INDUSTRIES = [
  'E-commerce / DTC',
  'სილამაზე და კოსმეტიკა',
  'მოდა',
  'რესტორანი / HoReCa',
  'ტექნოლოგია / SaaS',
  'ფინანსები / ბანკი',
  'მოგზაურობა / სასტუმრო',
  'სპორტი / ფიტნესი',
  'ჯანდაცვა',
  'განათლება',
  'სხვა',
];

export default function ClientRegisterPage() {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    const f = new FormData(e.currentTarget);
    const payload = {
      name: String(f.get('name') ?? '').trim(),
      companyName: String(f.get('companyName') ?? '').trim(),
      email: String(f.get('email') ?? '').trim(),
      phone: String(f.get('phone') ?? '').trim(),
      industry: String(f.get('industry') ?? ''),
      password: String(f.get('password') ?? ''),
    };
    try {
      const res = await fetch('/api/register/client', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error ?? 'რეგისტრაცია ვერ მოხერხდა');
      if (typeof window !== 'undefined') {
        window.sessionStorage.setItem('pendingUserId', data.userId);
      }
      router.push(`/auth/register/contract?type=client&uid=${data.userId}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'უცნობი შეცდომა');
      setBusy(false);
    }
  }

  return (
    <section className="container-page py-12 max-w-2xl">
      <div className="text-center mb-10">
        <span className="chip-brand mb-3">ბიზნეს რეგისტრაცია</span>
        <h1 className="text-3xl font-extrabold tracking-tight text-ink-900">დაიწყე უფასოდ</h1>
        <p className="muted mt-2">შექმენი ანგარიში და შეუკვეთე პირველი კონტენტი 5 წუთში.</p>
      </div>

      <form onSubmit={onSubmit} className="card p-6 sm:p-8 space-y-5">
        {error && (
          <div className="rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-900">
            ❌ {error}
          </div>
        )}
        <div>
          <label className="label">სახელი *</label>
          <input name="name" className="input" placeholder="გვარი სახელი" required />
        </div>
        <div>
          <label className="label">კომპანიის სახელი (არჩევითი)</label>
          <input name="companyName" className="input" placeholder="მაგ.: Mera Cosmetics" />
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="label">ელ-ფოსტა *</label>
            <input name="email" className="input" type="email" placeholder="you@brand.ge" required />
          </div>
          <div>
            <label className="label">ტელეფონის ნომერი *</label>
            <input name="phone" className="input" placeholder="+995 5XX XX XX XX" />
          </div>
        </div>
        <div>
          <label className="label">ინდუსტრია *</label>
          <select name="industry" className="input">
            {INDUSTRIES.map((i) => (
              <option key={i}>{i}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="label">პაროლი *</label>
          <input name="password" className="input" type="password" placeholder="მინ. 8 სიმბოლო" required minLength={8} />
        </div>

        <label className="flex items-start gap-2 text-sm text-ink-700">
          <input type="checkbox" className="accent-brand-600 h-4 w-4 mt-1" required />
          ვეთანხმები{' '}
          <Link href="#" className="link">წესებსა და პირობებს</Link>.
        </label>

        <button type="submit" disabled={busy} className="btn-primary w-full py-3 text-base">
          {busy ? 'მუშავდება...' : 'შემდეგი — ხელშეკრულება'}
        </button>
        <p className="text-xs muted text-center -mt-2">
          რეგისტრაცია მთავრდება ხელშეკრულების ხელის მოწერით.
        </p>
        <p className="text-sm muted text-center">
          უკვე გაქვს ანგარიში?{' '}
          <Link href="/auth/login" className="link">შესვლა</Link>
        </p>
      </form>
    </section>
  );
}
