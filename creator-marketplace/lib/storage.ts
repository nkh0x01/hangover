// Simple local filesystem-backed storage for dev. In production swap with
// signed S3/MinIO uploads — but the surface here matches.
//
// Layout under public/uploads/:
//   public/uploads/originals/<id>.<ext>     ← raw file (private, never linked publicly)
//   public/uploads/preview/<id>.<ext>       ← watermarked preview (publicly served)
//
// For images, watermarking is done with sharp (overlay + diagonal tile).
// For videos, we don't run ffmpeg in this minimal setup — we save the file
// as-is and rely on the visible <WatermarkedMedia> overlay in the UI.

import { promises as fs } from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import sharp from 'sharp';

const ROOT = path.join(process.cwd(), 'public', 'uploads');
const ORIGINALS = path.join(ROOT, 'originals');
const PREVIEW = path.join(ROOT, 'preview');

async function ensureDirs() {
  await fs.mkdir(ORIGINALS, { recursive: true });
  await fs.mkdir(PREVIEW, { recursive: true });
}

export interface SavedFile {
  id: string;
  ext: string;
  originalUrl: string;     // private, server-only
  previewUrl: string;      // public, watermarked
  watermarked: boolean;
  size: number;
  mime: string;
}

export async function saveFile(
  buffer: Buffer,
  mime: string,
  originalName: string,
): Promise<SavedFile> {
  await ensureDirs();
  const id = crypto.randomBytes(8).toString('hex');
  const ext = (path.extname(originalName) || extFromMime(mime) || '.bin').toLowerCase();
  const baseName = `${id}${ext}`;

  // Save the original (private)
  const origPath = path.join(ORIGINALS, baseName);
  await fs.writeFile(origPath, buffer);

  const previewPath = path.join(PREVIEW, baseName);
  let watermarked = false;
  if (mime.startsWith('image/')) {
    await watermarkImage(buffer, previewPath);
    watermarked = true;
  } else {
    // For non-image (video / pdf) we don't transform — store a copy
    // under preview/ so the URL surface stays consistent.
    await fs.writeFile(previewPath, buffer);
  }

  return {
    id,
    ext,
    originalUrl: `/uploads/originals/${baseName}`,
    previewUrl: `/uploads/preview/${baseName}`,
    watermarked,
    size: buffer.length,
    mime,
  };
}

function extFromMime(m: string): string | null {
  if (m === 'image/jpeg') return '.jpg';
  if (m === 'image/png') return '.png';
  if (m === 'image/webp') return '.webp';
  if (m === 'video/mp4') return '.mp4';
  if (m === 'video/quicktime') return '.mov';
  if (m === 'application/pdf') return '.pdf';
  return null;
}

async function watermarkImage(input: Buffer, outPath: string) {
  // Read intrinsic dimensions so the SVG overlay scales correctly.
  const meta = await sharp(input).metadata();
  const w = meta.width ?? 1200;
  const h = meta.height ?? 1200;

  const svg = buildWatermarkSVG(w, h);
  await sharp(input)
    .composite([{ input: Buffer.from(svg), blend: 'over' }])
    .toFile(outPath);
}

function buildWatermarkSVG(w: number, h: number): string {
  const tileFontSize = Math.max(18, Math.round(Math.min(w, h) / 28));
  const cornerFont = Math.max(16, Math.round(Math.min(w, h) / 40));
  // Diagonal repeating tile of `კრეატორები.ge` plus a center stamp.
  const tileSpacing = Math.round(tileFontSize * 9);
  const xCount = Math.ceil(w / tileSpacing) + 2;
  const yCount = Math.ceil(h / tileSpacing) + 2;
  let tiles = '';
  for (let i = 0; i < xCount; i++) {
    for (let j = 0; j < yCount; j++) {
      const x = i * tileSpacing;
      const y = j * tileSpacing;
      tiles += `<text x="${x}" y="${y}" fill="rgba(255,255,255,0.18)" font-size="${tileFontSize}" font-weight="700" transform="rotate(-30 ${x} ${y})" font-family="sans-serif">კრეატორები.ge</text>`;
    }
  }
  return `
    <svg width="${w}" height="${h}" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="g" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="rgba(0,0,0,0.0)"/>
          <stop offset="100%" stop-color="rgba(0,0,0,0.25)"/>
        </linearGradient>
      </defs>
      <rect width="100%" height="100%" fill="url(#g)"/>
      ${tiles}
      <rect x="10" y="10" width="${cornerFont * 9.5}" height="${cornerFont * 1.8}" rx="6" fill="rgba(124,58,237,0.85)"/>
      <text x="${10 + cornerFont * 0.7}" y="${10 + cornerFont * 1.3}" fill="white" font-size="${cornerFont}" font-weight="800" font-family="sans-serif">კრეატორები.ge</text>
      <text x="${w - cornerFont * 0.5}" y="${h - cornerFont * 0.5}" text-anchor="end" fill="rgba(255,255,255,0.7)" font-size="${cornerFont * 0.7}" font-family="monospace">© ${new Date().getFullYear()} · PREVIEW</text>
    </svg>
  `;
}

export const STORAGE_ROOT = ROOT;
