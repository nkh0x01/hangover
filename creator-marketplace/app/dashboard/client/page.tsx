import Link from 'next/link';
import { redirect } from 'next/navigation';
import { requireRole } from '@/lib/session';
import { prisma } from '@/lib/prisma';
import { listOrdersForClient } from '@/lib/orders';
import { OrderStatuses, type OrderStatus } from '@/lib/enums';
import { formatGEL } from '@/lib/i18n';
import { IconStar } from '@/components/Icons';

export const dynamic = 'force-dynamic';

export default async function ClientDashboardPage() {
  const user = await requireRole(['CLIENT', 'ADMIN']);
  const client = await prisma.client.findUnique({ where: { userId: user.id } });
  if (!client) redirect('/auth/register/client');

  const myOrders = await listOrdersForClient(client.id);
  const active = myOrders.filter((o) =>
    ['NEW', 'AWAITING_CREATOR', 'IN_PROGRESS', 'SUBMITTED', 'REVISION_REQUESTED'].includes(o.status),
  );
  const completed = myOrders.filter((o) => o.status === 'COMPLETED');
  const totalSpent = myOrders
    .filter((o) => o.status === 'COMPLETED')
    .reduce((s, o) => s + o.price, 0);
  const savedCreators = await prisma.creator.findMany({
    where: { featured: true },
    take: 4,
  });

  return (
    <section className="container-page py-10">
      <div className="flex flex-col sm:flex-row sm:items-center gap-3 mb-8">
        <div className="flex-1">
          <h1 className="text-2xl font-extrabold text-ink-900">კლიენტის დაშბორდი</h1>
          <p className="muted text-sm">
            მოგესალმები, {client.name}{client.companyName ? ` (${client.companyName})` : ''}
          </p>
        </div>
        <Link href="/marketplace" className="btn-primary">+ ახალი შეკვეთა</Link>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
        <StatCard label="აქტიური შეკვეთები" value={String(active.length)} highlight />
        <StatCard label="დასრულდა" value={String(completed.length)} />
        <StatCard label="ჯამური ხარჯი" value={formatGEL(totalSpent)} sub="დასრულებული შეკვეთები" />
        <StatCard label="შენახული კრეატორი" value={String(savedCreators.length)} />
      </div>

      {/* Active orders */}
      <section className="card p-6 mb-8">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-ink-900">მიმდინარე შეკვეთები</h2>
          <span className="muted text-sm">{active.length}</span>
        </div>
        {active.length === 0 ? (
          <p className="muted text-sm py-8 text-center">
            აქტიური შეკვეთები არ გაქვს.{' '}
            <Link href="/marketplace" className="link">დაიწყე ერთი</Link>.
          </p>
        ) : (
          <div className="space-y-3">
            {active.map((o) => {
              const meta = OrderStatuses[o.status as OrderStatus];
              return (
                <Link
                  key={o.id}
                  href={`/orders/${o.id}`}
                  className="rounded-xl border border-ink-200 p-4 flex flex-col sm:flex-row sm:items-center gap-4 hover:shadow-soft transition"
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={o.creator.avatar ?? ''} alt="" className="h-12 w-12 rounded-full" />
                  <div className="flex-1 min-w-0">
                    <p className="font-semibold text-ink-900 truncate">{o.serviceTitleKa || o.service?.titleKa}</p>
                    <p className="text-xs muted">
                      {o.creator.nameKa} · ვადა: {o.deadline ? new Date(o.deadline).toLocaleDateString('ka-GE') : '—'} · #{o.id.slice(-6).toUpperCase()}
                    </p>
                  </div>
                  <div className="flex items-center gap-3">
                    <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${meta.cls}`}>
                      {meta.ka}
                    </span>
                    <span className="text-sm font-bold text-ink-900">{formatGEL(o.price)}</span>
                  </div>
                </Link>
              );
            })}
          </div>
        )}
      </section>

      {/* Completed */}
      <section className="card p-6 mb-8">
        <h2 className="text-lg font-bold text-ink-900 mb-4">დასრულებული შეკვეთები</h2>
        {completed.length === 0 ? (
          <p className="muted text-sm py-8 text-center">ჯერ დასრულებული არ არის.</p>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {completed.map((o) => (
              <Link
                key={o.id}
                href={`/orders/${o.id}`}
                className="rounded-xl border border-ink-200 p-4 hover:shadow-soft transition"
              >
                <div className="flex items-center gap-3">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={o.creator.avatar ?? ''} alt="" className="h-10 w-10 rounded-full" />
                  <div className="flex-1 min-w-0">
                    <p className="font-semibold text-sm truncate">{o.serviceTitleKa || o.service?.titleKa}</p>
                    <p className="text-xs muted">{o.creator.nameKa}</p>
                  </div>
                  <span className="chip-green">დასრულდა</span>
                </div>
              </Link>
            ))}
          </div>
        )}
      </section>

      <section className="card p-6">
        <h2 className="text-lg font-bold text-ink-900 mb-4">რეკომენდირებული კრეატორები</h2>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          {savedCreators.map((c) => (
            <Link
              key={c.id}
              href={`/creator/${c.slug}`}
              className="rounded-xl border border-ink-200 p-4 hover:shadow-soft transition text-center"
            >
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={c.avatar ?? ''} alt={c.nameKa} className="h-14 w-14 rounded-full mx-auto" />
              <p className="text-sm font-semibold text-ink-900 mt-2 truncate">{c.nameKa}</p>
              <p className="text-xs muted truncate">{c.cityKa}</p>
              <p className="text-xs font-semibold text-brand-700 mt-1">
                იწყება {formatGEL(c.startingPrice)}
              </p>
              <p className="text-[10px] muted mt-0.5 flex items-center justify-center gap-1">
                <IconStar className="text-amber-500" /> {c.rating.toFixed(1)}
              </p>
            </Link>
          ))}
        </div>
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
