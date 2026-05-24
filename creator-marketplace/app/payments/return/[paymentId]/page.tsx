import Link from 'next/link';
import { getIntent } from '@/lib/payments';
import { IconCheck, IconShield } from '@/components/Icons';
import { formatGEL } from '@/lib/i18n';

export const dynamic = 'force-dynamic';

export default function PaymentReturnPage({
  params,
  searchParams,
}: {
  params: { paymentId: string };
  searchParams: { status?: string };
}) {
  const intent = getIntent(params.paymentId);
  const ok =
    intent?.status === 'held' ||
    intent?.status === 'released' ||
    searchParams.status === 'success';

  return (
    <section className="container-page py-16 max-w-2xl">
      {ok ? (
        <div className="card p-8 text-center">
          <div className="h-16 w-16 rounded-full bg-emerald-100 text-emerald-700 inline-flex items-center justify-center mx-auto mb-4">
            <IconCheck className="h-8 w-8" />
          </div>
          <h1 className="text-2xl font-extrabold text-ink-900">გადახდა წარმატებულია</h1>
          <p className="muted mt-2">
            თანხა მიღებულია და ინახება პლატფორმის Escrow-ში.
          </p>

          {intent && (
            <div className="rounded-xl bg-ink-50 p-5 mt-6 text-left space-y-2 text-sm">
              <Row label="გადახდის ID" value={intent.id} mono />
              <Row label="შეკვეთის ID" value={intent.orderId} mono />
              <Row label="თანხა" value={formatGEL(intent.amount / 100)} bold />
              <Row label="პროვაიდერი" value={intent.provider.toUpperCase()} />
              <Row label="სტატუსი" value={statusKa(intent.status)} />
            </div>
          )}

          <div className="rounded-xl bg-emerald-50 border border-emerald-200 p-4 mt-5 flex items-start gap-3 text-left">
            <IconShield className="text-emerald-700 shrink-0 mt-0.5" />
            <p className="text-sm text-emerald-900">
              თანხა გადაირიცხება კრეატორთან მხოლოდ მას შემდეგ, რაც დაადასტურებ მიწოდებას.
              დასრულებამდე ფული ინახება პლატფორმის უსაფრთხო escrow ანგარიშზე.
            </p>
          </div>

          <div className="flex flex-col sm:flex-row gap-2 justify-center mt-6">
            <Link href="/dashboard/client" className="btn-primary">
              გადადი დაშბორდზე
            </Link>
            <Link href="/messages" className="btn-secondary">
              დაუკავშირდი კრეატორს
            </Link>
          </div>
        </div>
      ) : (
        <div className="card p-8 text-center">
          <div className="h-16 w-16 rounded-full bg-red-100 text-red-700 inline-flex items-center justify-center mx-auto mb-4 text-3xl">
            ✕
          </div>
          <h1 className="text-2xl font-extrabold text-ink-900">გადახდა ვერ მოხერხდა</h1>
          <p className="muted mt-2">
            ბანკმა უარყო ტრანზაქცია ან გადახდა გაუქმდა. თანხა შენი ანგარიშიდან არ ჩამოიჭრება.
          </p>
          <div className="flex justify-center gap-2 mt-6">
            <Link href="/marketplace" className="btn-secondary">დაბრუნება მარკეტფლეისზე</Link>
            <Link href="/dashboard/client" className="btn-primary">დაშბორდი</Link>
          </div>
        </div>
      )}
    </section>
  );
}

function Row({ label, value, mono, bold }: { label: string; value: string; mono?: boolean; bold?: boolean }) {
  return (
    <div className="flex justify-between gap-3">
      <span className="muted">{label}</span>
      <span className={`${mono ? 'font-mono text-xs' : ''} ${bold ? 'font-bold' : ''} text-ink-900`}>
        {value}
      </span>
    </div>
  );
}

function statusKa(s: string): string {
  switch (s) {
    case 'created': return 'შექმნილია';
    case 'processing': return 'მუშავდება';
    case 'held': return 'Escrow-ში — ელოდება მიწოდებას';
    case 'released': return 'კრეატორთან გადარიცხულია';
    case 'refunded': return 'დაბრუნებულია';
    case 'failed': return 'უარყოფილია';
    default: return s;
  }
}
