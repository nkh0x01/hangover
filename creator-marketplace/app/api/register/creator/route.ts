import { NextResponse } from 'next/server';
import { prisma } from '@/lib/prisma';
import { hashPassword } from '@/lib/auth';
import { scanContactInfo } from '@/lib/contact-guard';

function slugify(s: string): string {
  return (
    s
      .toLowerCase()
      .normalize('NFKD')
      .replace(/[^a-z0-9\s-]/g, '')
      .trim()
      .replace(/\s+/g, '-') || `creator-${Date.now().toString(36)}`
  );
}

export async function POST(req: Request) {
  const body = await req.json().catch(() => ({}));
  const {
    name,
    email,
    password,
    phone,
    city,
    category,
    platforms = [],
    socialLinks = {},
    bio = '',
    startingPrice = 0,
    responseTimeHours = 4,
    languages = ['ქართული'],
  } = body ?? {};

  if (!name || !email || !password) {
    return NextResponse.json({ error: 'name, email, password required' }, { status: 400 });
  }
  if (String(password).length < 8) {
    return NextResponse.json({ error: 'password must be at least 8 characters' }, { status: 400 });
  }
  const bioScan = scanContactInfo(bio);
  if (bioScan.detected.phones.length || bioScan.detected.emails.length || bioScan.detected.handles.length) {
    return NextResponse.json(
      { error: 'ბიოში ნუ წერ პირად ნომერს / ელ-ფოსტას / Telegram-ს. პლატფორმა შუამავალია.' },
      { status: 422 },
    );
  }

  const normalizedEmail = String(email).toLowerCase().trim();
  const existing = await prisma.user.findUnique({ where: { email: normalizedEmail } });
  if (existing) {
    return NextResponse.json({ error: 'ეს ელ-ფოსტა უკვე დარეგისტრირებულია' }, { status: 409 });
  }

  let slug = slugify(name);
  // Ensure slug uniqueness.
  const collision = await prisma.creator.findUnique({ where: { slug } });
  if (collision) slug = `${slug}-${Date.now().toString(36).slice(-4)}`;

  const passwordHash = await hashPassword(password);
  const user = await prisma.user.create({
    data: {
      email: normalizedEmail,
      passwordHash,
      name,
      phone,
      role: 'CREATOR',
      creatorProfile: {
        create: {
          slug,
          name,
          nameKa: name,
          bio,
          bioKa: bio,
          city: city ?? 'Tbilisi',
          cityKa: city ?? 'თბილისი',
          category: category ?? 'ugc',
          niches: JSON.stringify([]),
          platforms: JSON.stringify(platforms),
          socialLinks: JSON.stringify(socialLinks),
          followers: JSON.stringify({}),
          languages: JSON.stringify(languages),
          startingPrice: Number(startingPrice) || 0,
          responseTimeHours: Number(responseTimeHours) || 4,
          status: 'PENDING',
        },
      },
    },
    include: { creatorProfile: true },
  });

  return NextResponse.json({
    ok: true,
    userId: user.id,
    creatorId: user.creatorProfile?.id,
    slug,
    nextStep: '/auth/register/contract?type=creator',
  });
}
