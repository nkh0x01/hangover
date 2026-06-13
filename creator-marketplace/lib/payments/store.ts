// In-process payment store. Survives only for the lifetime of the Node process
// — fine for local dev; replace with `prisma.payment.*` calls in production
// (schema is already defined in prisma/schema.prisma).

import type { PaymentIntent, PaymentStatus } from './types';

const intents = new Map<string, PaymentIntent>();

export function saveIntent(intent: PaymentIntent) {
  intents.set(intent.id, intent);
  return intent;
}

export function getIntent(id: string): PaymentIntent | null {
  return intents.get(id) ?? null;
}

export function updateIntent(
  id: string,
  patch: Partial<PaymentIntent> & { status?: PaymentStatus },
): PaymentIntent | null {
  const cur = intents.get(id);
  if (!cur) return null;
  const next = { ...cur, ...patch, updatedAt: new Date().toISOString() };
  intents.set(id, next);
  return next;
}

export function listIntents(): PaymentIntent[] {
  return Array.from(intents.values()).sort((a, b) =>
    b.createdAt.localeCompare(a.createdAt),
  );
}

// Idempotency for webhook deliveries — a provider may deliver the same
// event multiple times. We store (provider, eventId) and short-circuit if
// we've already processed it.
const seenEvents = new Set<string>();
export function markEventSeen(providerEventId: string): boolean {
  if (seenEvents.has(providerEventId)) return false;
  seenEvents.add(providerEventId);
  return true;
}
