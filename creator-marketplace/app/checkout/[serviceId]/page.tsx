import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getService, services } from '@/lib/data/services';
import { getCreatorById } from '@/lib/data/creators';
import { formatGEL } from '@/lib/i18n';
import { PLATFORM_COMMISSION_PERCENT } from '@/lib/data/orders';
import { IconCheck, IconShield } from '@/components/Icons';

export function generateStaticParams() {
  return services.map((s) => ({ serviceId: s.id }));
}

export default function CheckoutPage({ params }: { params: { serviceId: string } }) {
  const service = getService(params.serviceId);
  if (!service) return notFound();
  const creator = getCreatorById(service.creatorId);
  if (!creator) return notFound();

  const commission = Math.round((service.price * PLATFORM_COMMISSION_PERCENT) / 100);
  const total = service.price; // client pays full, commission deducted from creator
  const payout = service.price - commission;

  return (
    <section className="container-page py-10">
      <nav className="text-xs muted mb-4 flex items-center gap-2">
        <Link href={`/service/${service.id}`} className="hover:text-ink-900">← უკან სერვისზე</Link>
      </nav>

      <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink-900 mb-2">
        შეკვეთის დადება
      </h1>
      <p className="muted">
        4 მარტივი ნაბიჯი — ბრიფი, ფაილები, ვადა, გადახდა.
      </p>

      <div className="grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-8 mt-8">
        <form className="space-y-6">
          {/* Step 1: package */}
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
                {service.addons.map((a, i) => (
                  <label key={i} className="flex items-center justify-between rounded-xl border border-ink-200 p-3 cursor-pointer hover:bg-ink-50">
                    <span className="flex items-center gap-2 text-sm text-ink-700">
                      <input type="checkbox" className="accent-brand-600 h-4 w-4" />
                      {a.titleKa}
                    </span>
                    <span className="font-semibold text-sm">+ {formatGEL(a.price)}</span>
                  </label>
                ))}
              </div>
            )}
          </Step>

          {/* Step 2: brief */}
          <Step number={2} title="კამპანიის ბრიფი">
            <textarea
              className="input min-h-[160px]"
              placeholder="აღწერე პროდუქტი, სამიზნე აუდიტორია, ტონი, ძირითადი მესიჯი, რეფერენსები..."
            />
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
              <input className="input" placeholder="ბრენდის სახელი" />
              <input className="input" placeholder="ვებგვერდი / Instagram" />
            </div>
          </Step>

          {/* Step 3: files */}
          <Step number={3} title="პროდუქტის ფაილები / ბმულები">
            <div className="rounded-xl border-2 border-dashed border-ink-300 bg-ink-50 p-6 text-center">
              <p className="text-sm muted">
                გადმოიტანე ფაილები ან{' '}
                <span className="link cursor-pointer">აირჩიე</span>
              </p>
              <p className="text-xs muted mt-1">PDF, PNG, JPG, MP4 — მაქს. 50MB</p>
            </div>
          </Step>

          {/* Step 4: deadline */}
          <Step number={4} title="ვადა">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className="label">სასურველი მიწოდების თარიღი</label>
                <input className="input" type="date" />
              </div>
              <div>
                <label className="label">პრიორიტეტი</label>
                <select className="input">
                  <option>სტანდარტული</option>
                  <option>სწრაფი მიწოდება (+25%)</option>
                </select>
              </div>
            </div>
          </Step>

          {/* Step 5: payment */}
          <Step number={5} title="გადახდის მეთოდი">
            <div className="space-y-2">
              <label className="flex items-center gap-3 rounded-xl border-2 border-brand-500 bg-brand-50 p-4 cursor-pointer">
                <input type="radio" name="pay" defaultChecked className="accent-brand-600" />
                <div className="flex-1">
                  <p className="font-semibold text-ink-900 text-sm">საბანკო ბარათი</p>
                  <p className="text-xs muted">Visa, Mastercard — Bank of Georgia / TBC</p>
                </div>
                <span className="text-xs muted">★ რეკომენდირებული</span>
              </label>
              <label className="flex items-center gap-3 rounded-xl border border-ink-200 p-4 cursor-pointer hover:bg-ink-50">
                <input type="radio" name="pay" className="accent-brand-600" />
                <div className="flex-1">
                  <p className="font-semibold text-ink-900 text-sm">საბანკო გადარიცხვა</p>
                  <p className="text-xs muted">ანგარიშის ფაქტურა იურიდიული პირებისთვის</p>
                </div>
              </label>
              <label className="flex items-center gap-3 rounded-xl border border-ink-200 p-4 cursor-pointer hover:bg-ink-50">
                <input type="radio" name="pay" className="accent-brand-600" />
                <div className="flex-1">
                  <p className="font-semibold text-ink-900 text-sm">მოითხოვე შეთავაზება</p>
                  <p className="text-xs muted">დიდი კამპანიებისთვის ან არასტანდარტული პროექტისთვის</p>
                </div>
              </label>
            </div>

            <div className="mt-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 flex items-start gap-3">
              <IconShield className="text-emerald-700 shrink-0 mt-0.5" />
              <p className="text-sm text-emerald-900">
                თანხა ინახება Escrow-ში — კრეატორი იღებს ანაზღაურებას მხოლოდ მას შემდეგ, რაც დაადასტურებ კონტენტს.
              </p>
            </div>
          </Step>
        </form>

        {/* Order summary sidebar */}
        <aside className="lg:sticky lg:top-20 lg:self-start">
          <div className="card p-6">
            <h3 className="font-bold text-ink-900 text-lg mb-4">შეჯამება</h3>

            <div className="flex items-center gap-3 mb-4 pb-4 border-b border-ink-100">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={creator.avatar} alt="" className="h-10 w-10 rounded-full" />
              <div className="flex-1 min-w-0">
                <p className="font-semibold text-sm truncate">{creator.nameKa}</p>
                <p className="text-xs muted">★ {creator.rating} · {creator.responseTimeHours} სთ პასუხი</p>
              </div>
            </div>

            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-ink-700">საბაზისო ფასი</span>
                <span className="font-medium">{formatGEL(service.price)}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-ink-700">დამატებები</span>
                <span className="muted">0 ₾</span>
              </div>
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

            <Link href="/dashboard/client" className="btn-primary w-full mt-5 text-base py-3">
              გადახდა {formatGEL(total)}
            </Link>
            <p className="text-xs muted text-center mt-3 flex items-center justify-center gap-1">
              <IconCheck className="text-emerald-600" /> SSL უსაფრთხო გადახდა
            </p>
          </div>
        </aside>
      </div>
    </section>
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
