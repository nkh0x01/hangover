import { NextResponse } from 'next/server';
import { getIntent, listIntents } from '@/lib/payments';

// GET /api/payments?id=<paymentId>   — single intent
// GET /api/payments                  — list (dev only, for debugging)
export function GET(req: Request) {
  const url = new URL(req.url);
  const id = url.searchParams.get('id');
  if (id) {
    const intent = getIntent(id);
    if (!intent) return NextResponse.json({ intent: null }, { status: 404 });
    return NextResponse.json({ intent });
  }
  return NextResponse.json({ intents: listIntents() });
}
