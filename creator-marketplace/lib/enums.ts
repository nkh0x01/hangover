// SQLite has no native enums — we keep these as string unions and re-export
// runtime constants for validation.

export type Role = 'CREATOR' | 'CLIENT' | 'ADMIN';
export const Roles = { CREATOR: 'CREATOR', CLIENT: 'CLIENT', ADMIN: 'ADMIN' } as const;

export type CreatorStatus = 'PENDING' | 'APPROVED' | 'REJECTED' | 'SUSPENDED';

export type OrderStatus =
  | 'NEW'
  | 'AWAITING_CREATOR'
  | 'IN_PROGRESS'
  | 'SUBMITTED'
  | 'REVISION_REQUESTED'
  | 'COMPLETED'
  | 'CANCELLED';

export const OrderStatuses: Record<OrderStatus, { ka: string; en: string; cls: string }> = {
  NEW: { ka: 'ახალი', en: 'New', cls: 'bg-ink-100 text-ink-700' },
  AWAITING_CREATOR: { ka: 'ელოდება დადასტურებას', en: 'Awaiting creator', cls: 'bg-amber-100 text-amber-700' },
  IN_PROGRESS: { ka: 'მუშავდება', en: 'In progress', cls: 'bg-brand-100 text-brand-700' },
  SUBMITTED: { ka: 'ჩაბარდა', en: 'Submitted', cls: 'bg-sky-100 text-sky-700' },
  REVISION_REQUESTED: { ka: 'შესწორება', en: 'Revision requested', cls: 'bg-orange-100 text-orange-700' },
  COMPLETED: { ka: 'დასრულდა', en: 'Completed', cls: 'bg-emerald-100 text-emerald-700' },
  CANCELLED: { ka: 'გაუქმდა', en: 'Cancelled', cls: 'bg-red-100 text-red-700' },
};

// Allowed transitions. Used by the status-change endpoint to reject bad moves.
export const ORDER_TRANSITIONS: Record<OrderStatus, OrderStatus[]> = {
  NEW: ['AWAITING_CREATOR', 'CANCELLED'],
  AWAITING_CREATOR: ['IN_PROGRESS', 'CANCELLED'],
  IN_PROGRESS: ['SUBMITTED', 'CANCELLED'],
  SUBMITTED: ['REVISION_REQUESTED', 'COMPLETED'],
  REVISION_REQUESTED: ['IN_PROGRESS', 'CANCELLED'],
  COMPLETED: [],
  CANCELLED: [],
};

export function canTransition(from: OrderStatus, to: OrderStatus): boolean {
  return ORDER_TRANSITIONS[from]?.includes(to) ?? false;
}
