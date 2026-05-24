'use client';

import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { Suspense, useState } from 'react';
import { IconCheck, IconShield } from '@/components/Icons';
import {
  AGREEMENT_SUMMARY,
  AGREEMENT_VERSION,
  getAgreementClauses,
  type AgreementType,
} from '@/lib/data/agreements';

export default function ContractPage() {
  return (
    <Suspense fallback={<section className="container-page py-16 muted">იტვირთება...</section>}>
      <ContractInner />
    </Suspense>
  );
}

function ContractInner() {
  const router = useRouter();
  const params = useSearchParams();
  const type: AgreementType =
    (params.get('type') as AgreementType) === 'client' ? 'client' : 'creator';
  const clauses = getAgreementClauses(type);

  const [fullName, setFullName] = useState('');
  const [accept1, setAccept1] = useState(false);
  const [accept2, setAccept2] = useState(false);
  const [accept3, setAccept3] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState(false);

  const canSign =
    fullName.trim().length >= 3 && accept1 && accept2 && accept3 && !submitting;

  async function onSign(e: React.FormEvent) {
    e.preventDefault();
    if (!canSign) return;
    setSubmitting(true);
    // In production: POST /api/agreements with { type, fullName, version, ipAddress, userAgent }
    await new Promise((r) => setTimeout(r, 500));
    setSubmitting(false);
    setDone(true);
    const next = type === 'creator' ? '/dashboard/creator?welcome=1' : '/dashboard/client?welcome=1';
    setTimeout(() => router.push(next), 1200);
  }

  return (
    <section className="container-page py-12 max-w-4xl">
      <div className="text-center mb-8">
        <span className="chip-brand mb-3">
          <IconShield /> სავალდებულო ნაბიჯი — {type === 'creator' ? 'კრეატორი' : 'ბიზნესი'}
        </span>
        <h1 className="text-3xl font-extrabold tracking-tight text-ink-900">
          პლატფორმის ხელშეკრულება
        </h1>
        <p className="muted mt-2">
          რეგისტრაცია დასრულდება მხოლოდ მას შემდეგ, რაც წაიკითხავ და ხელს მოაწერ ხელშეკრულებას.
        </p>
        <p className="text-xs muted mt-1">ვერსია {AGREEMENT_VERSION} · ძალაშია 2026 წლის 1 მაისიდან</p>
      </div>

      {/* Quick summary */}
      <div className="card p-5 mb-6 bg-brand-50/60 border-brand-200">
        <h2 className="font-bold text-ink-900 mb-3">⚡ მოკლედ — რას ეთანხმები</h2>
        <ul className="space-y-2 text-sm">
          {AGREEMENT_SUMMARY.ka.map((s) => (
            <li key={s} className="flex items-start gap-2">
              <span className="h-5 w-5 mt-0.5 rounded-full bg-brand-600 text-white inline-flex items-center justify-center shrink-0">
                <IconCheck />
              </span>
              <span className="text-ink-800">{s}</span>
            </li>
          ))}
        </ul>
      </div>

      {/* Full contract text */}
      <div className="card p-6 sm:p-8">
        <div className="max-h-[480px] overflow-y-auto pr-4 space-y-5 border border-ink-100 rounded-xl p-5 bg-ink-50/40">
          <p className="text-xs muted">
            ეს ხელშეკრულება ფორმდება „{type === 'creator' ? 'კრეატორსა' : 'კლიენტს/ბიზნესს'}" და
            შპს „კრეატორები.ge"-ს (ს/კ 405XXXXXX, თბილისი) შორის. გაცნობის შემდეგ, „ხელის
            მოწერით" შენ ადასტურებ მის ყველა პუნქტს და ეთანხმები ციფრულ ხელმოწერას
            ქართული კანონმდებლობის შესაბამისად.
          </p>
          {clauses.map((c) => (
            <div key={c.id}>
              <h3 className="font-semibold text-ink-900">{c.titleKa}</h3>
              <p className="text-sm text-ink-700 leading-relaxed mt-1">{c.bodyKa}</p>
            </div>
          ))}
        </div>

        {/* Acceptance checkboxes */}
        <form onSubmit={onSign} className="mt-6 space-y-4">
          <label className="flex items-start gap-3 cursor-pointer">
            <input
              type="checkbox"
              checked={accept1}
              onChange={(e) => setAccept1(e.target.checked)}
              className="accent-brand-600 h-4 w-4 mt-1"
            />
            <span className="text-sm text-ink-800">
              წავიკითხე და ვეთანხმები <strong>ხელშეკრულების ყველა პუნქტს</strong> ვერსია {AGREEMENT_VERSION}.
            </span>
          </label>
          <label className="flex items-start gap-3 cursor-pointer">
            <input
              type="checkbox"
              checked={accept2}
              onChange={(e) => setAccept2(e.target.checked)}
              className="accent-brand-600 h-4 w-4 mt-1"
            />
            <span className="text-sm text-ink-800">
              ვადასტურებ <strong>პუნქტ 2-ს (პლატფორმის გვერდის ავლის აკრძალვა)</strong> — არ მივიღებ
              ან არ შევთავაზებ პლატფორმის გარეთ ანგარიშსწორებას.
            </span>
          </label>
          <label className="flex items-start gap-3 cursor-pointer">
            <input
              type="checkbox"
              checked={accept3}
              onChange={(e) => setAccept3(e.target.checked)}
              className="accent-brand-600 h-4 w-4 mt-1"
            />
            <span className="text-sm text-ink-800">
              ვადასტურებ <strong>პუნქტ 4-ს (პლატფორმის 12% საკომისიო)</strong> და თანახმა ვარ მისი
              ავტომატური ჩამოჭრის ჩემი შემოსავლიდან.
            </span>
          </label>

          <div className="pt-2 border-t border-ink-100">
            <label className="label">სრული სახელი (ციფრული ხელმოწერა) *</label>
            <input
              className="input"
              placeholder="გვარი სახელი"
              value={fullName}
              onChange={(e) => setFullName(e.target.value)}
            />
            <p className="text-xs muted mt-1.5">
              ეს ჩაითვლება შენი ხელწერით ხელმოწერად. ხელშეკრულება ინახება IP-მისამართთან, თარიღთან და მოწყობილობასთან ერთად.
            </p>
          </div>

          <div className="flex flex-col sm:flex-row gap-3 pt-2">
            <button type="submit" disabled={!canSign} className="btn-primary flex-1 py-3 text-base">
              {submitting ? 'ხელმოწერა...' : done ? '✓ ხელშეკრულება ხელმოწერილია' : 'ხელის მოწერა და დასრულება'}
            </button>
            <Link href="/auth/register" className="btn-secondary py-3 text-base text-center">
              უარის თქმა
            </Link>
          </div>

          <p className="text-xs muted text-center">
            ხელის მოწერით, ეთანხმები ხელშეკრულების მთლიან ვერსიას{' '}
            <Link href="#" className="link">PDF სახით</Link>.
          </p>
        </form>
      </div>
    </section>
  );
}
