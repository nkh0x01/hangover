import { NextResponse } from 'next/server';
import { services } from '@/lib/data/services';

export function GET(req: Request) {
  const url = new URL(req.url);
  let list = [...services];
  const creatorId = url.searchParams.get('creatorId');
  if (creatorId) list = list.filter((s) => s.creatorId === creatorId);
  const category = url.searchParams.get('category');
  if (category) list = list.filter((s) => s.category === category);
  return NextResponse.json({ services: list, total: list.length });
}
