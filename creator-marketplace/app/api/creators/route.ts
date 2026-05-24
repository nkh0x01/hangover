import { NextResponse } from 'next/server';
import { creators } from '@/lib/data/creators';

const AUDIENCE_BANDS: Record<string, [number, number]> = {
  nano: [0, 10000],
  micro: [10000, 50000],
  mid: [50000, 200000],
  macro: [200000, 1_000_000],
  mega: [1_000_000, Infinity],
};

export function GET(req: Request) {
  const url = new URL(req.url);
  let list = [...creators];

  const q = url.searchParams.get('q')?.toLowerCase();
  if (q) {
    list = list.filter(
      (c) =>
        c.nameKa.toLowerCase().includes(q) ||
        c.name.toLowerCase().includes(q) ||
        c.cityKa.toLowerCase().includes(q) ||
        c.bioKa.toLowerCase().includes(q) ||
        c.nichesKa.some((n) => n.toLowerCase().includes(q)) ||
        c.niches.some((n) => n.toLowerCase().includes(q)),
    );
  }
  const category = url.searchParams.get('category');
  if (category) list = list.filter((c) => c.category === category);
  const platform = url.searchParams.get('platform');
  if (platform) list = list.filter((c) => c.platforms.includes(platform as never));
  const city = url.searchParams.get('city');
  if (city) list = list.filter((c) => c.cityKa === city);
  const maxPrice = url.searchParams.get('maxPrice');
  if (maxPrice) list = list.filter((c) => c.startingPrice <= Number(maxPrice));
  const rating = url.searchParams.get('rating');
  if (rating) list = list.filter((c) => c.rating >= Number(rating));
  const verified = url.searchParams.get('verified');
  if (verified === '1') list = list.filter((c) => c.verified);
  const audience = url.searchParams.get('audience');
  if (audience && AUDIENCE_BANDS[audience]) {
    const [min, max] = AUDIENCE_BANDS[audience];
    list = list.filter((c) => c.totalFollowers >= min && c.totalFollowers < max);
  }

  return NextResponse.json({ creators: list, total: list.length });
}
