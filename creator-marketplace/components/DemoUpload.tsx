'use client';

import { useState } from 'react';
import { WatermarkedMedia } from './WatermarkedMedia';
import { IconCheck, IconShield } from './Icons';

// Stand-in for the real upload pipeline. In production:
//   1. client sends file to signed S3/MinIO URL
//   2. server-side worker re-encodes video and burns in the watermark via ffmpeg
//      (drawtext "კრეატორები.ge" + diagonal repeating logo)
//   3. images go through sharp with a tiled SVG overlay
//   4. the unwatermarked original is held in private storage and only
//      released to the client after the order is paid and approved.
const SAMPLE_DEMOS = [
  {
    id: 'd1',
    type: 'image' as const,
    src: 'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=800&q=80&auto=format&fit=crop',
    title: 'პროდუქტის ფოტო — Lumi Beauty (DEMO)',
  },
  {
    id: 'd2',
    type: 'image' as const,
    src: 'https://images.unsplash.com/photo-1522335789203-aaa2f6d3f8d3?w=800&q=80&auto=format&fit=crop',
    title: 'UGC ვიდეო — Mera Serum (DEMO)',
  },
  {
    id: 'd3',
    type: 'image' as const,
    src: 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=800&q=80&auto=format&fit=crop',
    title: 'ფეშენ ფოტო — Spring Lookbook (DEMO)',
  },
];

export function DemoUpload() {
  const [demos, setDemos] = useState(SAMPLE_DEMOS);

  function onFakeUpload() {
    setDemos((d) => [
      ...d,
      {
        id: `d-${Date.now()}`,
        type: 'image',
        src: `https://images.unsplash.com/photo-1483985988355-763728e1935b?w=800&q=80&auto=format&fit=crop&r=${Date.now()}`,
        title: `ახალი დემო — ${new Date().toLocaleString('ka-GE')}`,
      },
    ]);
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
            ყველა ვიდეო და ფოტო ავტომატურად მიიღებს{' '}
            <strong className="text-brand-700">კრეატორები.ge</strong> ვოთერმარკს. სუფთა, ორიგინალური
            ვერსია გაიგზავნება მხოლოდ კლიენტთან, როცა ის გადაიხდის შეკვეთას.
          </p>
        </div>
      </div>

      <div className="rounded-xl border-2 border-dashed border-ink-300 bg-ink-50 p-6 mb-4 text-center">
        <p className="text-sm muted">
          გადმოიტანე ფაილები ან{' '}
          <button onClick={onFakeUpload} type="button" className="link">
            აირჩიე
          </button>
        </p>
        <p className="text-xs muted mt-1">
          JPG, PNG, MP4, MOV — მაქს. 200MB. ვოთერმარკი ემატება ავტომატურად.
        </p>
      </div>

      <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-900 flex items-start gap-2 mb-4">
        <span className="h-5 w-5 mt-0.5 rounded-full bg-emerald-600 text-white inline-flex items-center justify-center shrink-0">
          <IconCheck />
        </span>
        <span>
          საჯაროდ გამოჩნდება მხოლოდ ვოთერმარკიანი ვერსია. სუფთა ფაილი (raw) ინახება
          დაცულ საცავში და გადაეცემა კლიენტს მხოლოდ ანაზღაურების შემდეგ.
        </span>
      </div>

      <p className="text-xs muted mb-2">ბოლო ნამუშევრები ({demos.length}):</p>
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
        {demos.map((d) => (
          <div key={d.id} className="space-y-1.5">
            <WatermarkedMedia
              src={d.src}
              alt={d.title}
              type={d.type}
              className="aspect-square rounded-xl bg-ink-100"
            />
            <p className="text-[11px] muted line-clamp-2">{d.title}</p>
          </div>
        ))}
      </div>
    </div>
  );
}
