import { NextResponse } from 'next/server';
import { getCurrentUser } from '@/lib/session';
import { prisma } from '@/lib/prisma';
import { submitDeliverable, transitionStatus } from '@/lib/orders';

// POST /api/orders/[id]/submit  { url, type }
// Creator-only. Attaches a deliverable + transitions order → SUBMITTED.
export async function POST(req: Request, { params }: { params: { id: string } }) {
  const user = await getCurrentUser();
  if (!user || user.role !== 'CREATOR') {
    return NextResponse.json({ error: 'forbidden' }, { status: 403 });
  }

  const body = await req.json().catch(() => ({}));
  const url = String(body?.url ?? '').trim();
  const type = String(body?.type ?? 'video');
  if (!url) return NextResponse.json({ error: 'url required' }, { status: 400 });

  const order = await prisma.order.findUnique({
    where: { id: params.id },
    include: { creator: true },
  });
  if (!order) return NextResponse.json({ error: 'order not found' }, { status: 404 });
  if (order.creator.userId !== user.id) {
    return NextResponse.json({ error: 'forbidden' }, { status: 403 });
  }

  await submitDeliverable(order.id, url, type);
  try {
    await transitionStatus({ orderId: order.id, to: 'SUBMITTED', actor: 'creator', note: 'კონტენტი ჩაბარდა' });
  } catch {
    // already submitted — that's fine
  }
  return NextResponse.json({ ok: true });
}
