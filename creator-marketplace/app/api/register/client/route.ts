import { NextResponse } from 'next/server';
import { prisma } from '@/lib/prisma';
import { hashPassword } from '@/lib/auth';

export async function POST(req: Request) {
  const body = await req.json().catch(() => ({}));
  const { name, email, password, phone, companyName, industry } = body ?? {};

  if (!name || !email || !password) {
    return NextResponse.json({ error: 'name, email, password required' }, { status: 400 });
  }
  if (String(password).length < 8) {
    return NextResponse.json({ error: 'password must be at least 8 characters' }, { status: 400 });
  }
  const normalizedEmail = String(email).toLowerCase().trim();
  const existing = await prisma.user.findUnique({ where: { email: normalizedEmail } });
  if (existing) {
    return NextResponse.json({ error: 'ეს ელ-ფოსტა უკვე დარეგისტრირებულია' }, { status: 409 });
  }
  const passwordHash = await hashPassword(password);
  const user = await prisma.user.create({
    data: {
      email: normalizedEmail,
      passwordHash,
      name,
      phone,
      role: 'CLIENT',
      clientProfile: { create: { name, companyName, industry, phone } },
    },
    include: { clientProfile: true },
  });
  return NextResponse.json({
    ok: true,
    userId: user.id,
    clientId: user.clientProfile?.id,
    nextStep: '/auth/register/contract?type=client',
  });
}
