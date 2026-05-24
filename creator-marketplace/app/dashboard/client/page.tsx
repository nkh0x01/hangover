import Link from 'next/link';
import { orders } from '@/lib/data/orders';
import { getCreatorById, creators } from '@/lib/data/creators';
import { getService } from '@/lib/data/services';
import { StatusBadge } from '@/components/StatusBadge';
import { formatGEL } from '@/lib/i18n';
import { IconStar } from '@/components/Icons';

// MVP: show all orders that belong to "Tata Khurtsidze" (Mera) + Wolt — pretend we're logged in as one client.
export default function ClientDashboardPage() {
  const myOrders = orders;
  const active = myOrders.filter((o) =>
    ['new', 'awaiting_creator', 'in_progress', 'submitted', 'revision_requested'].includes(o.status),
  );
  const completed = myOrders.filter((o) => o.status === 'completed');
  const savedCreators = creators.slice(0, 4);

  const totalSpent = myOrders.reduce((s, o) => s + (o.status === 'completed' ? o.price : 0), 0);

  return (
    <section className="container-page py-10">
      <div className="flex flex-col sm:flex-row sm:items-center gap-3 mb-8">
        <div className="flex-1">
          <h1 className="text-2xl font-extrabold text-ink-900">კლიენტის დაშბორდი</h1>
          <p className="muted text-sm">მართე შენი შეკვეთები, კრეატორები და კონტენტი ერთ ადგილზე.</p>
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
          <Link href="#" className="link text-sm">ყველას ნახვა</Link>
        </div>
        {active.length === 0 ? (
          <p className="muted text-sm py-8 text-center">აქტიური შეკვეთები არ გაქვს.</p>
        ) : (
          <div className="space-y-3">
            {active.map((o) => {
              const creator = getCreatorById(o.creatorId);
              const service = getService(o.serviceId);
              return (
                <div key={o.id} className="rounded-xl border border-ink-200 p-4 flex flex-col sm:flex-row sm:items-center gap-4">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={creator?.avatar} alt="" className="h-12 w-12 rounded-full" />
                  <div className="flex-1 min-w-0">
                    <p className="font-semibold text-ink-900">{service?.titleKa}</p>
                    <p className="text-xs muted">
                      {creator?.nameKa} · ვადა: {o.deadline} · #{o.id.replace('o-', '')}
                    </p>
                  </div>
                  <div className="flex items-center gap-3">
                    <StatusBadge status={o.status} />
                    <span className="text-sm font-bold text-ink-900">{formatGEL(o.price)}</span>
                  </div>
                  <div className="flex gap-2">
                    <Link href={`/messages?conv=conv-1`} className="btn-ghost text-xs">
                      შეტყობინება
                    </Link>
                    <button className="btn-secondary text-xs" type="button">დეტალები</button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </section>

      {/* Completed orders */}
      <section className="card p-6 mb-8">
        <h2 className="text-lg font-bold text-ink-900 mb-4">დასრულებული შეკვეთები</h2>
        {completed.length === 0 ? (
          <p className="muted text-sm py-8 text-center">ჯერ დასრულებული შეკვეთები არ გაქვს.</p>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {completed.map((o) => {
              const creator = getCreatorById(o.creatorId);
              const service = getService(o.serviceId);
              return (
                <div key={o.id} className="rounded-xl border border-ink-200 p-4">
                  <div className="flex items-center gap-3">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={creator?.avatar} alt="" className="h-10 w-10 rounded-full" />
                    <div className="flex-1 min-w-0">
                      <p className="font-semibold text-sm truncate">{service?.titleKa}</p>
                      <p className="text-xs muted">{creator?.nameKa}</p>
                    </div>
                    <StatusBadge status={o.status} />
                  </div>
                  <div className="flex items-center gap-2 mt-4">
                    <button className="btn-secondary flex-1 text-xs" type="button">
                      ჩამოტვირთვა
                    </button>
                    <button className="btn-ghost text-xs" type="button">
                      <IconStar className="text-amber-500" /> შეფასება
                    </button>
                    <Link href={`/service/${o.serviceId}`} className="btn-ghost text-xs">
                      ↻ თავიდან
                    </Link>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </section>

      {/* Saved creators */}
      <section className="card p-6">
        <h2 className="text-lg font-bold text-ink-900 mb-4">შენახული კრეატორები</h2>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          {savedCreators.map((c) => (
            <Link
              key={c.id}
              href={`/creator/${c.slug}`}
              className="rounded-xl border border-ink-200 p-4 hover:shadow-soft transition text-center"
            >
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={c.avatar} alt={c.nameKa} className="h-14 w-14 rounded-full mx-auto" />
              <p className="text-sm font-semibold text-ink-900 mt-2 truncate">{c.nameKa}</p>
              <p className="text-xs muted truncate">{c.cityKa}</p>
              <p className="text-xs font-semibold text-brand-700 mt-1">
                იწყება {formatGEL(c.startingPrice)}
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
