'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { categories } from '@/lib/data/categories';

const PLATFORMS = ['tiktok', 'instagram', 'youtube', 'facebook', 'linkedin'] as const;
const PLATFORM_LABELS: Record<string, string> = {
  tiktok: 'TikTok',
  instagram: 'Instagram',
  youtube: 'YouTube',
  facebook: 'Facebook',
  linkedin: 'LinkedIn',
};

export default function CreatorRegisterPage() {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [platforms, setPlatforms] = useState<string[]>(['instagram']);

  function togglePlatform(p: string) {
    setPlatforms((cur) => (cur.includes(p) ? cur.filter((x) => x !== p) : [...cur, p]));
  }

  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    const f = new FormData(e.currentTarget);
    const payload = {
      name: String(f.get('name') ?? '').trim(),
      email: String(f.get('email') ?? '').trim(),
      phone: String(f.get('phone') ?? '').trim(),
      city: String(f.get('city') ?? ''),
      category: String(f.get('category') ?? 'ugc'),
      bio: String(f.get('bio') ?? ''),
      startingPrice: Number(f.get('startingPrice') ?? 0),
      responseTimeHours: Number(f.get('responseTimeHours') ?? 4),
      password: String(f.get('password') ?? ''),
      platforms,
      socialLinks: {
        tiktok: String(f.get('tiktok') ?? ''),
        instagram: String(f.get('instagram') ?? ''),
        youtube: String(f.get('youtube') ?? ''),
        other: String(f.get('other') ?? ''),
      },
    };
    try {
      const res = await fetch('/api/register/creator', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error ?? 'რეგისტრაცია ვერ მოხერხდა');
      // Persist user id for the contract step.
      if (typeof window !== 'undefined') {
        window.sessionStorage.setItem('pendingUserId', data.userId);
      }
      router.push(`/auth/register/contract?type=creator&uid=${data.userId}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'უცნობი შეცდომა');
      setBusy(false);
    }
  }

  return (
    <section className="container-page py-12 max-w-3xl">
      <div className="text-center mb-10">
        <span className="chip-brand mb-3">კრეატორის რეგისტრაცია</span>
        <h1 className="text-3xl font-extrabold tracking-tight text-ink-900">გახდი კრეატორი</h1>
        <p className="muted mt-2">დააარსე შენი პროფესიული პროფილი 10 წუთში.</p>
      </div>

      <form onSubmit={onSubmit} className="card p-6 sm:p-8 space-y-6">
        {error && (
          <div className="rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-900">
            ❌ {error}
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="label">სრული სახელი *</label>
            <input name="name" className="input" placeholder="გვარი სახელი" required />
          </div>
          <div>
            <label className="label">ელ-ფოსტა *</label>
            <input name="email" className="input" type="email" placeholder="you@example.com" required />
          </div>
          <div>
            <label className="label">ტელეფონის ნომერი *</label>
            <input name="phone" className="input" placeholder="+995 5XX XX XX XX" />
          </div>
          <div>
            <label className="label">ქალაქი *</label>
            <select name="city" className="input">
              <option>თბილისი</option>
              <option>ბათუმი</option>
              <option>ქუთაისი</option>
              <option>რუსთავი</option>
              <option>გორი</option>
              <option>ზუგდიდი</option>
              <option>ფოთი</option>
            </select>
          </div>
        </div>

        <div>
          <label className="label">კატეგორია / ნიშა *</label>
          <select name="category" className="input">
            {categories.map((c) => (
              <option key={c.id} value={c.id}>{c.emoji} {c.ka}</option>
            ))}
          </select>
        </div>

        <div>
          <label className="label">ძირითადი პლატფორმები *</label>
          <div className="flex flex-wrap gap-2">
            {PLATFORMS.map((p) => (
              <label
                key={p}
                className={`inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-medium cursor-pointer ${
                  platforms.includes(p)
                    ? 'border-brand-500 bg-brand-50 text-brand-700'
                    : 'border-ink-200 text-ink-700 hover:bg-ink-50'
                }`}
              >
                <input
                  type="checkbox"
                  className="accent-brand-600 h-4 w-4"
                  checked={platforms.includes(p)}
                  onChange={() => togglePlatform(p)}
                />
                {PLATFORM_LABELS[p]}
              </label>
            ))}
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="label">TikTok ბმული</label>
            <input name="tiktok" className="input" placeholder="https://tiktok.com/@yourname" />
          </div>
          <div>
            <label className="label">Instagram ბმული</label>
            <input name="instagram" className="input" placeholder="https://instagram.com/yourname" />
          </div>
          <div>
            <label className="label">YouTube ბმული</label>
            <input name="youtube" className="input" placeholder="https://youtube.com/@yourname" />
          </div>
          <div>
            <label className="label">სხვა ბმული</label>
            <input name="other" className="input" placeholder="LinkedIn / Facebook" />
          </div>
        </div>

        <div>
          <label className="label">მოკლე ბიო *</label>
          <textarea name="bio" className="input min-h-[100px]" placeholder="რას აკეთებ, რა გამოცდილება გაქვს, რა ბრენდებთან გიმუშავია..." required />
          <p className="text-[11px] muted mt-1">⚠ ნუ წერ ნომერს / ელ-ფოსტას / Telegram — სისტემა აღმოაჩენს და ვერ შეინახავ.</p>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="label">საწყისი ფასი (₾) *</label>
            <input name="startingPrice" className="input" type="number" min={50} placeholder="350" required />
          </div>
          <div>
            <label className="label">საშ. პასუხის დრო (სთ)</label>
            <input name="responseTimeHours" className="input" type="number" min={1} placeholder="4" />
          </div>
        </div>

        <div>
          <label className="label">პაროლი *</label>
          <input name="password" className="input" type="password" placeholder="მინ. 8 სიმბოლო" required minLength={8} />
        </div>

        <label className="flex items-start gap-2 text-sm text-ink-700">
          <input type="checkbox" className="accent-brand-600 h-4 w-4 mt-1" required />
          ვადასტურებ, რომ ვეთანხმები{' '}
          <Link href="#" className="link">წესებსა და პირობებს</Link>.
        </label>

        <div className="flex flex-col sm:flex-row gap-3">
          <button type="submit" disabled={busy} className="btn-primary flex-1 py-3 text-base">
            {busy ? 'მუშავდება...' : 'შემდეგი — ხელშეკრულება'}
          </button>
          <Link href="/auth/login" className="btn-secondary flex-1 py-3 text-base text-center">
            უკვე მაქვს ანგარიში
          </Link>
        </div>

        <p className="text-xs muted text-center">
          რეგისტრაცია მთავრდება ხელშეკრულების ხელის მოწერით. შემდეგ შენი პროფილი გადადის ადმინისტრაციის დასადასტურებლად.
        </p>
      </form>
    </section>
  );
}
