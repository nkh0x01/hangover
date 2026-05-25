import Link from 'next/link';
import { redirect } from 'next/navigation';
import { requireRole } from '@/lib/session';
import { prisma } from '@/lib/prisma';
import { listOrdersForCreator } from '@/lib/orders';
import { OrderStatuses, type OrderStatus } from '@/lib/enums';
import { formatGEL } from '@/lib/i18n';
import { PLATFORM_COMMISSION_PERCENT } from '@/lib/data/orders';
import { IconStar, IconVerified } from '@/components/Icons';
import { ResumeUpload } from '@/components/ResumeUpload';
import { DemoUpload } from '@/components/DemoUpload';

export const dynamic = 'force-dynamic';

export default async function CreatorDashboardPage() {
  const user = await requireRole(['CREATOR', 'ADMIN']);
  const creator = await prisma.creator.findUnique({
    where: { userId: user.id },
    include: { services: true },
  });
  if (!creator) {
    redirect('/auth/register/creator');
  }
  const orders = await listOrdersForCreator(creator.id);
  const reviews = await prisma.review.findMany({
    where: { creatorId: creator.id },
    orderBy: { createdAt: 'desc' },
    take: 3,
  });

  const earnings = orders
    .filter((o) => ['IN_PROGRESS', 'SUBMITTED', 'COMPLETED'].includes(o.status))
    .reduce((sum, o) => sum + o.payout, 0);
  const lifetime = orders
    .filter((o) => o.status === 'COMPLETED')
    .reduce((s, o) => s + o.payout, 0);
  const pendingCount = orders.filter((o) => ['NEW', 'AWAITING_CREATOR'].includes(o.status)).length;

  return (
    <section className="container-page py-10">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center gap-4 mb-8">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={creator.avatar ?? ''} alt={creator.nameKa} className="h-16 w-16 rounded-2xl object-cover" />
        <div className="flex-1">
          <h1 className="text-2xl font-extrabold text-ink-900 flex items-center gap-2">
            მოგესალმები, {creator.nameKa.split(' ')[0]}
            {creator.verified && <IconVerified className="text-brand-600" />}
          </h1>
          <p className="muted text-sm">კრეატორის დაშბორდი · სტატუსი: {creator.status}</p>
        </div>
        <div className="flex gap-2">
          <Link href={`/creator/${creator.slug}`} className="btn-secondary">საჯარო პროფილი</Link>
          <Link href="#services" className="btn-primary">დაამატე სერვისი</Link>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
        <StatCard label="აქტიური შემოსავალი" value={formatGEL(earnings)} sub={`${PLATFORM_COMMISSION_PERCENT}% საკომისიოს გარდა`} />
        <StatCard label="ჯამური შემოსავალი" value={formatGEL(lifetime)} sub="დასრულებული შეკვეთები" />
        <StatCard label="ახალი შეკვეთები" value={String(pendingCount)} sub="ელოდება შენს დადასტურებას" highlight />
        <StatCard label="რეიტინგი" value={`${creator.rating.toFixed(1)} ★`} sub={`${creator.reviewCount} შეფასება`} />
      </div>

      {/* Orders */}
      <section className="card p-6 mb-8">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-ink-900">შემოსული შეკვეთები</h2>
          <span className="muted text-sm">{orders.length} ჯამში</span>
        </div>
        <div className="overflow-x-auto -mx-2">
          <table className="w-full text-sm">
            <thead className="text-xs muted uppercase">
              <tr>
                <th className="text-left py-2 px-2 font-medium">შეკვეთა</th>
                <th className="text-left py-2 px-2 font-medium">კლიენტი</th>
                <th className="text-left py-2 px-2 font-medium">ვადა</th>
                <th className="text-left py-2 px-2 font-medium">ფასი</th>
                <th className="text-left py-2 px-2 font-medium">სტატუსი</th>
                <th className="text-left py-2 px-2 font-medium">მოქმედება</th>
              </tr>
            </thead>
            <tbody>
              {orders.length === 0 && (
                <tr>
                  <td colSpan={6} className="text-center py-8 muted">ჯერ შეკვეთები არ გაქვს.</td>
                </tr>
              )}
              {orders.map((o) => {
                const meta = OrderStatuses[o.status as OrderStatus];
                return (
                  <tr key={o.id} className="border-t border-ink-100">
                    <td className="py-3 px-2 font-medium text-ink-900">
                      <Link href={`/orders/${o.id}`} className="hover:text-brand-700 truncate block max-w-[200px]">
                        {o.serviceTitleKa || o.service?.titleKa}
                      </Link>
                    </td>
                    <td className="py-3 px-2">
                      {o.clientName}
                      {o.clientCompany && <span className="muted block text-xs">{o.clientCompany}</span>}
                    </td>
                    <td className="py-3 px-2 muted">{o.deadline ? new Date(o.deadline).toLocaleDateString('ka-GE') : '—'}</td>
                    <td className="py-3 px-2 font-semibold">{formatGEL(o.price)}</td>
                    <td className="py-3 px-2">
                      <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${meta.cls}`}>
                        {meta.ka}
                      </span>
                    </td>
                    <td className="py-3 px-2">
                      <Link href={`/orders/${o.id}`} className="link text-xs">დეტალები →</Link>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </section>

      {/* Resume + Demo upload */}
      <section id="profile-assets" className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <ResumeUpload />
        <DemoUpload />
      </section>

      {/* Services */}
      <section id="services" className="card p-6 mb-8">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-ink-900">ჩემი სერვისები</h2>
          <button className="btn-primary text-sm" type="button">+ ახალი სერვისი</button>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {creator.services.map((s) => (
            <div key={s.id} className="rounded-xl border border-ink-200 p-4 flex gap-3">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={s.thumbnail ?? ''} alt="" className="h-16 w-20 object-cover rounded-lg" />
              <div className="flex-1 min-w-0">
                <p className="font-semibold text-ink-900 text-sm truncate">{s.titleKa}</p>
                <p className="text-xs muted">{s.deliveryDays} დღე · {s.revisions} შესწორება</p>
                <p className="text-sm font-bold text-brand-700 mt-1">{formatGEL(s.price)}</p>
              </div>
              <div className="flex flex-col gap-1 text-xs">
                <button className="chip" type="button">რედაქტირება</button>
                <button className="chip" type="button">დამალვა</button>
              </div>
            </div>
          ))}
        </div>
      </section>

      {/* Reviews */}
      <section className="card p-6">
        <h2 className="text-lg font-bold text-ink-900 mb-4">ბოლო შეფასებები</h2>
        {reviews.length === 0 ? (
          <p className="muted text-sm py-4 text-center">ჯერ შეფასებები არ გაქვს.</p>
        ) : (
          <div className="space-y-4">
            {reviews.map((r) => (
              <div key={r.id} className="border-b border-ink-100 last:border-0 pb-4 last:pb-0">
                <div className="flex gap-0.5 text-amber-500 mb-2">
                  {Array.from({ length: r.rating }).map((_, i) => (
                    <IconStar key={i} />
                  ))}
                </div>
                <p className="text-sm text-ink-700">"{r.comment}"</p>
                <p className="text-xs muted mt-1">{new Date(r.createdAt).toLocaleDateString('ka-GE')}</p>
              </div>
            ))}
          </div>
        )}
      </section>
    </section>
  );
}

function StatCard({
  label,
  value,
  sub,
  highlight,
}: {
  label: string;
  value: string;
  sub?: string;
  highlight?: boolean;
}) {
  return (
    <div className={`card p-5 ${highlight ? 'ring-2 ring-brand-200' : ''}`}>
      <p className="text-xs muted">{label}</p>
      <p className={`text-2xl font-extrabold mt-1 ${highlight ? 'text-brand-700' : 'text-ink-900'}`}>
        {value}
      </p>
      {sub && <p className="text-xs muted mt-1">{sub}</p>}
    </div>
  );
}
