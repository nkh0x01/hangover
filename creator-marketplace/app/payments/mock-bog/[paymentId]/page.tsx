'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';

// Local stand-in for the real BOG e-commerce hosted page. Looks roughly
// like the real one (logo + amount + card form + Pay button) but actually
// POSTs to /api/payments/webhook/bog with { paymentId, status: 'held' }
// and redirects back to /payments/return/[paymentId].

export default function MockBogPage({ params }: { params: { paymentId: string } }) {
  const router = useRouter();
  const [intent, setIntent] = useState<{
    amount: number;
    currency: string;
    metadata?: Record<string, string>;
  } | null>(null);
  const [loading, setLoading] = useState(false);
  const [card, setCard] = useState('4242 4242 4242 4242');

  useEffect(() => {
    fetch(`/api/payments?id=${params.paymentId}`)
      .then((r) => r.json())
      .then((d) => setIntent(d.intent ?? null))
      .catch(() => setIntent(null));
  }, [params.paymentId]);

  async function pay(status: 'held' | 'failed') {
    setLoading(true);
    await fetch('/api/payments/webhook/bog', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ paymentId: params.paymentId, status, eventId: `mock-${Date.now()}` }),
    });
    router.push(`/payments/return/${params.paymentId}?status=${status === 'held' ? 'success' : 'fail'}`);
  }

  const amount = intent ? (intent.amount / 100).toFixed(2) : '...';

  return (
    <section className="min-h-[80vh] flex items-center justify-center bg-ink-50 py-12">
      <div className="card max-w-md w-full mx-4 p-8">
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-2">
            <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-orange-500 text-white font-bold">
              B
            </span>
            <div>
              <p className="font-bold text-ink-900">Bank of Georgia</p>
              <p className="text-[10px] muted">e-commerce · mock checkout (dev)</p>
            </div>
          </div>
          <span className="chip-amber">DEV MODE</span>
        </div>

        <div className="rounded-xl bg-ink-50 p-4 mb-6">
          <p className="text-xs muted">გადასახდელი თანხა</p>
          <p className="text-3xl font-extrabold text-ink-900">
            {amount} <span className="text-lg">{intent?.currency ?? 'GEL'}</span>
          </p>
          {intent?.metadata?.serviceTitleKa && (
            <p className="text-xs muted mt-1 truncate">
              {intent.metadata.serviceTitleKa}
            </p>
          )}
        </div>

        <div className="space-y-3 mb-6">
          <div>
            <label className="label">ბარათის ნომერი</label>
            <input
              className="input font-mono"
              value={card}
              onChange={(e) => setCard(e.target.value)}
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="label">ვადა</label>
              <input className="input font-mono" placeholder="12/28" defaultValue="12/28" />
            </div>
            <div>
              <label className="label">CVV</label>
              <input className="input font-mono" placeholder="•••" defaultValue="123" />
            </div>
          </div>
          <div>
            <label className="label">მფლობელის სახელი</label>
            <input className="input" defaultValue={intent?.metadata?.clientName ?? ''} />
          </div>
        </div>

        <div className="flex gap-2">
          <button
            onClick={() => pay('failed')}
            disabled={loading}
            className="btn-secondary flex-1"
            type="button"
          >
            გაუქმება
          </button>
          <button
            onClick={() => pay('held')}
            disabled={loading}
            className="btn-primary flex-1"
            type="button"
          >
            {loading ? 'მუშავდება...' : `გადახდა ${amount} ₾`}
          </button>
        </div>

        <p className="text-[10px] muted text-center mt-5 leading-relaxed">
          ეს არის dev-ვერსიის სიმულაცია. რეალურ რეჟიმში გადასვლა მოხდება Bank of
          Georgia-ს ნამდვილ 3DS გვერდზე, BOG e-commerce API-ის გავლით.
        </p>
        <p className="text-center mt-3">
          <Link href="/" className="text-xs link">← უკან მთავარ გვერდზე</Link>
        </p>
      </div>
    </section>
  );
}
