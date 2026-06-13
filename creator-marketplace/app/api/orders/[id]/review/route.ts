import { NextResponse } from 'next/server';
import { getCurrentUser } from '@/lib/session';
import { prisma } from '@/lib/prisma';
import { transitionStatus } from '@/lib/orders';

// POST /api/orders/[id]/review  { rating: 1..5, comment }
// Client-only. Marks order COMPLETED, saves review, and triggers escrow release.
// Recomputes the creator's rating + reviewCount.
export async function POST(req: Request, { params }: { params: { id: string } }) {
  const user = await getCurrentUser();
  if (!user || user.role !== 'CLIENT') {
    return NextResponse.json({ error: 'forbidden' }, { status: 403 });
  }
  const body = await req.json().catch(() => ({}));
  const rating = Math.max(1, Math.min(5, Number(body?.rating ?? 0)));
  const comment = String(body?.comment ?? '').trim();
  if (rating < 1 || comment.length < 5) {
    return NextResponse.json({ error: 'rating + comment required' }, { status: 400 });
  }

  const order = await prisma.order.findUnique({
    where: { id: params.id },
    include: { client: true, creator: true, payment: true, review: true },
  });
  if (!order) return NextResponse.json({ error: 'order not found' }, { status: 404 });
  if (order.client.userId !== user.id) {
    return NextResponse.json({ error: 'forbidden' }, { status: 403 });
  }
  if (order.review) {
    return NextResponse.json({ error: 'review already exists' }, { status: 409 });
  }
  if (order.status !== 'SUBMITTED' && order.status !== 'COMPLETED') {
    return NextResponse.json({ error: 'review only allowed after submission' }, { status: 422 });
  }

  await prisma.review.create({
    data: {
      orderId: order.id,
      creatorId: order.creatorId,
      clientId: order.clientId,
      rating,
      comment,
    },
  });

  // Recompute creator aggregates.
  const agg = await prisma.review.aggregate({
    where: { creatorId: order.creatorId },
    _avg: { rating: true },
    _count: { _all: true },
  });
  await prisma.creator.update({
    where: { id: order.creatorId },
    data: { rating: agg._avg.rating ?? 0, reviewCount: agg._count._all },
  });

  // Flip status → COMPLETED (if not already).
  if (order.status === 'SUBMITTED') {
    try {
      await transitionStatus({
        orderId: order.id,
        to: 'COMPLETED',
        actor: 'client',
        note: 'შეფასება დატოვებულია, escrow გაიხსნა',
      });
    } catch {
      /* ignore */
    }
  }

  // Trigger escrow release.
  let released = false;
  if (order.payment) {
    try {
      const origin = req.headers.get('host')
        ? `${req.headers.get('x-forwarded-proto') ?? 'http'}://${req.headers.get('host')}`
        : '';
      const res = await fetch(`${origin}/api/payments/release`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ paymentId: order.payment.id }),
      });
      released = res.ok;
    } catch {
      /* will retry from admin if needed */
    }
  }

  return NextResponse.json({ ok: true, released });
}
