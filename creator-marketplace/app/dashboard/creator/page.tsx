import Link from 'next/link';
import { creators } from '@/lib/data/creators';
import { getServicesByCreator } from '@/lib/data/services';
import { getOrdersByCreator, PLATFORM_COMMISSION_PERCENT } from '@/lib/data/orders';
import { getReviewsByCreator } from '@/lib/data/reviews';
import { StatusBadge } from '@/components/StatusBadge';
import { formatGEL } from '@/lib/i18n';
import { IconStar, IconVerified } from '@/components/Icons';
import { ResumeUpload } from '@/components/ResumeUpload';
import { DemoUpload } from '@/components/DemoUpload';

// MVP: show the first creator's dashboard as logged-in user.
export default function CreatorDashboardPage() {
  const me = creators[0];
  const services = getServicesByCreator(me.id);
  const orders = getOrdersByCreator(me.id);
  const reviews = getReviewsByCreator(me.id);

  const earnings = orders
    .filter((o) => o.status === 'completed' || o.status === 'submitted' || o.status === 'in_progress')
    .reduce((sum, o) => sum + o.payout, 0);
  const lifetime = orders.reduce((s, o) => s + (o.status === 'completed' ? o.payout : 0), 0);
  const pendingCount = orders.filter((o) => o.status === 'new' || o.status === 'awaiting_creator').length;

  return (
    <section className="container-page py-10">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center gap-4 mb-8">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={me.avatar} alt={me.nameKa} className="h-16 w-16 rounded-2xl object-cover" />
        <div className="flex-1">
          <h1 className="text-2xl font-extrabold text-ink-900 flex items-center gap-2">
            მოგესალმები, {me.nameKa.split(' ')[0]}
            {me.verified && <IconVerified className="text-brand-600" />}
          </h1>
          <p className="muted text-sm">კრეატორის დაშბორდი</p>
        </div>
        <div className="flex gap-2">
          <Link href={`/creator/${me.slug}`} className="btn-secondary">საჯარო პროფილი</Link>
          <Link href="#services" className="btn-primary">დაამატე სერვისი</Link>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
        <StatCard label="აქტიური შემოსავალი" value={formatGEL(earnings)} sub={`${PLATFORM_COMMISSION_PERCENT}% საკომისიოს გარდა`} />
        <StatCard label="ჯამური შემოსავალი" value={formatGEL(lifetime)} sub="დასრულებული შეკვეთები" />
        <StatCard label="ახალი შეკვეთები" value={String(pendingCount)} sub="ელოდება შენს დადასტურებას" highlight />
        <StatCard
          label="რეიტინგი"
          value={`${me.rating.toFixed(1)} ★`}
          sub={`${me.reviewCount} შეფასება`}
        />
      </div>

      {/* Orders */}
      <section className="card p-6 mb-8">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-ink-900">შემოსული შეკვეთები</h2>
          <Link href="#" className="link text-sm">ყველას ნახვა</Link>
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
              {orders.map((o) => (
                <tr key={o.id} className="border-t border-ink-100">
                  <td className="py-3 px-2 font-medium text-ink-900">#{o.id.replace('o-', '')}</td>
                  <td className="py-3 px-2">
                    {o.clientName}
                    {o.clientCompany && <span className="muted block text-xs">{o.clientCompany}</span>}
                  </td>
                  <td className="py-3 px-2 muted">{o.deadline}</td>
                  <td className="py-3 px-2 font-semibold">{formatGEL(o.price)}</td>
                  <td className="py-3 px-2"><StatusBadge status={o.status} /></td>
                  <td className="py-3 px-2">
                    {o.status === 'awaiting_creator' && (
                      <div className="flex gap-1.5">
                        <button className="chip-green hover:bg-emerald-200" type="button">დადასტურება</button>
                        <button className="chip hover:bg-ink-200" type="button">უარყოფა</button>
                      </div>
                    )}
                    {o.status === 'in_progress' && (
                      <button className="chip-brand hover:bg-brand-200" type="button">კონტენტის ჩაბარება</button>
                    )}
                    {o.status === 'submitted' && (
                      <span className="muted text-xs">ელოდება მიმოხილვას</span>
                    )}
                    {(o.status === 'completed' || o.status === 'cancelled') && (
                      <Link href="#" className="link text-xs">დეტალები</Link>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      {/* Resume + Demo upload (middle-man protection) */}
      <section id="profile-assets" className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <ResumeUpload />
        <DemoUpload />
      </section>

      {/* Services management */}
      <section id="services" className="card p-6 mb-8">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-ink-900">ჩემი სერვისები</h2>
          <button className="btn-primary text-sm" type="button">+ ახალი სერვისი</button>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {services.map((s) => (
            <div key={s.id} className="rounded-xl border border-ink-200 p-4 flex gap-3">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={s.thumbnail} alt="" className="h-16 w-20 object-cover rounded-lg" />
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

      {/* Two-col: reviews + analytics */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section className="card p-6">
          <h2 className="text-lg font-bold text-ink-900 mb-4">ბოლო შეფასებები</h2>
          <div className="space-y-4">
            {reviews.slice(0, 3).map((r) => (
              <div key={r.id} className="border-b border-ink-100 last:border-0 pb-4 last:pb-0">
                <div className="flex items-center justify-between">
                  <p className="font-semibold text-sm text-ink-900">{r.clientName}</p>
                  <div className="flex gap-0.5 text-amber-500">
                    {Array.from({ length: r.rating }).map((_, i) => (
                      <IconStar key={i} />
                    ))}
                  </div>
                </div>
                <p className="text-sm text-ink-700 mt-1">"{r.commentKa}"</p>
              </div>
            ))}
          </div>
        </section>

        <section className="card p-6">
          <h2 className="text-lg font-bold text-ink-900 mb-4">ანალიტიკა (30 დღე)</h2>
          <div className="grid grid-cols-2 gap-4 text-sm">
            <Metric label="პროფილის ნახვები" value="2,148" trend="+18%" />
            <Metric label="შეტყობინებები" value="34" trend="+9%" />
            <Metric label="შეკვეთები" value="6" trend="+50%" />
            <Metric label="დასრულების მაჩვენებელი" value="98%" trend="+1%" />
          </div>
          <div className="mt-6">
            <p className="text-xs muted mb-1">ნახვები კვირაში</p>
            <div className="flex items-end gap-1.5 h-24">
              {[40, 55, 35, 70, 90, 60, 100].map((v, i) => (
                <div
                  key={i}
                  className="flex-1 bg-brand-200 rounded-md relative"
                  style={{ height: `${v}%` }}
                >
                  <div
                    className="absolute inset-0 bg-brand-500 rounded-md"
                    style={{ height: `${v * 0.6}%`, top: 'auto', bottom: 0 }}
                  />
                </div>
              ))}
            </div>
          </div>
        </section>
      </div>
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

function Metric({ label, value, trend }: { label: string; value: string; trend: string }) {
  return (
    <div className="rounded-xl bg-ink-50 p-4">
      <p className="text-xs muted">{label}</p>
      <p className="text-xl font-bold text-ink-900 mt-1">{value}</p>
      <p className="text-xs text-emerald-600 font-medium mt-0.5">{trend}</p>
    </div>
  );
}
