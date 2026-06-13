import { NextResponse } from 'next/server';
import { getProvider, getIntent } from '@/lib/payments';

// POST /api/payments/refund
//   body: { paymentId, reason? }
// Used in dispute resolution. Admin / system can trigger a full refund
// to the client. The provider returns the money via the original card
// rail (2–5 business days for BOG).
export async function POST(req: Request) {
  const body = await req.json().catch(() => ({}));
  const { paymentId, reason } = body ?? {};
  if (!paymentId) {
    return NextResponse.json({ error: 'paymentId required' }, { status: 400 });
  }
  const cur = getIntent(paymentId);
  if (!cur) {
    return NextResponse.json({ error: 'intent not found' }, { status: 404 });
  }
  if (cur.status === 'released') {
    return NextResponse.json(
      { error: 'cannot refund already released payout' },
      { status: 409 },
    );
  }
  const refunded = await getProvider().refund(paymentId, reason);
  return NextResponse.json({ ok: true, payment: refunded });
}
