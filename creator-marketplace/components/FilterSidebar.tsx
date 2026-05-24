'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { useState, useEffect } from 'react';
import { categories } from '@/lib/data/categories';
import { cities } from '@/lib/data/creators';
import { IconFilter } from './Icons';

const PLATFORMS = [
  { id: 'tiktok', label: 'TikTok' },
  { id: 'instagram', label: 'Instagram' },
  { id: 'youtube', label: 'YouTube' },
  { id: 'facebook', label: 'Facebook' },
  { id: 'linkedin', label: 'LinkedIn' },
];

const AUDIENCE_SIZES = [
  { id: 'nano', label: 'ნანო (< 10K)', min: 0, max: 10000 },
  { id: 'micro', label: 'მიკრო (10K – 50K)', min: 10000, max: 50000 },
  { id: 'mid', label: 'საშუალო (50K – 200K)', min: 50000, max: 200000 },
  { id: 'macro', label: 'მაკრო (200K – 1M)', min: 200000, max: 1_000_000 },
  { id: 'mega', label: 'მეგა (> 1M)', min: 1_000_000, max: Infinity },
];

const DELIVERY = [
  { id: '3', label: '3 დღემდე' },
  { id: '7', label: '7 დღემდე' },
  { id: '14', label: '14 დღემდე' },
];

export function FilterSidebar() {
  const router = useRouter();
  const params = useSearchParams();
  const [open, setOpen] = useState(false);

  const [category, setCategory] = useState(params.get('category') ?? '');
  const [platform, setPlatform] = useState(params.get('platform') ?? '');
  const [city, setCity] = useState(params.get('city') ?? '');
  const [audience, setAudience] = useState(params.get('audience') ?? '');
  const [maxPrice, setMaxPrice] = useState(Number(params.get('maxPrice') ?? 3000));
  const [rating, setRating] = useState(Number(params.get('rating') ?? 0));
  const [delivery, setDelivery] = useState(params.get('delivery') ?? '');
  const [verified, setVerified] = useState(params.get('verified') === '1');

  useEffect(() => {
    setCategory(params.get('category') ?? '');
    setPlatform(params.get('platform') ?? '');
    setCity(params.get('city') ?? '');
    setAudience(params.get('audience') ?? '');
    setMaxPrice(Number(params.get('maxPrice') ?? 3000));
    setRating(Number(params.get('rating') ?? 0));
    setDelivery(params.get('delivery') ?? '');
    setVerified(params.get('verified') === '1');
  }, [params]);

  function apply() {
    const next = new URLSearchParams(params.toString());
    function setOrDelete(k: string, v: string | number | boolean) {
      if (!v || v === '0' || v === 0) next.delete(k);
      else next.set(k, String(v));
    }
    setOrDelete('category', category);
    setOrDelete('platform', platform);
    setOrDelete('city', city);
    setOrDelete('audience', audience);
    setOrDelete('maxPrice', maxPrice < 3000 ? maxPrice : 0);
    setOrDelete('rating', rating);
    setOrDelete('delivery', delivery);
    setOrDelete('verified', verified ? '1' : '');
    router.push(`/marketplace?${next.toString()}`);
    setOpen(false);
  }

  function clear() {
    router.push('/marketplace');
    setOpen(false);
  }

  const FilterBody = (
    <div className="space-y-6">
      <div>
        <label className="label">კატეგორია</label>
        <select className="input" value={category} onChange={(e) => setCategory(e.target.value)}>
          <option value="">ყველა კატეგორია</option>
          {categories.map((c) => (
            <option key={c.id} value={c.id}>
              {c.emoji} {c.ka}
            </option>
          ))}
        </select>
      </div>

      <div>
        <label className="label">პლატფორმა</label>
        <div className="flex flex-wrap gap-2">
          {PLATFORMS.map((p) => (
            <button
              key={p.id}
              onClick={() => setPlatform(platform === p.id ? '' : p.id)}
              className={`chip ${platform === p.id ? 'bg-brand-100 text-brand-700' : ''}`}
              type="button"
            >
              {p.label}
            </button>
          ))}
        </div>
      </div>

      <div>
        <label className="label">ქალაქი</label>
        <select className="input" value={city} onChange={(e) => setCity(e.target.value)}>
          <option value="">ყველა ქალაქი</option>
          {cities.map((c) => (
            <option key={c} value={c}>
              {c}
            </option>
          ))}
        </select>
      </div>

      <div>
        <label className="label">ფასი (₾) — მაქს. {maxPrice}</label>
        <input
          type="range"
          min={100}
          max={3000}
          step={50}
          value={maxPrice}
          onChange={(e) => setMaxPrice(Number(e.target.value))}
          className="w-full accent-brand-600"
        />
        <div className="flex justify-between text-xs muted mt-1">
          <span>100 ₾</span>
          <span>3000 ₾+</span>
        </div>
      </div>

      <div>
        <label className="label">შეფასება</label>
        <div className="flex gap-2">
          {[0, 4, 4.5, 4.8].map((r) => (
            <button
              key={r}
              type="button"
              onClick={() => setRating(r)}
              className={`chip ${rating === r ? 'bg-brand-100 text-brand-700' : ''}`}
            >
              {r === 0 ? 'ყველა' : `${r}+ ★`}
            </button>
          ))}
        </div>
      </div>

      <div>
        <label className="label">აუდიტორიის ზომა</label>
        <div className="space-y-2">
          {AUDIENCE_SIZES.map((a) => (
            <label key={a.id} className="flex items-center gap-2 text-sm cursor-pointer">
              <input
                type="radio"
                name="audience"
                checked={audience === a.id}
                onChange={() => setAudience(a.id)}
                className="accent-brand-600"
              />
              {a.label}
            </label>
          ))}
        </div>
      </div>

      <div>
        <label className="label">მიწოდების ვადა</label>
        <div className="flex flex-wrap gap-2">
          {DELIVERY.map((d) => (
            <button
              key={d.id}
              type="button"
              onClick={() => setDelivery(delivery === d.id ? '' : d.id)}
              className={`chip ${delivery === d.id ? 'bg-brand-100 text-brand-700' : ''}`}
            >
              {d.label}
            </button>
          ))}
        </div>
      </div>

      <label className="flex items-center gap-2 cursor-pointer">
        <input
          type="checkbox"
          checked={verified}
          onChange={(e) => setVerified(e.target.checked)}
          className="accent-brand-600 h-4 w-4"
        />
        <span className="text-sm font-medium text-ink-700">მხოლოდ დადასტურებული</span>
      </label>

      <div className="flex gap-2 pt-2">
        <button onClick={clear} className="btn-secondary flex-1" type="button">
          გასუფთავება
        </button>
        <button onClick={apply} className="btn-primary flex-1" type="button">
          გამოყენება
        </button>
      </div>
    </div>
  );

  return (
    <>
      <aside className="hidden lg:block card p-5 sticky top-20 self-start">
        <div className="flex items-center gap-2 mb-4">
          <IconFilter />
          <h3 className="font-semibold text-ink-900">ფილტრები</h3>
        </div>
        {FilterBody}
      </aside>

      <div className="lg:hidden">
        <button onClick={() => setOpen(true)} className="btn-secondary w-full" type="button">
          <IconFilter /> ფილტრები
        </button>
        {open && (
          <div className="fixed inset-0 z-50 bg-black/40 flex" onClick={() => setOpen(false)}>
            <div
              className="ml-auto h-full w-full max-w-md bg-white p-6 overflow-y-auto"
              onClick={(e) => e.stopPropagation()}
            >
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-semibold text-ink-900 text-lg">ფილტრები</h3>
                <button onClick={() => setOpen(false)} className="btn-ghost">
                  ✕
                </button>
              </div>
              {FilterBody}
            </div>
          </div>
        )}
      </div>
    </>
  );
}
