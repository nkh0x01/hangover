import { NextResponse } from 'next/server';
import { getCurrentUser } from '@/lib/session';
import { prisma } from '@/lib/prisma';

export async function GET() {
  const user = await getCurrentUser();
  if (!user || user.role !== 'CREATOR') {
    return NextResponse.json({ items: [] }, { status: 401 });
  }
  const creator = await prisma.creator.findUnique({ where: { userId: user.id } });
  if (!creator) return NextResponse.json({ items: [] });
  const items = await prisma.portfolioItem.findMany({
    where: { creatorId: creator.id },
    orderBy: { createdAt: 'desc' },
  });
  return NextResponse.json({ items });
}
