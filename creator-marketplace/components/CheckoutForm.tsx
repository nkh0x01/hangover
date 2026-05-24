'use client';

import Link from 'next/link';
import { useMemo, useState } from 'react';
import type { Service } from '@/lib/types';
import { formatGEL } from '@/lib/i18n';
import { PLATFORM_COMMISSION_PERCENT } from '@/lib/data/orders';
import { IconCheck, IconShield } from '@/components/Icons';
import { scanContactInfo } from '@/lib/contact-guard';

interface Props {
  service: Service;
  creator: { name: string; nameKa: string; avatar: string; rating: number; responseTimeHours: number };
}

const PAY_METHODS = [
  { id: 'card', titleKa: 'საბანკო ბარათი', sub: 'Visa, Mastercard · Bank of Georgia', recommended: true },
  { id: 'transfer', titleKa: 'საბანკო გადარიცხვა', sub: 'ფაქტურა იურიდიული პირებისთვის' },
  { id: 'quote', titleKa: 'მოითხოვე შეთავაზება', sub: 'დიდი კამპანიებისთვის' },
];

export function CheckoutForm({ service, creator }: Props) {
  const [brief, setBrief] = useState('');
  const [clientName, setClientName] = useState('');
  const [clientEmail, setClientEmail] = useState('');
  const [deadline, setDeadline] = useState('');
  const [priority, setPriority] = useState<'std' | 'rush'>('std');
  const [selectedAddons, setSelectedAddons] = useState<string[]>([]);
  const [pay, setPay] = useState('card');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const briefScan = scanContactInfo(brief);
  const briefHasViolation = briefScan.hasViolations;

  function toggleAddon(title: string) {
    setSelectedAddons((cur) =>
      cur.includes(title) ? cur.filter((t) => t !== title) : [...cur, title],
    );
  }

  const addonsAmount = useMemo(
    () =>
      (service.addons ?? [])
        .filter((a) => selectedAddons.includes(a.titleKa))
        .reduce((s, a) => s + a.price, 0),
    [service.addons, selectedAddons],
  );
  const rushFee = priority === 'rush' ? Math.round(service.price * 0.25) : 0;
  const total = service.price + addonsAmount + rushFee;
  const commission = Math.round((total * PLATFORM_COMMISSION_PERCENT) / 100);
  const payout = total - commission;

  const canPay =
    !submitting &&
    clientName.trim().length >= 3 &&
    brief.trim().length >= 20 &&
    !briefHasViolation &&
    (pay === 'card' || pay === 'transfer' || pay === 'quote');

  async function onPay() {
    setError(null);
    if (pay === 'quote') {
      // For quote requests we'd open a messenger thread, not charge a card.
      window.location.href = `/messages?service=${service.id}&quote=1`;
      return;
    }
    setSubmitting(true);
    try {
      const res = await fetch('/api/payments/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          serviceId: service.id,
          addons: selectedAddons,
          clientName: clientName.trim(),
          clientEmail: clientEmail.trim() || undefined,
          brief: brief.trim(),
          deadline,
          priority,
          payMethod: pay,
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data?.error ?? 'payment creation failed');
      }
      // Redirect to the (mock or real) hosted page.
      window.location.href = data.redirectUrl;
    } catch (e) {
      setError(e instanceof Error ? e.message : 'უცნობი შეცდომა');
      setSubmitting(false);
    }
  }

  return (
    <div className="grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-8 mt-8">
      <div className="space-y-6">
        <Step number={1} title="აირჩიე პაკეტი">
          <div className="rounded-xl border-2 border-brand-500 bg-brand-50 p-4">
            <div className="flex justify-between items-start gap-3">
              <div className="flex-1">
                <p className="font-semibold text-ink-900">{service.titleKa}</p>
                <p className="text-sm muted mt-1 line-clamp-2">{service.descriptionKa}</p>
              </div>
              <span className="font-bold text-ink-900">{formatGEL(service.price)}</span>
            </div>
          </div>
          {service.addons.length > 0 && (
            <div className="mt-4 space-y-2">
              <p className="text-xs muted">დამატებები:</p>
              {service.addons.map((a) => (
                <label
                  key={a.titleKa}
                  className="flex items-center justify-between rounded-xl border border-ink-200 p-3 cursor-pointer hover:bg-ink-50"
                >
                  <span className="flex items-center gap-2 text-sm text-ink-700">
                    <input
                      type="checkbox"
                      checked={selectedAddons.includes(a.titleKa)}
                      onChange={() => toggleAddon(a.titleKa)}
                      className="accent-brand-600 h-4 w-4"
                    />
                    {a.titleKa}
                  </span>
                  <span className="font-semibold text-sm">+ {formatGEL(a.price)}</span>
                </label>
              ))}
            </div>
          )}
        </Step>

        <Step number={2} title="კამპანიის ბრიფი">
          <textarea
            className={`input min-h-[160px] ${briefHasViolation ? 'border-amber-400 focus:ring-amber-100 focus:border-amber-500' : ''}`}
            placeholder="აღწერე პროდუქტი, სამიზნე აუდიტორია, ტონი, ძირითადი მესიჯი, რეფერენსები..."
            value={brief}
            onChange={(e) => setBrief(e.target.value)}
          />
          {briefHasViolation && (
            <p className="text-[11px] text-amber-700 mt-1">
              ⚠ ბრიფი შეიცავს პირად საკონტაქტო ინფორმაციას (ნომერი / ელ-ფოსტა / Telegram). გადახდები მხოლოდ პლატფორმაზე — წაშალე და თავიდან სცადე.
            </p>
          )}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
            <input
              className="input"
              placeholder="შენი / ბრენდის სახელი *"
              value={clientName}
              onChange={(e) => setClientName(e.target.value)}
            />
            <input
              className="input"
              type="email"
              placeholder="ელ-ფოსტა (ქვითრისთვის)"
              value={clientEmail}
              onChange={(e) => setClientEmail(e.target.value)}
            />
          </div>
        </Step>

        <Step number={3} title="პროდუქტის ფაილები / ბმულები">
          <div className="rounded-xl border-2 border-dashed border-ink-300 bg-ink-50 p-6 text-center">
            <p className="text-sm muted">
              გადმოიტანე ფაილები ან <span className="link cursor-pointer">აირჩიე</span>
            </p>
            <p className="text-xs muted mt-1">PDF, PNG, JPG, MP4 — მაქს. 50MB</p>
          </div>
        </Step>

        <Step number={4} title="ვადა">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="label">სასურველი მიწოდების თარიღი</label>
              <input
                className="input"
                type="date"
                value={deadline}
                onChange={(e) => setDeadline(e.target.value)}
              />
            </div>
            <div>
              <label className="label">პრიორიტეტი</label>
              <select
                className="input"
                value={priority}
                onChange={(e) => setPriority(e.target.value as 'std' | 'rush')}
              >
                <option value="std">სტანდარტული</option>
                <option value="rush">სწრაფი მიწოდება (+25%)</option>
              </select>
            </div>
          </div>
        </Step>

        <Step number={5} title="გადახდის მეთოდი">
          <div className="space-y-2">
            {PAY_METHODS.map((m) => (
              <label
                key={m.id}
                className={`flex items-center gap-3 rounded-xl border-2 p-4 cursor-pointer ${
                  pay === m.id
                    ? 'border-brand-500 bg-brand-50'
                    : 'border-ink-200 hover:bg-ink-50'
                }`}
              >
                <input
                  type="radio"
                  name="pay"
                  checked={pay === m.id}
                  onChange={() => setPay(m.id)}
                  className="accent-brand-600"
                />
                <div className="flex-1">
                  <p className="font-semibold text-ink-900 text-sm">{m.titleKa}</p>
                  <p className="text-xs muted">{m.sub}</p>
                </div>
                {m.recommended && <span className="text-xs muted">★ რეკომენდირებული</span>}
              </label>
            ))}
          </div>

          <div className="mt-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 flex items-start gap-3">
            <IconShield className="text-emerald-700 shrink-0 mt-0.5" />
            <p className="text-sm text-emerald-900">
              თანხა ინახება Escrow-ში — კრეატორი იღებს ანაზღაურებას მხოლოდ მას შემდეგ, რაც დაადასტურებ კონტენტს.
            </p>
          </div>

          {error && (
            <div className="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-900">
              ❌ {error}
            </div>
          )}
        </Step>
      </div>

      {/* Order summary sidebar */}
      <aside className="lg:sticky lg:top-20 lg:self-start">
        <div className="card p-6">
          <h3 className="font-bold text-ink-900 text-lg mb-4">შეჯამება</h3>

          <div className="flex items-center gap-3 mb-4 pb-4 border-b border-ink-100">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={creator.avatar} alt="" className="h-10 w-10 rounded-full" />
            <div className="flex-1 min-w-0">
              <p className="font-semibold text-sm truncate">{creator.nameKa}</p>
              <p className="text-xs muted">
                ★ {creator.rating} · {creator.responseTimeHours} სთ პასუხი
              </p>
            </div>
          </div>

          <div className="space-y-2 text-sm">
            <div className="flex justify-between">
              <span className="text-ink-700">საბაზისო ფასი</span>
              <span className="font-medium">{formatGEL(service.price)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-ink-700">დამატებები</span>
              <span className={addonsAmount ? 'font-medium' : 'muted'}>
                {addonsAmount ? `+ ${formatGEL(addonsAmount)}` : '0 ₾'}
              </span>
            </div>
            {rushFee > 0 && (
              <div className="flex justify-between">
                <span className="text-ink-700">სწრაფი მიწოდება</span>
                <span className="font-medium">+ {formatGEL(rushFee)}</span>
              </div>
            )}
            <div className="flex justify-between text-xs muted pt-2 border-t border-ink-100">
              <span>აქედან კრეატორი მიიღებს</span>
              <span>{formatGEL(payout)}</span>
            </div>
            <div className="flex justify-between text-xs muted">
              <span>პლატფორმის საკომისიო ({PLATFORM_COMMISSION_PERCENT}%)</span>
              <span>{formatGEL(commission)}</span>
            </div>
          </div>

          <div className="flex justify-between items-center mt-5 pt-5 border-t border-ink-200">
            <span className="font-bold text-ink-900">ჯამი</span>
            <span className="text-2xl font-extrabold text-ink-900">{formatGEL(total)}</span>
          </div>

          <button
            type="button"
            onClick={onPay}
            disabled={!canPay}
            className="btn-primary w-full mt-5 text-base py-3 disabled:opacity-50"
          >
            {submitting
              ? 'მუშავდება...'
              : pay === 'quote'
                ? 'შეთავაზების მოთხოვნა'
                : `გადახდა ${formatGEL(total)}`}
          </button>
          <p className="text-xs muted text-center mt-3 flex items-center justify-center gap-1">
            <IconCheck className="text-emerald-600" /> SSL უსაფრთხო გადახდა · 3DS
          </p>
          <p className="text-[11px] muted text-center mt-1">
            BOG e-commerce — გადასცემს ბანკის გვერდს
          </p>

          {!clientName && (
            <p className="text-[11px] text-amber-700 mt-3 text-center">
              შეიყვანე სახელი ბრიფში სანამ გადახდას დაიწყებ
            </p>
          )}
          {brief.trim().length > 0 && brief.trim().length < 20 && (
            <p className="text-[11px] text-amber-700 mt-1 text-center">
              ბრიფი ძალიან მოკლეა (მინ. 20 სიმბოლო)
            </p>
          )}
        </div>

        <p className="text-xs muted text-center mt-3">
          <Link href="/faq" className="link">როგორ მუშაობს Escrow?</Link>
        </p>
      </aside>
    </div>
  );
}

function Step({
  number,
  title,
  children,
}: {
  number: number;
  title: string;
  children: React.ReactNode;
}) {
  return (
    <div className="card p-6">
      <div className="flex items-center gap-3 mb-4">
        <span className="h-8 w-8 rounded-full bg-brand-600 text-white text-sm font-bold flex items-center justify-center">
          {number}
        </span>
        <h3 className="font-bold text-ink-900">{title}</h3>
      </div>
      {children}
    </div>
  );
}
