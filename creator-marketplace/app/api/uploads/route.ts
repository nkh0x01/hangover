import { NextResponse } from 'next/server';
import { getCurrentUser } from '@/lib/session';
import { saveFile } from '@/lib/storage';
import { prisma } from '@/lib/prisma';

export const runtime = 'nodejs';

// POST /api/uploads
//   multipart/form-data { file: <blob>, purpose: 'portfolio' | 'demo' | 'deliverable' | 'brief' }
//
// Returns { id, previewUrl, originalUrl, watermarked }.
// - 'portfolio' / 'demo' uploads from creators are auto-watermarked (image)
//   and a PortfolioItem row is created so they show on the creator profile.
// - 'deliverable' uploads land in private storage; only the watermarked
//   preview URL is returned. The full original is released when the order
//   is COMPLETED + paid.
// - 'brief' uploads are private files for the order brief.
const ACCEPTED = [
  'image/jpeg',
  'image/png',
  'image/webp',
  'video/mp4',
  'video/quicktime',
  'application/pdf',
];
const MAX_BYTES = 50 * 1024 * 1024; // 50 MB

export async function POST(req: Request) {
  const user = await getCurrentUser();
  if (!user) {
    return NextResponse.json({ error: 'unauthenticated' }, { status: 401 });
  }

  const form = await req.formData().catch(() => null);
  if (!form) {
    return NextResponse.json({ error: 'multipart body required' }, { status: 400 });
  }
  const file = form.get('file');
  const purpose = String(form.get('purpose') ?? 'portfolio');
  if (!(file instanceof File)) {
    return NextResponse.json({ error: 'file missing' }, { status: 400 });
  }
  if (!ACCEPTED.includes(file.type)) {
    return NextResponse.json(
      { error: `mime ${file.type} not supported` },
      { status: 415 },
    );
  }
  if (file.size > MAX_BYTES) {
    return NextResponse.json({ error: 'file too large (max 50 MB)' }, { status: 413 });
  }

  const buf = Buffer.from(await file.arrayBuffer());
  const saved = await saveFile(buf, file.type, file.name);

  // Auto-record portfolio/demo for creators.
  if ((purpose === 'portfolio' || purpose === 'demo') && user.role === 'CREATOR') {
    const creator = await prisma.creator.findUnique({ where: { userId: user.id } });
    if (creator) {
      await prisma.portfolioItem.create({
        data: {
          creatorId: creator.id,
          type: file.type.startsWith('image/') ? 'image' : 'video',
          url: saved.originalUrl,
          thumbnail: saved.previewUrl,
          title: file.name,
          titleKa: file.name,
        },
      });
    }
  }

  // For deliverables we only return the preview URL (the original stays
  // private until the order is approved + escrow released).
  if (purpose === 'deliverable') {
    return NextResponse.json({
      ok: true,
      previewUrl: saved.previewUrl,
      watermarked: saved.watermarked,
    });
  }

  return NextResponse.json({
    ok: true,
    id: saved.id,
    previewUrl: saved.previewUrl,
    originalUrl: saved.originalUrl,
    watermarked: saved.watermarked,
    size: saved.size,
    mime: saved.mime,
  });
}
