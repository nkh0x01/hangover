import Link from 'next/link';
import type { Service } from '@/lib/types';
import { formatGEL } from '@/lib/i18n';
import { IconClock } from './Icons';

export function ServiceCard({ service }: { service: Service }) {
  return (
    <Link
      href={`/service/${service.id}`}
      className="card overflow-hidden hover:-translate-y-0.5 hover:shadow-soft transition block"
    >
      <div className="aspect-video bg-ink-100 overflow-hidden">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={service.thumbnail}
          alt={service.titleKa}
          className="h-full w-full object-cover hover:scale-105 transition"
        />
      </div>
      <div className="p-4">
        <h4 className="font-semibold text-ink-900 line-clamp-2 min-h-[2.5rem]">{service.titleKa}</h4>
        <p className="text-xs muted line-clamp-2 mt-1.5 min-h-[2rem]">{service.descriptionKa}</p>
        <div className="flex items-center justify-between mt-4 pt-4 border-t border-ink-100">
          <span className="text-xs muted flex items-center gap-1">
            <IconClock /> {service.deliveryDays} დღე
          </span>
          <div className="text-right">
            <p className="text-xs muted">იწყება</p>
            <p className="text-base font-bold text-brand-700">{formatGEL(service.price)}</p>
          </div>
        </div>
      </div>
    </Link>
  );
}
