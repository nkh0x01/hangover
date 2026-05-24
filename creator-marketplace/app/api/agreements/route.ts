import { NextResponse } from 'next/server';
import { AGREEMENT_VERSION } from '@/lib/data/agreements';

// POST /api/agreements — persist a signed platform agreement.
// Stores user-typed signature, IP, user-agent, and version for audit trail.
// Schema lives in prisma/schema.prisma (`Agreement` model).
export async function POST(req: Request) {
  const body = await req.json().catch(() => ({}));
  const { userId, type, fullName } = body ?? {};

  if (!userId || !type || !fullName || String(fullName).trim().length < 3) {
    return NextResponse.json(
      { error: 'userId, type, and fullName (>= 3 chars) are required' },
      { status: 400 },
    );
  }
  if (type !== 'creator' && type !== 'client') {
    return NextResponse.json({ error: 'type must be creator or client' }, { status: 400 });
  }

  const ipAddress = req.headers.get('x-forwarded-for')?.split(',')[0]?.trim() ?? null;
  const userAgent = req.headers.get('user-agent') ?? null;

  // TODO: prisma.agreement.create({...})
  const record = {
    id: `agr-${Date.now()}`,
    userId,
    type,
    version: AGREEMENT_VERSION,
    fullName: String(fullName).trim(),
    acceptedAt: new Date().toISOString(),
    ipAddress,
    userAgent,
    acceptedTerms: true,
    acceptedAntiCircumvention: true,
    acceptedCommission: true,
  };

  return NextResponse.json({ ok: true, agreement: record });
}
