import { NextResponse } from 'next/server';
import { getProvider, getIntent } from '@/lib/payments';

// POST /api/payments/release
//   body: { paymentId }
// Called when the client clicks "დაადასტურე და გადარიცხე ანაზღაურება" on the
// completed order. Triggers a payout from the platform escrow sub-account to
// the creator's IBAN, minus the 12% commission.
//
// In production: also flip Order.status → COMPLETED and create a Payout row
// with the BOG Business transfer ID for accounting.
export async function POST(req: Request) {
  const body = await req.json().catch(() => ({}));
  const { paymentId } = body ?? {};
  if (!paymentId) {
    return NextResponse.json({ error: 'paymentId required' }, { status: 400 });
  }
  const cur = getIntent(paymentId);
  if (!cur) {
    return NextResponse.json({ error: 'intent not found' }, { status: 404 });
  }
  if (cur.status !== 'held') {
    return NextResponse.json(
      { error: `cannot release intent in status ${cur.status}` },
      { status: 409 },
    );
  }
  const released = await getProvider().release(paymentId);
  return NextResponse.json({
    ok: true,
    payment: released,
    note: 'payout to creator initiated',
  });
}
