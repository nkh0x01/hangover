import Link from 'next/link';
import type { Creator } from '@/lib/types';
import { formatFollowers, formatGEL } from '@/lib/i18n';
import { getCategory } from '@/lib/data/categories';
import { IconLocation, IconStar, IconVerified, PlatformIcon } from './Icons';

export function CreatorCard({ creator }: { creator: Creator }) {
  const cat = getCategory(creator.category);
  return (
    <Link
      href={`/creator/${creator.slug}`}
      className="group block card overflow-hidden hover:-translate-y-0.5 hover:shadow-soft transition"
    >
      <div className="relative aspect-[16/9] overflow-hidden bg-ink-100">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={creator.cover}
          alt={creator.nameKa}
          className="h-full w-full object-cover group-hover:scale-105 transition"
        />
        <div className="absolute top-3 right-3 flex items-center gap-1 rounded-full bg-white/90 backdrop-blur px-2 py-1 text-xs font-semibold text-ink-800 shadow">
          <IconStar className="text-amber-500" />
          {creator.rating.toFixed(1)}
          <span className="muted font-normal">({creator.reviewCount})</span>
        </div>
      </div>
      <div className="p-4">
        <div className="flex items-start gap-3">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={creator.avatar}
            alt={creator.nameKa}
            className="h-12 w-12 rounded-full object-cover ring-2 ring-white -mt-10 shadow"
          />
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-1.5">
              <h3 className="font-semibold text-ink-900 truncate">{creator.nameKa}</h3>
              {creator.verified && (
                <IconVerified className="text-brand-600 shrink-0" aria-label="verified" />
              )}
            </div>
            <p className="text-xs muted flex items-center gap-1 mt-0.5">
              <IconLocation />
              {creator.cityKa} · {cat?.ka}
            </p>
          </div>
        </div>
        <p className="text-sm text-ink-600 line-clamp-2 mt-3 min-h-[2.5rem]">{creator.bioKa}</p>

        <div className="flex items-center gap-2 mt-3 text-ink-500">
          {creator.platforms.map((p) => (
            <span key={p} title={p} className="text-ink-500">
              <PlatformIcon platform={p} />
            </span>
          ))}
          <span className="text-xs ml-auto chip">
            {formatFollowers(creator.totalFollowers)} მიმდევარი
          </span>
        </div>

        <div className="flex items-end justify-between mt-4 pt-4 border-t border-ink-100">
          <div>
            <p className="text-xs muted">იწყება</p>
            <p className="text-base font-bold text-ink-900">
              {formatGEL(creator.startingPrice)}
            </p>
          </div>
          <span className="btn-secondary group-hover:bg-brand-600 group-hover:text-white group-hover:border-brand-600 transition">
            ნახე პროფილი
          </span>
        </div>
      </div>
    </Link>
  );
}
