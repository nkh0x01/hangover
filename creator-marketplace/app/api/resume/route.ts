import { NextResponse } from 'next/server';
import { validateResume } from '@/lib/contact-guard';

// POST /api/resume — server-side validation that mirrors the client-side check.
// In production: also persist the cleaned resume + watermarked PDF.
export async function POST(req: Request) {
  const body = await req.json().catch(() => ({}));
  const text: string = body.text ?? '';
  if (!text.trim()) {
    return NextResponse.json({ error: 'empty resume' }, { status: 400 });
  }
  const result = validateResume(text);
  if (!result.ok) {
    return NextResponse.json(
      {
        ok: false,
        error: result.reason,
        detected: result.scan.detected,
        redactions: result.scan.redactions,
      },
      { status: 422 },
    );
  }
  return NextResponse.json({ ok: true, redactions: 0 });
}
