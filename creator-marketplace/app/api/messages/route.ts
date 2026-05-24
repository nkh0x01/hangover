import { NextResponse } from 'next/server';
import { conversations, getMessages } from '@/lib/data/messages';

export function GET(req: Request) {
  const url = new URL(req.url);
  const convId = url.searchParams.get('conv');
  if (convId) {
    return NextResponse.json({ messages: getMessages(convId) });
  }
  return NextResponse.json({ conversations });
}

export async function POST(req: Request) {
  const body = await req.json().catch(() => ({}));
  if (!body.conversationId || !body.text) {
    return NextResponse.json({ error: 'conversationId and text are required' }, { status: 400 });
  }
  return NextResponse.json({
    ok: true,
    message: {
      id: `m-${Date.now()}`,
      conversationId: body.conversationId,
      from: body.from ?? 'client',
      text: body.text,
      createdAt: new Date().toISOString(),
    },
  });
}
