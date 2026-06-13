import { NextResponse } from 'next/server';
import { getIntent, markEventSeen, updateIntent } from '@/lib/payments';

// POST /api/payments/webhook/bog
//
// BOG calls this URL after every status change on a payment. We:
//   1. verify the callback signature (HMAC SHA-256 with the shared secret)
//   2. dedupe via the event id (markEventSeen)
//   3. map BOG's status → our PaymentStatus, update the intent
//   4. on `held` (funds captured): flip the linked Order to AWAITING_CREATOR
//
// Spec: https://api.bog.ge/docs/payments/callback
//   body: { event: 'order_payment', body: { order_id, external_order_id,
//           order_status: { key: 'completed'|'rejected'|'refunded'|... } } }
//
// In dev (mock provider) we accept unsigned callbacks so the local Pay
// button on /payments/mock-bog works.

const STATUS_MAP: Record<string, 'held' | 'failed' | 'refunded'> = {
  completed: 'held',
  rejected: 'failed',
  refunded: 'refunded',
};

export async function POST(req: Request) {
  const raw = await req.text();
  let payload: unknown;
  try {
    payload = JSON.parse(raw);
  } catch {
    return NextResponse.json({ error: 'invalid json' }, { status: 400 });
  }

  // Signature verification (skip for mock).
  const isMock =
    !process.env.PAYMENTS_PROVIDER || process.env.PAYMENTS_PROVIDER === 'mock';
  if (!isMock) {
    const sig = req.headers.get('callback-signature') ?? '';
    const secret = process.env.BOG_CALLBACK_SECRET ?? '';
    if (!sig || !secret) {
      return NextResponse.json({ error: 'missing signature' }, { status: 401 });
    }
    const crypto = await import('node:crypto');
    const expected = crypto.createHmac('sha256', secret).update(raw).digest('hex');
    if (sig !== expected) {
      return NextResponse.json({ error: 'bad signature' }, { status: 401 });
    }
  }

  const p = payload as {
    event?: string;
    eventId?: string;
    paymentId?: string;
    providerRef?: string;
    body?: {
      external_order_id?: string;
      order_status?: { key?: string };
    };
  };

  const eventId = p.eventId ?? `${p.providerRef ?? p.paymentId ?? 'na'}:${Date.now()}`;
  if (!markEventSeen(eventId)) {
    return NextResponse.json({ ok: true, dedup: true });
  }

  // Mock payload (sent by /payments/mock-bog): { paymentId, status }
  const intentId =
    p.paymentId ?? (p as unknown as { paymentId: string }).paymentId;
  if (!intentId) {
    return NextResponse.json({ error: 'paymentId missing' }, { status: 400 });
  }

  const current = getIntent(intentId);
  if (!current) {
    return NextResponse.json({ error: 'intent not found' }, { status: 404 });
  }

  const incomingStatus =
    (p as unknown as { status?: string }).status ??
    STATUS_MAP[p.body?.order_status?.key ?? ''] ??
    'failed';
  const next = updateIntent(intentId, {
    status: incomingStatus as never,
    capturedAt: incomingStatus === 'held' ? new Date().toISOString() : current.capturedAt,
  });

  // Sync the DB:
  //   - update Payment.status
  //   - on 'held' → push the order from NEW to AWAITING_CREATOR (+ event row)
  if (current.orderId) {
    try {
      const { prisma } = await import('@/lib/prisma');
      await prisma.payment.update({
        where: { orderId: current.orderId },
        data: { status: incomingStatus, providerRef: current.providerRef ?? undefined },
      });
      if (incomingStatus === 'held') {
        await prisma.order.update({
          where: { id: current.orderId },
          data: {
            status: 'AWAITING_CREATOR',
            events: {
              create: {
                actor: 'system',
                type: 'status_change',
                fromStatus: 'NEW',
                toStatus: 'AWAITING_CREATOR',
                note: 'გადახდა მიღებულია, escrow-ში დაცულია',
              },
            },
          },
        });
      }
    } catch (e) {
      console.warn('[webhook] failed to sync DB:', e);
    }
  }

  return NextResponse.json({ ok: true, paymentId: intentId, status: next?.status });
}
