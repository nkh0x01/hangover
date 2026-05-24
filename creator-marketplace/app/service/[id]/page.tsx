import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getService, services } from '@/lib/data/services';
import { getCreatorById } from '@/lib/data/creators';
import { getCategory } from '@/lib/data/categories';
import { formatGEL } from '@/lib/i18n';
import { IconCheck, IconClock, IconStar, IconVerified } from '@/components/Icons';

export function generateStaticParams() {
  return services.map((s) => ({ id: s.id }));
}

export default function ServiceDetailPage({ params }: { params: { id: string } }) {
  const service = getService(params.id);
  if (!service) return notFound();
  const creator = getCreatorById(service.creatorId);
  if (!creator) return notFound();
  const cat = getCategory(service.category);

  return (
    <section className="container-page py-10 grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-8">
      <div>
        <nav className="text-xs muted mb-3 flex items-center gap-2">
          <Link href="/marketplace" className="hover:text-ink-900">კატალოგი</Link>
          <span>/</span>
          <Link href={`/marketplace?category=${service.category}`} className="hover:text-ink-900">
            {cat?.ka}
          </Link>
          <span>/</span>
          <Link href={`/creator/${creator.slug}`} className="hover:text-ink-900">
            {creator.nameKa}
          </Link>
        </nav>

        <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink-900">
          {service.titleKa}
        </h1>
        <div className="flex items-center gap-3 mt-3">
          <Link href={`/creator/${creator.slug}`} className="flex items-center gap-2">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={creator.avatar} alt={creator.nameKa} className="h-8 w-8 rounded-full" />
            <span className="text-sm font-semibold text-ink-900">{creator.nameKa}</span>
            {creator.verified && <IconVerified className="text-brand-600" />}
          </Link>
          <span className="text-ink-300">·</span>
          <span className="text-sm flex items-center gap-1 text-ink-700">
            <IconStar className="text-amber-500" /> {creator.rating.toFixed(1)} ({creator.reviewCount})
          </span>
        </div>

        <div className="mt-6 aspect-video overflow-hidden rounded-2xl bg-ink-100">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={service.thumbnail} alt={service.titleKa} className="h-full w-full object-cover" />
        </div>

        <section className="mt-8">
          <h2 className="text-lg font-bold text-ink-900 mb-2">სერვისის შესახებ</h2>
          <p className="text-ink-700 leading-relaxed">{service.descriptionKa}</p>
        </section>

        <section className="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div className="card p-5">
            <h3 className="font-semibold text-ink-900 mb-3">რას მოიცავს</h3>
            <ul className="space-y-2">
              {service.includesKa.map((i) => (
                <li key={i} className="flex items-start gap-2 text-sm text-ink-700">
                  <span className="h-5 w-5 mt-0.5 rounded-full bg-emerald-100 text-emerald-700 inline-flex items-center justify-center shrink-0">
                    <IconCheck />
                  </span>
                  {i}
                </li>
              ))}
            </ul>
          </div>
          <div className="card p-5">
            <h3 className="font-semibold text-ink-900 mb-3">მოთხოვნები კლიენტისგან</h3>
            <ul className="space-y-2">
              {service.requirementsKa.map((i) => (
                <li key={i} className="flex items-start gap-2 text-sm text-ink-700">
                  <span className="h-5 w-5 mt-0.5 rounded-full bg-amber-100 text-amber-700 inline-flex items-center justify-center shrink-0">
                    !
                  </span>
                  {i}
                </li>
              ))}
            </ul>
          </div>
        </section>

        {service.addons.length > 0 && (
          <section className="mt-8">
            <h2 className="text-lg font-bold text-ink-900 mb-3">დამატებები (Add-ons)</h2>
            <div className="space-y-2">
              {service.addons.map((a, i) => (
                <div key={i} className="card p-4 flex items-center justify-between">
                  <span className="text-sm text-ink-700">{a.titleKa}</span>
                  <span className="font-semibold text-ink-900">+ {formatGEL(a.price)}</span>
                </div>
              ))}
            </div>
          </section>
        )}
      </div>

      {/* Sticky order card */}
      <aside className="lg:sticky lg:top-20 lg:self-start space-y-4">
        <div className="card p-6">
          <p className="muted text-xs">ფასი</p>
          <p className="text-3xl font-extrabold text-ink-900">{formatGEL(service.price)}</p>
          <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div className="flex items-center gap-2 text-ink-700">
              <IconClock /> {service.deliveryDays} დღე მიწოდება
            </div>
            <div className="flex items-center gap-2 text-ink-700">
              ↻ {service.revisions} შესწორება
            </div>
          </div>

          <Link href={`/checkout/${service.id}`} className="btn-primary w-full mt-5 text-base py-3">
            შეუკვეთე სერვისი
          </Link>
          <Link
            href={`/messages?creator=${creator.slug}`}
            className="btn-secondary w-full mt-2"
          >
            მოითხოვე შეთავაზება
          </Link>
          <p className="text-xs muted mt-3 text-center">
            გადახდა უსაფრთხო Escrow-ით — თანხა მიდის კრეატორთან მხოლოდ მიწოდების შემდეგ.
          </p>
        </div>

        <Link href={`/creator/${creator.slug}`} className="card p-5 flex items-center gap-3 hover:shadow-soft transition">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={creator.avatar} alt={creator.nameKa} className="h-12 w-12 rounded-full" />
          <div className="flex-1 min-w-0">
            <p className="font-semibold text-ink-900 truncate flex items-center gap-1.5">
              {creator.nameKa}
              {creator.verified && <IconVerified className="text-brand-600" />}
            </p>
            <p className="text-xs muted truncate">{creator.cityKa} · {cat?.ka}</p>
          </div>
        </Link>
      </aside>
    </section>
  );
}
