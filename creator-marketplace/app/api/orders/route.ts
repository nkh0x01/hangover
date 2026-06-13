import { NextResponse } from 'next/server';
import { orders, PLATFORM_COMMISSION_PERCENT } from '@/lib/data/orders';

export function GET(req: Request) {
  const url = new URL(req.url);
  let list = [...orders];
  const creatorId = url.searchParams.get('creatorId');
  if (creatorId) list = list.filter((o) => o.creatorId === creatorId);
  const status = url.searchParams.get('status');
  if (status) list = list.filter((o) => o.status === status);
  return NextResponse.json({ orders: list, total: list.length, commissionPercent: PLATFORM_COMMISSION_PERCENT });
}

// Stub for creating a new order (in real app: validate, persist, notify creator).
export async function POST(req: Request) {
  const body = await req.json().catch(() => ({}));
  if (!body.serviceId || !body.creatorId) {
    return NextResponse.json({ error: 'serviceId and creatorId are required' }, { status: 400 });
  }
  const price = Number(body.price ?? 0);
  const commission = Math.round((price * PLATFORM_COMMISSION_PERCENT) / 100);
  return NextResponse.json({
    ok: true,
    order: {
      id: `o-${Date.now()}`,
      ...body,
      commission,
      payout: price - commission,
      status: 'new',
      createdAt: new Date().toISOString(),
    },
  });
}
