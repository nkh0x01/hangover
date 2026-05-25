import { NextResponse } from 'next/server';
import { getCurrentUser } from '@/lib/session';
import { prisma } from '@/lib/prisma';
import { listOrdersForClient, listOrdersForCreator } from '@/lib/orders';

// GET /api/orders/me — orders for the current user (based on role).
export async function GET() {
  const user = await getCurrentUser();
  if (!user) return NextResponse.json({ orders: [] }, { status: 401 });
  if (user.role === 'CREATOR') {
    const creator = await prisma.creator.findUnique({ where: { userId: user.id } });
    if (!creator) return NextResponse.json({ orders: [] });
    return NextResponse.json({ orders: await listOrdersForCreator(creator.id) });
  }
  if (user.role === 'CLIENT') {
    const client = await prisma.client.findUnique({ where: { userId: user.id } });
    if (!client) return NextResponse.json({ orders: [] });
    return NextResponse.json({ orders: await listOrdersForClient(client.id) });
  }
  return NextResponse.json({ orders: [] });
}
