import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getCreator, creators } from '@/lib/data/creators';
import { getServicesByCreator } from '@/lib/data/services';
import { getReviewsByCreator } from '@/lib/data/reviews';
import { getCategory } from '@/lib/data/categories';
import { ServiceCard } from '@/components/ServiceCard';
import {
  IconClock,
  IconLocation,
  IconStar,
  IconUsers,
  IconVerified,
  PlatformIcon,
} from '@/components/Icons';
import { formatFollowers, formatGEL } from '@/lib/i18n';

export function generateStaticParams() {
  return creators.map((c) => ({ slug: c.slug }));
}

export default function CreatorProfilePage({ params }: { params: { slug: string } }) {
  const creator = getCreator(params.slug);
  if (!creator) return notFound();

  const services = getServicesByCreator(creator.id);
  const reviews = getReviewsByCreator(creator.id);
  const cat = getCategory(creator.category);

  return (
    <>
      {/* Cover */}
      <div className="relative h-56 sm:h-72 bg-ink-200 overflow-hidden">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={creator.cover} alt="" className="h-full w-full object-cover" />
        <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent" />
      </div>

      <div className="container-page -mt-20 relative z-10">
        <div className="card p-6 sm:p-8">
          <div className="flex flex-col sm:flex-row sm:items-end gap-5">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={creator.avatar}
              alt={creator.nameKa}
              className="h-28 w-28 rounded-2xl ring-4 ring-white shadow-card object-cover -mt-16"
            />
            <div className="flex-1">
              <div className="flex items-center gap-2 flex-wrap">
                <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink-900">
                  {creator.nameKa}
                </h1>
                {creator.verified && (
                  <span className="chip-brand">
                    <IconVerified /> დადასტურებული კრეატორი
                  </span>
                )}
              </div>
              <p className="muted text-sm mt-1 flex items-center gap-2 flex-wrap">
                <span className="flex items-center gap-1">
                  <IconLocation /> {creator.cityKa}
                </span>
                <span>·</span>
                <span>{cat?.emoji} {cat?.ka}</span>
                <span>·</span>
                <span className="flex items-center gap-1">
                  <IconStar className="text-amber-500" /> {creator.rating.toFixed(1)} ({creator.reviewCount} შეფასება)
                </span>
              </p>
              <div className="flex items-center gap-3 mt-3 text-ink-500">
                {creator.platforms.map((p) => (
                  <a
                    key={p}
                    href={creator.socialLinks[p]}
                    target="_blank"
                    rel="noreferrer"
                    className="text-ink-500 hover:text-brand-700 transition"
                    title={p}
                  >
                    <PlatformIcon platform={p} />
                  </a>
                ))}
              </div>
            </div>
            <div className="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
              <Link href={`/messages?creator=${creator.slug}`} className="btn-secondary">
                შეტყობინების გაგზავნა
              </Link>
              <Link href={`#services`} className="btn-primary">
                შეუკვეთე სერვისი
              </Link>
            </div>
          </div>

          {/* Stats row */}
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mt-7 pt-7 border-t border-ink-100">
            <Stat icon={<IconUsers />} label="ჯამური მიმდევრები" value={formatFollowers(creator.totalFollowers)} />
            <Stat label="იწყება" value={formatGEL(creator.startingPrice)} valueClass="text-brand-700" />
            <Stat icon={<IconClock />} label="საშ. მიწოდება" value={`${creator.avgDeliveryDays} დღე`} />
            <Stat label="პასუხის დრო" value={`${creator.responseTimeHours} სთ`} />
          </div>
        </div>
      </div>

      {/* Main grid */}
      <section className="container-page py-10 grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-8">
        <div className="space-y-10">
          {/* About */}
          <section>
            <h2 className="text-xl font-bold text-ink-900 mb-3">კრეატორის შესახებ</h2>
            <p className="text-ink-700 leading-relaxed">{creator.bioKa}</p>

            <div className="flex flex-wrap gap-2 mt-4">
              {creator.nichesKa.map((n) => (
                <span key={n} className="chip">{n}</span>
              ))}
            </div>
          </section>

          {/* Portfolio */}
          <section>
            <h2 className="text-xl font-bold text-ink-900 mb-3">პორტფოლიო</h2>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
              {creator.portfolio.map((p) => (
                <div
                  key={p.id}
                  className="relative aspect-square rounded-xl overflow-hidden bg-ink-100 group"
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={p.thumbnail}
                    alt={p.titleKa}
                    className="h-full w-full object-cover group-hover:scale-105 transition"
                  />
                  {p.type === 'video' && (
                    <span className="absolute top-2 right-2 chip bg-black/60 text-white border-0">
                      ▶ ვიდეო
                    </span>
                  )}
                  <div className="absolute inset-x-0 bottom-0 p-2 bg-gradient-to-t from-black/70 to-transparent text-white text-xs opacity-0 group-hover:opacity-100 transition">
                    {p.titleKa}
                  </div>
                </div>
              ))}
            </div>
          </section>

          {/* Services */}
          <section id="services">
            <div className="flex items-end justify-between mb-3">
              <h2 className="text-xl font-bold text-ink-900">სერვისები</h2>
              <span className="muted text-sm">{services.length} სერვისი</span>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
              {services.map((s) => (
                <ServiceCard key={s.id} service={s} />
              ))}
            </div>
          </section>

          {/* Reviews */}
          <section>
            <h2 className="text-xl font-bold text-ink-900 mb-3">შეფასებები ({reviews.length})</h2>
            <div className="space-y-4">
              {reviews.length === 0 && (
                <p className="muted text-sm">ჯერ შეფასებები არ არის.</p>
              )}
              {reviews.map((r) => (
                <div key={r.id} className="card p-5">
                  <div className="flex items-center gap-3">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={r.clientAvatar}
                      alt={r.clientName}
                      className="h-10 w-10 rounded-full object-cover"
                    />
                    <div className="flex-1">
                      <p className="font-semibold text-ink-900 text-sm">{r.clientName}</p>
                      <p className="text-xs muted">{r.date}</p>
                    </div>
                    <div className="flex gap-0.5 text-amber-500">
                      {Array.from({ length: r.rating }).map((_, i) => (
                        <IconStar key={i} />
                      ))}
                    </div>
                  </div>
                  <p className="text-ink-700 mt-3 leading-relaxed">"{r.commentKa}"</p>
                </div>
              ))}
            </div>
          </section>
        </div>

        {/* Sidebar */}
        <aside className="space-y-5">
          <div className="card p-5">
            <h3 className="font-semibold text-ink-900 mb-3">პლატფორმები</h3>
            <ul className="space-y-2.5">
              {creator.platforms.map((p) => (
                <li key={p} className="flex items-center justify-between text-sm">
                  <span className="flex items-center gap-2 text-ink-700 capitalize">
                    <PlatformIcon platform={p} /> {p}
                  </span>
                  <span className="chip">
                    {formatFollowers(creator.followers[p] ?? 0)}
                  </span>
                </li>
              ))}
            </ul>
          </div>

          <div className="card p-5">
            <h3 className="font-semibold text-ink-900 mb-3">აუდიტორიის დემოგრაფია</h3>
            <div className="text-sm">
              <p className="muted text-xs">სქესი</p>
              <div className="flex items-center gap-1 mt-1.5">
                <div
                  className="h-2 rounded-full bg-pink-400"
                  style={{ width: `${creator.audienceDemographics.genderFemalePct}%` }}
                />
                <div
                  className="h-2 rounded-full bg-sky-400"
                  style={{ width: `${creator.audienceDemographics.genderMalePct}%` }}
                />
              </div>
              <div className="flex justify-between text-xs muted mt-1">
                <span>♀ {creator.audienceDemographics.genderFemalePct}%</span>
                <span>♂ {creator.audienceDemographics.genderMalePct}%</span>
              </div>
            </div>
            <div className="mt-4">
              <p className="muted text-xs">ასაკი</p>
              <div className="space-y-1.5 mt-2">
                {creator.audienceDemographics.ageGroups.map((g) => (
                  <div key={g.ageGroup} className="text-xs">
                    <div className="flex justify-between text-ink-700">
                      <span>{g.ageGroup}</span>
                      <span>{g.percent}%</span>
                    </div>
                    <div className="h-1.5 rounded-full bg-ink-100 overflow-hidden mt-0.5">
                      <div
                        className="h-full bg-brand-500"
                        style={{ width: `${g.percent}%` }}
                      />
                    </div>
                  </div>
                ))}
              </div>
            </div>
            <div className="mt-4">
              <p className="muted text-xs">ტოპ ქვეყნები</p>
              <div className="flex flex-wrap gap-1.5 mt-2">
                {creator.audienceDemographics.topCountries.map((c) => (
                  <span key={c} className="chip">{c}</span>
                ))}
              </div>
            </div>
          </div>

          <div className="card p-5">
            <h3 className="font-semibold text-ink-900 mb-3">ენები</h3>
            <div className="flex flex-wrap gap-1.5">
              {creator.languages.map((l) => (
                <span key={l} className="chip">{l}</span>
              ))}
            </div>
          </div>

          <Link href={`#services`} className="btn-primary w-full">
            დაიწყე თანამშრომლობა
          </Link>
        </aside>
      </section>
    </>
  );
}

function Stat({
  icon,
  label,
  value,
  valueClass,
}: {
  icon?: React.ReactNode;
  label: string;
  value: string;
  valueClass?: string;
}) {
  return (
    <div>
      <p className="muted text-xs flex items-center gap-1.5">
        {icon} {label}
      </p>
      <p className={`mt-1 text-lg font-bold text-ink-900 ${valueClass ?? ''}`}>{value}</p>
    </div>
  );
}
