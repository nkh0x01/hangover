import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getService, services } from '@/lib/data/services';
import { getCreatorById } from '@/lib/data/creators';
import { CheckoutForm } from '@/components/CheckoutForm';

export function generateStaticParams() {
  return services.map((s) => ({ serviceId: s.id }));
}

export default function CheckoutPage({ params }: { params: { serviceId: string } }) {
  const service = getService(params.serviceId);
  if (!service) return notFound();
  const creator = getCreatorById(service.creatorId);
  if (!creator) return notFound();

  return (
    <section className="container-page py-10">
      <nav className="text-xs muted mb-4 flex items-center gap-2">
        <Link href={`/service/${service.id}`} className="hover:text-ink-900">
          ← უკან სერვისზე
        </Link>
      </nav>

      <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink-900 mb-2">
        შეკვეთის დადება
      </h1>
      <p className="muted">5 მარტივი ნაბიჯი — ბრიფი, ფაილები, ვადა, გადახდა.</p>

      <CheckoutForm
        service={service}
        creator={{
          name: creator.name,
          nameKa: creator.nameKa,
          avatar: creator.avatar,
          rating: creator.rating,
          responseTimeHours: creator.responseTimeHours,
        }}
      />
    </section>
  );
}
