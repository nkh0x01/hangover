import { NextResponse } from 'next/server';
import { getCurrentUser } from '@/lib/session';
import { prisma } from '@/lib/prisma';
import { transitionStatus } from '@/lib/orders';
import type { OrderStatus } from '@/lib/enums';

// POST /api/orders/[id]/transition  { to: OrderStatus, note?: string }
// Authz: creator can move AWAITING_CREATOR→IN_PROGRESS, IN_PROGRESS→SUBMITTED,
//        REVISION_REQUESTED→IN_PROGRESS, *→CANCELLED.
//        Client can move SUBMITTED→COMPLETED, SUBMITTED→REVISION_REQUESTED, *→CANCELLED.
//        Admin can do anything.
export async function POST(req: Request, { params }: { params: { id: string } }) {
  const user = await getCurrentUser();
  if (!user) return NextResponse.json({ error: 'unauthenticated' }, { status: 401 });

  const body = await req.json().catch(() => ({}));
  const to = body?.to as OrderStatus;
  const note: string | undefined = body?.note;
  if (!to) return NextResponse.json({ error: 'to required' }, { status: 400 });

  const order = await prisma.order.findUnique({
    where: { id: params.id },
    include: { creator: true, client: true },
  });
  if (!order) return NextResponse.json({ error: 'order not found' }, { status: 404 });

  const isCreator = user.role === 'CREATOR' && order.creator.userId === user.id;
  const isClient = user.role === 'CLIENT' && order.client.userId === user.id;
  const isAdmin = user.role === 'ADMIN';

  if (!isCreator && !isClient && !isAdmin) {
    return NextResponse.json({ error: 'forbidden' }, { status: 403 });
  }

  // Per-actor whitelists
  const allowed: Record<'creator' | 'client' | 'admin', OrderStatus[]> = {
    creator: ['IN_PROGRESS', 'SUBMITTED', 'CANCELLED'],
    client: ['REVISION_REQUESTED', 'COMPLETED', 'CANCELLED'],
    admin: ['NEW', 'AWAITING_CREATOR', 'IN_PROGRESS', 'SUBMITTED', 'REVISION_REQUESTED', 'COMPLETED', 'CANCELLED'],
  };
  const role = isAdmin ? 'admin' : isCreator ? 'creator' : 'client';
  if (!allowed[role].includes(to)) {
    return NextResponse.json({ error: `${role} ვერ გადაიყვანს სტატუსს ${to}-ში` }, { status: 403 });
  }

  try {
    const updated = await transitionStatus({
      orderId: params.id,
      to,
      actor: role,
      actorUserId: user.id,
      note,
    });
    return NextResponse.json({ ok: true, order: updated });
  } catch (e) {
    return NextResponse.json(
      { error: e instanceof Error ? e.message : 'transition failed' },
      { status: 422 },
    );
  }
}
