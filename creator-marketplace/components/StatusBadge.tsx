import type { OrderStatus } from '@/lib/types';

const map: Record<OrderStatus, { label: string; cls: string }> = {
  new: { label: 'ახალი', cls: 'bg-ink-100 text-ink-700' },
  awaiting_creator: { label: 'ელოდება დადასტურებას', cls: 'bg-amber-100 text-amber-700' },
  in_progress: { label: 'მუშავდება', cls: 'bg-brand-100 text-brand-700' },
  submitted: { label: 'ჩაბარდა', cls: 'bg-sky-100 text-sky-700' },
  revision_requested: { label: 'შესწორება', cls: 'bg-orange-100 text-orange-700' },
  completed: { label: 'დასრულდა', cls: 'bg-emerald-100 text-emerald-700' },
  cancelled: { label: 'გაუქმდა', cls: 'bg-red-100 text-red-700' },
};

export function StatusBadge({ status }: { status: OrderStatus }) {
  const m = map[status];
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${m.cls}`}>
      {m.label}
    </span>
  );
}
