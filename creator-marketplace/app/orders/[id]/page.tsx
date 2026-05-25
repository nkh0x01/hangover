import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { requireUser } from '@/lib/session';
import { getOrderWithDetails } from '@/lib/orders';
import { OrderStatuses, type OrderStatus } from '@/lib/enums';
import { formatGEL } from '@/lib/i18n';
import { OrderActions } from '@/components/OrderActions';
import { IconClock, IconShield, IconStar } from '@/components/Icons';

export const dynamic = 'force-dynamic';

export default async function OrderDetailPage({ params }: { params: { id: string } }) {
  const user = await requireUser();
  const order = await getOrderWithDetails(params.id);
  if (!order) return notFound();

  // Authz: only the order's client, creator, or an admin can view it.
  const isClient = user.role === 'CLIENT' && order.client.userId === user.id;
  const isCreator = user.role === 'CREATOR' && order.creator.userId === user.id;
  const isAdmin = user.role === 'ADMIN';
  if (!isClient && !isCreator && !isAdmin) {
    redirect('/');
  }

  const viewerRole: 'creator' | 'client' | 'admin' = isCreator ? 'creator' : isAdmin ? 'admin' : 'client';
  const status = order.status as OrderStatus;
  const meta = OrderStatuses[status];
  const addons: string[] = JSON.parse(order.addons || '[]');

  return (
    <section className="container-page py-10">
      <nav className="text-xs muted mb-3">
        <Link href={viewerRole === 'creator' ? '/dashboard/creator' : '/dashboard/client'} className="hover:text-ink-900">
          ← უკან დაშბორდზე
        </Link>
      </nav>

      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
          <p className="font-mono text-xs muted">შეკვეთა #{order.id.slice(-8).toUpperCase()}</p>
          <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink-900">
            {order.serviceTitleKa || order.service?.titleKa}
          </h1>
          <p className="muted text-sm mt-1">
            {order.creator.nameKa} ↔ {order.clientCompany ?? order.clientName}
          </p>
        </div>
        <span className={`inline-flex items-center rounded-full px-3 py-1.5 text-sm font-semibold ${meta.cls}`}>
          {meta.ka}
        </span>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6">
        <div className="space-y-6">
          {/* Status timeline */}
          <section className="card p-6">
            <h2 className="font-bold text-ink-900 mb-4">სტატუსის ისტორია</h2>
            <ol className="relative border-l border-ink-200 ml-2 space-y-5">
              {order.events.map((e) => (
                <li key={e.id} className="ml-5">
                  <span className="absolute -left-2 flex h-4 w-4 items-center justify-center rounded-full bg-brand-600 ring-4 ring-white" />
                  <p className="text-sm font-semibold text-ink-900">
                    {e.toStatus ? OrderStatuses[e.toStatus as OrderStatus]?.ka ?? e.toStatus : e.type}
                  </p>
                  <p className="text-xs muted">
                    {actorLabel(e.actor)} ·{' '}
                    {new Date(e.createdAt).toLocaleString('ka-GE', {
                      day: '2-digit',
                      month: 'short',
                      hour: '2-digit',
                      minute: '2-digit',
                    })}
                  </p>
                  {e.note && <p className="text-sm text-ink-700 mt-1">{e.note}</p>}
                </li>
              ))}
            </ol>
          </section>

          {/* Brief */}
          <section className="card p-6">
            <h2 className="font-bold text-ink-900 mb-3">ბრიფი</h2>
            <p className="text-sm text-ink-700 whitespace-pre-wrap leading-relaxed">
              {order.campaignBrief}
            </p>
            {addons.length > 0 && (
              <div className="mt-4 pt-4 border-t border-ink-100">
                <p className="text-xs muted mb-2">დამატებები:</p>
                <div className="flex flex-wrap gap-1.5">
                  {addons.map((a) => (
                    <span key={a} className="chip-brand">{a}</span>
                  ))}
                </div>
              </div>
            )}
          </section>

          {/* Deliverables */}
          {order.deliverables.length > 0 && (
            <section className="card p-6">
              <h2 className="font-bold text-ink-900 mb-3">ჩაბარებული კონტენტი</h2>
              <ul className="space-y-2">
                {order.deliverables.map((d) => (
                  <li key={d.id} className="rounded-xl border border-ink-200 p-3 flex items-center justify-between text-sm">
                    <div className="min-w-0 flex-1">
                      <p className="font-medium truncate">{d.url}</p>
                      <p className="text-xs muted">
                        {d.type} · {new Date(d.createdAt).toLocaleString('ka-GE')}
                      </p>
                    </div>
                    <a href={d.url} target="_blank" rel="noreferrer" className="link text-xs">
                      გახსნა →
                    </a>
                  </li>
                ))}
              </ul>
            </section>
          )}

          {/* Review */}
          {order.review && (
            <section className="card p-6">
              <h2 className="font-bold text-ink-900 mb-3">შეფასება</h2>
              <div className="flex gap-0.5 text-amber-500 mb-2">
                {Array.from({ length: order.review.rating }).map((_, i) => (
                  <IconStar key={i} />
                ))}
              </div>
              <p className="text-sm text-ink-700">"{order.review.comment}"</p>
            </section>
          )}

          {/* Actions */}
          <section className="card p-6">
            <h2 className="font-bold text-ink-900 mb-4">მოქმედებები</h2>
            <OrderActions
              orderId={order.id}
              paymentId={order.payment?.id}
              status={order.status}
              viewerRole={viewerRole}
              reviewed={!!order.review}
            />
          </section>
        </div>

        {/* Sidebar */}
        <aside className="space-y-4">
          <div className="card p-5">
            <h3 className="font-semibold text-ink-900 mb-3">გადახდა</h3>
            <div className="space-y-1.5 text-sm">
              <Row label="ჯამური თანხა" value={formatGEL(order.price)} bold />
              <Row label="საკომისიო" value={formatGEL(order.commission)} />
              <Row label="კრეატორზე გადასარიცხი" value={formatGEL(order.payout)} />
              <div className="pt-2 border-t border-ink-100 flex items-center gap-2 text-xs">
                <IconShield className="text-emerald-600 shrink-0" />
                <span className="text-ink-700">
                  {order.payment?.status === 'released'
                    ? 'გადარიცხულია კრეატორთან'
                    : order.payment
                      ? 'ინახება Escrow-ში'
                      : 'გადახდის სტატუსი უცნობია'}
                </span>
              </div>
            </div>
          </div>

          <div className="card p-5">
            <h3 className="font-semibold text-ink-900 mb-3">დეტალები</h3>
            <div className="space-y-2 text-sm">
              <Row
                label="ვადა"
                value={
                  order.deadline
                    ? new Date(order.deadline).toLocaleDateString('ka-GE')
                    : '—'
                }
              />
              <Row label="პრიორიტეტი" value={order.priority === 'rush' ? '⚡ სწრაფი' : 'სტანდარტული'} />
              <Row label="შექმნა" value={new Date(order.createdAt).toLocaleDateString('ka-GE')} />
            </div>
          </div>

          <div className="card p-5">
            <h3 className="font-semibold text-ink-900 mb-3">მონაწილეები</h3>
            <Link href={`/creator/${order.creator.slug}`} className="flex items-center gap-3 hover:bg-ink-50 rounded-lg p-2 -m-2">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={order.creator.avatar ?? ''} alt="" className="h-10 w-10 rounded-full" />
              <div className="min-w-0">
                <p className="text-sm font-semibold truncate">{order.creator.nameKa}</p>
                <p className="text-xs muted">კრეატორი</p>
              </div>
            </Link>
            <div className="flex items-center gap-3 mt-3 pt-3 border-t border-ink-100">
              <div className="h-10 w-10 rounded-full bg-ink-100 flex items-center justify-center font-bold text-ink-700">
                {order.clientName.charAt(0)}
              </div>
              <div className="min-w-0">
                <p className="text-sm font-semibold truncate">{order.clientName}</p>
                <p className="text-xs muted truncate">{order.clientCompany ?? 'ბიზნესი'}</p>
              </div>
            </div>
            <Link href={`/messages?conv=${order.id}`} className="btn-secondary w-full mt-4">
              💬 შეტყობინება
            </Link>
          </div>

          <div className="card p-5 flex items-start gap-2 text-xs">
            <IconClock className="text-brand-600 shrink-0 mt-0.5" />
            <p className="text-ink-700">
              მიწოდების ვადა ითვლება შეკვეთის დადასტურებიდან.
            </p>
          </div>
        </aside>
      </div>
    </section>
  );
}

function Row({ label, value, bold }: { label: string; value: string; bold?: boolean }) {
  return (
    <div className="flex justify-between">
      <span className="muted">{label}</span>
      <span className={bold ? 'font-bold text-ink-900' : 'text-ink-800'}>{value}</span>
    </div>
  );
}

function actorLabel(a: string): string {
  return a === 'creator' ? 'კრეატორი' : a === 'client' ? 'კლიენტი' : a === 'admin' ? 'ადმინი' : 'სისტემა';
}
