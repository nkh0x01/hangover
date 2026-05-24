'use client';

import { useState } from 'react';

export function ReleaseButton({
  paymentId,
  className = 'btn-secondary text-xs',
}: {
  paymentId: string;
  className?: string;
}) {
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  async function onClick() {
    setBusy(true);
    setErr(null);
    try {
      const res = await fetch('/api/payments/release', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ paymentId }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error ?? 'release failed');
      setDone(true);
    } catch (e) {
      setErr(e instanceof Error ? e.message : 'უცნობი შეცდომა');
    } finally {
      setBusy(false);
    }
  }

  if (done) return <span className="chip-green">✓ გადარიცხულია</span>;
  return (
    <>
      <button onClick={onClick} disabled={busy} className={className} type="button">
        {busy ? '...' : 'დაადასტურე და გადარიცხე'}
      </button>
      {err && <span className="text-[11px] text-red-700 ml-1">{err}</span>}
    </>
  );
}
