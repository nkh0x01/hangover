'use client';

import { useRouter } from 'next/navigation';
import { useState } from 'react';

interface Props {
  orderId: string;
  paymentId?: string | null;
  status: string;
  viewerRole: 'creator' | 'client' | 'admin';
  reviewed?: boolean;
}

export function OrderActions({ orderId, status, viewerRole, reviewed }: Props) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [showSubmit, setShowSubmit] = useState(false);
  const [showReview, setShowReview] = useState(false);
  const [deliverable, setDeliverable] = useState('');
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState('');

  async function transition(to: string, note?: string) {
    setBusy(true);
    setErr(null);
    try {
      const res = await fetch(`/api/orders/${orderId}/transition`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ to, note }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error ?? 'failed');
      router.refresh();
    } catch (e) {
      setErr(e instanceof Error ? e.message : 'უცნობი შეცდომა');
    } finally {
      setBusy(false);
    }
  }

  async function submitDeliverable() {
    if (!deliverable.trim()) return;
    setBusy(true);
    setErr(null);
    try {
      const res = await fetch(`/api/orders/${orderId}/submit`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url: deliverable.trim(), type: 'video' }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error ?? 'failed');
      setShowSubmit(false);
      setDeliverable('');
      router.refresh();
    } catch (e) {
      setErr(e instanceof Error ? e.message : 'უცნობი შეცდომა');
    } finally {
      setBusy(false);
    }
  }

  async function leaveReview() {
    if (comment.trim().length < 5) {
      setErr('კომენტარი ძალიან მოკლეა');
      return;
    }
    setBusy(true);
    setErr(null);
    try {
      const res = await fetch(`/api/orders/${orderId}/review`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ rating, comment: comment.trim() }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error ?? 'failed');
      setShowReview(false);
      router.refresh();
    } catch (e) {
      setErr(e instanceof Error ? e.message : 'უცნობი შეცდომა');
    } finally {
      setBusy(false);
    }
  }

  const actions: React.ReactNode[] = [];

  if (viewerRole === 'creator') {
    if (status === 'NEW' || status === 'AWAITING_CREATOR') {
      actions.push(
        <button key="accept" disabled={busy} onClick={() => transition('IN_PROGRESS', 'მიღებულია')} className="btn-primary">
          ✓ მიღება და დაწყება
        </button>,
        <button key="reject" disabled={busy} onClick={() => transition('CANCELLED', 'უარყოფილია კრეატორის მიერ')} className="btn-secondary">
          უარყოფა
        </button>,
      );
    }
    if (status === 'IN_PROGRESS' || status === 'REVISION_REQUESTED') {
      actions.push(
        <button key="submit" disabled={busy} onClick={() => setShowSubmit(true)} className="btn-primary">
          📤 კონტენტის ჩაბარება
        </button>,
      );
    }
  }

  if (viewerRole === 'client') {
    if (status === 'SUBMITTED') {
      actions.push(
        <button key="approve" disabled={busy} onClick={() => setShowReview(true)} className="btn-primary">
          ✓ მიღება + შეფასება (escrow გაიხსნება)
        </button>,
        <button key="revise" disabled={busy} onClick={() => transition('REVISION_REQUESTED', 'შესწორება მოთხოვნილია')} className="btn-secondary">
          ↻ შესწორების მოთხოვნა
        </button>,
      );
    }
    if (status === 'COMPLETED' && !reviewed) {
      actions.push(
        <button key="review-only" disabled={busy} onClick={() => setShowReview(true)} className="btn-secondary">
          ⭐ შეფასების დატოვება
        </button>,
      );
    }
    if (['NEW', 'AWAITING_CREATOR', 'IN_PROGRESS'].includes(status)) {
      actions.push(
        <button key="cancel" disabled={busy} onClick={() => transition('CANCELLED', 'კლიენტმა გააუქმა')} className="btn-ghost text-red-700">
          გაუქმება
        </button>,
      );
    }
  }

  return (
    <div className="space-y-3">
      {err && (
        <div className="rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-900">
          ❌ {err}
        </div>
      )}

      {actions.length > 0 ? (
        <div className="flex flex-wrap gap-2">{actions}</div>
      ) : (
        <p className="muted text-sm">ამ მომენტში მოქმედება არ არის შესაძლებელი.</p>
      )}

      {showSubmit && (
        <div className="rounded-xl border border-ink-200 p-4 bg-ink-50">
          <p className="font-semibold text-sm mb-2">კონტენტის URL</p>
          <input
            className="input mb-2"
            placeholder="https://drive.google.com/... ან Dropbox / WeTransfer ბმული"
            value={deliverable}
            onChange={(e) => setDeliverable(e.target.value)}
          />
          <p className="text-[11px] muted mb-3">
            ⚠ ფაილს დაამატე ვოთერმარკი — ფინალურ (unwatermarked) ვერსიას
            კლიენტი მიიღებს მხოლოდ მისი დადასტურების შემდეგ.
          </p>
          <div className="flex gap-2">
            <button onClick={submitDeliverable} disabled={busy || !deliverable.trim()} className="btn-primary">
              ჩაბარება
            </button>
            <button onClick={() => setShowSubmit(false)} className="btn-secondary">გაუქმება</button>
          </div>
        </div>
      )}

      {showReview && (
        <div className="rounded-xl border border-ink-200 p-4 bg-ink-50">
          <p className="font-semibold text-sm mb-2">შენი შეფასება</p>
          <div className="flex gap-1 mb-3">
            {[1, 2, 3, 4, 5].map((n) => (
              <button
                key={n}
                onClick={() => setRating(n)}
                className={`text-2xl ${n <= rating ? 'text-amber-500' : 'text-ink-200'}`}
                type="button"
                aria-label={`${n} stars`}
              >
                ★
              </button>
            ))}
          </div>
          <textarea
            className="input min-h-[100px]"
            placeholder="დაწერე შენი გამოცდილება..."
            value={comment}
            onChange={(e) => setComment(e.target.value)}
          />
          <div className="flex gap-2 mt-3">
            <button onClick={leaveReview} disabled={busy} className="btn-primary">
              დატოვება და escrow-ის გახსნა
            </button>
            <button onClick={() => setShowReview(false)} className="btn-secondary">გაუქმება</button>
          </div>
        </div>
      )}
    </div>
  );
}
