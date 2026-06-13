'use client';

import { useEffect, useRef, useState } from 'react';
import { WatermarkedMedia } from './WatermarkedMedia';
import { IconCheck, IconShield } from './Icons';

interface UploadedItem {
  id: string;
  type: 'image' | 'video';
  previewUrl: string;
  title: string;
  watermarked: boolean;
}

// Uploads images/videos to /api/uploads. Images come back server-watermarked
// (sharp overlay); videos are stored as-is and the visible <WatermarkedMedia>
// overlay covers them in the preview.
export function DemoUpload() {
  const fileRef = useRef<HTMLInputElement>(null);
  const [items, setItems] = useState<UploadedItem[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    // Hydrate from server: read the current user's portfolio items.
    fetch('/api/portfolio/me')
      .then((r) => (r.ok ? r.json() : { items: [] }))
      .then((d) =>
        setItems(
          (d.items ?? []).map(
            (i: { id: string; type: string; thumbnail: string; titleKa: string }) => ({
              id: i.id,
              type: (i.type as 'image' | 'video') ?? 'image',
              previewUrl: i.thumbnail,
              title: i.titleKa,
              watermarked: true,
            }),
          ),
        ),
      )
      .catch(() => {});
  }, []);

  async function onChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    setBusy(true);
    setError(null);
    try {
      const fd = new FormData();
      fd.append('file', file);
      fd.append('purpose', 'demo');
      const res = await fetch('/api/uploads', { method: 'POST', body: fd });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error ?? 'upload failed');
      setItems((cur) => [
        {
          id: data.id ?? `${Date.now()}`,
          type: file.type.startsWith('image/') ? 'image' : 'video',
          previewUrl: data.previewUrl,
          title: file.name,
          watermarked: data.watermarked,
        },
        ...cur,
      ]);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'უცნობი შეცდომა');
    } finally {
      setBusy(false);
      if (fileRef.current) fileRef.current.value = '';
    }
  }

  return (
    <div className="card p-6">
      <div className="flex items-start gap-3 mb-4">
        <span className="h-10 w-10 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center shrink-0">
          <IconShield />
        </span>
        <div className="flex-1">
          <h3 className="font-bold text-ink-900">დემო ნამუშევრების ატვირთვა</h3>
          <p className="text-xs muted mt-0.5">
            ყველა ფოტო/ვიდეო ავტომატურად მიიღებს{' '}
            <strong className="text-brand-700">კრეატორები.ge</strong> ვოთერმარკს. სუფთა
            (raw) ვერსია ინახება დაცულ საცავში და გადაეცემა კლიენტს მხოლოდ
            გადახდის შემდეგ.
          </p>
        </div>
      </div>

      <label className="rounded-xl border-2 border-dashed border-ink-300 bg-ink-50 p-6 mb-4 text-center block cursor-pointer hover:bg-ink-100 transition">
        <input
          ref={fileRef}
          type="file"
          accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime"
          className="hidden"
          onChange={onChange}
          disabled={busy}
        />
        <p className="text-sm muted">
          {busy ? 'მუშავდება...' : 'დააწექი ფაილის ასარჩევად'}
        </p>
        <p className="text-xs muted mt-1">JPG, PNG, WEBP, MP4, MOV — მაქს. 50MB</p>
      </label>

      {error && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-900 mb-3">
          ❌ {error}
        </div>
      )}

      <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-900 flex items-start gap-2 mb-4">
        <span className="h-5 w-5 mt-0.5 rounded-full bg-emerald-600 text-white inline-flex items-center justify-center shrink-0">
          <IconCheck />
        </span>
        <span>
          საჯაროდ გამოჩნდება მხოლოდ ვოთერმარკიანი ვერსია. raw ფაილი დაცულია მანამ,
          სანამ კლიენტი არ გადაიხდის შეკვეთას.
        </span>
      </div>

      <p className="text-xs muted mb-2">
        პორტფოლიოში: <strong>{items.length}</strong>{' '}
        {items.length === 1 ? 'ნამუშევარი' : 'ნამუშევარი'}
      </p>
      {items.length > 0 && (
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          {items.slice(0, 9).map((d) => (
            <div key={d.id} className="space-y-1.5">
              <WatermarkedMedia
                src={d.previewUrl}
                alt={d.title}
                type={d.type}
                className="aspect-square rounded-xl bg-ink-100"
              />
              <p className="text-[11px] muted line-clamp-2">{d.title}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
