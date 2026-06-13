import Link from 'next/link';
import { adminUsers, platformStats } from '@/lib/data/users';
import { categories } from '@/lib/data/categories';
import { creators } from '@/lib/data/creators';
import { orders } from '@/lib/data/orders';
import { formatGEL } from '@/lib/i18n';
import { StatusBadge } from '@/components/StatusBadge';
import { IconVerified } from '@/components/Icons';

export default function AdminPage() {
  const pending = adminUsers.filter((u) => u.status === 'pending');
  const recentOrders = orders.slice(0, 5);
  const featured = creators.filter((c) => c.featured);

  return (
    <section className="container-page py-10">
      <div className="flex items-center justify-between mb-8">
        <div>
          <span className="chip-brand mb-2">ადმინ პანელი</span>
          <h1 className="text-2xl font-extrabold text-ink-900 mt-1">პლატფორმის ოპერაცია</h1>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
        <StatCard label="მომხმარებლები" value={platformStats.totalUsers.toLocaleString('ka-GE')} sub={`${platformStats.totalCreators} კრეატორი`} />
        <StatCard label="აქტიური შეკვეთები" value={String(platformStats.ordersInProgress)} sub={`${platformStats.totalOrders30d} შეკვეთა 30 დღეში`} />
        <StatCard label="საკომისიო (30 დღე)" value={formatGEL(platformStats.commission30dGEL)} sub={`ბრუნვა ${formatGEL(platformStats.revenue30dGEL)}`} highlight />
        <StatCard label="დასადასტურებელი" value={String(platformStats.pendingCreators)} sub={`${platformStats.openDisputes} ღია დავა`} />
      </div>

      {/* Pending creators */}
      <section className="card p-6 mb-8">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-ink-900">დასადასტურებელი კრეატორები</h2>
          <Link href="#" className="link text-sm">ყველას ნახვა</Link>
        </div>
        {pending.length === 0 ? (
          <p className="muted text-sm py-6 text-center">დასადასტურებელი არ არის.</p>
        ) : (
          <div className="overflow-x-auto -mx-2">
            <table className="w-full text-sm">
              <thead className="text-xs muted uppercase">
                <tr>
                  <th className="text-left py-2 px-2 font-medium">სახელი</th>
                  <th className="text-left py-2 px-2 font-medium">ელ-ფოსტა</th>
                  <th className="text-left py-2 px-2 font-medium">თარიღი</th>
                  <th className="text-left py-2 px-2 font-medium">მოქმედება</th>
                </tr>
              </thead>
              <tbody>
                {pending.map((u) => (
                  <tr key={u.id} className="border-t border-ink-100">
                    <td className="py-3 px-2 font-medium">{u.name}</td>
                    <td className="py-3 px-2 muted">{u.email}</td>
                    <td className="py-3 px-2 muted">{u.joinedAt}</td>
                    <td className="py-3 px-2">
                      <div className="flex gap-1.5">
                        <button className="chip-green hover:bg-emerald-200" type="button">დადასტურება</button>
                        <button className="chip hover:bg-ink-200" type="button">უარყოფა</button>
                        <button className="chip-brand hover:bg-brand-200" type="button">ვერიფიკაცია</button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {/* Recent orders + Categories side by side */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <section className="card p-6">
          <h2 className="text-lg font-bold text-ink-900 mb-4">ბოლო შეკვეთები</h2>
          <div className="space-y-3">
            {recentOrders.map((o) => (
              <div key={o.id} className="flex items-center gap-3 border-b border-ink-100 last:border-0 pb-3 last:pb-0">
                <span className="font-mono text-xs muted w-16">#{o.id.replace('o-', '')}</span>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium truncate">{o.clientCompany ?? o.clientName}</p>
                  <p className="text-xs muted">საკომისიო: {formatGEL(o.commission)}</p>
                </div>
                <StatusBadge status={o.status} />
              </div>
            ))}
          </div>
        </section>

        <section className="card p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-bold text-ink-900">კატეგორიების მართვა</h2>
            <button className="btn-primary text-xs" type="button">+ კატეგორია</button>
          </div>
          <div className="grid grid-cols-2 gap-2 max-h-72 overflow-y-auto pr-2">
            {categories.map((c) => (
              <div
                key={c.id}
                className="flex items-center gap-2 rounded-lg border border-ink-200 px-3 py-2 text-sm"
              >
                <span>{c.emoji}</span>
                <span className="flex-1 truncate">{c.ka}</span>
                <span className="text-xs muted">
                  {creators.filter((cr) => cr.category === c.id).length}
                </span>
              </div>
            ))}
          </div>
        </section>
      </div>

      {/* Featured creators */}
      <section className="card p-6 mb-8">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-ink-900">რჩეული კრეატორები (Homepage)</h2>
          <button className="btn-secondary text-xs" type="button">მართვა</button>
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          {featured.map((c) => (
            <div key={c.id} className="rounded-xl border border-ink-200 p-3 text-center">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={c.avatar} alt="" className="h-12 w-12 rounded-full mx-auto" />
              <p className="text-xs font-semibold mt-2 truncate flex items-center justify-center gap-1">
                {c.nameKa}
                {c.verified && <IconVerified className="text-brand-600 h-3 w-3" />}
              </p>
              <button className="chip mt-2 text-[10px]" type="button">წაშლა</button>
            </div>
          ))}
        </div>
      </section>

      {/* Users list */}
      <section className="card p-6">
        <h2 className="text-lg font-bold text-ink-900 mb-4">ყველა მომხმარებელი</h2>
        <div className="overflow-x-auto -mx-2">
          <table className="w-full text-sm">
            <thead className="text-xs muted uppercase">
              <tr>
                <th className="text-left py-2 px-2 font-medium">სახელი</th>
                <th className="text-left py-2 px-2 font-medium">ელ-ფოსტა</th>
                <th className="text-left py-2 px-2 font-medium">როლი</th>
                <th className="text-left py-2 px-2 font-medium">სტატუსი</th>
                <th className="text-left py-2 px-2 font-medium">დარეგისტრირდა</th>
              </tr>
            </thead>
            <tbody>
              {adminUsers.map((u) => (
                <tr key={u.id} className="border-t border-ink-100">
                  <td className="py-3 px-2 font-medium">{u.name}</td>
                  <td className="py-3 px-2 muted">{u.email}</td>
                  <td className="py-3 px-2 capitalize">
                    <span className="chip">{u.role}</span>
                  </td>
                  <td className="py-3 px-2">
                    <span
                      className={
                        u.status === 'approved' || u.status === 'active'
                          ? 'chip-green'
                          : u.status === 'pending'
                            ? 'chip-amber'
                            : 'chip'
                      }
                    >
                      {u.status}
                    </span>
                  </td>
                  <td className="py-3 px-2 muted">{u.joinedAt}</td>
                </tr>
              ))}
            </tbody>
          </table>
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
