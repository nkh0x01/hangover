import Link from 'next/link';
import { IconUsers, IconBolt } from '@/components/Icons';

export default function RegisterChoosePage() {
  return (
    <section className="container-page py-16 max-w-3xl">
      <div className="text-center mb-10">
        <h1 className="text-3xl font-extrabold tracking-tight text-ink-900">
          შექმენი ანგარიში
        </h1>
        <p className="muted mt-2">ვინ ხარ?</p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <Link
          href="/auth/register/creator"
          className="card p-7 hover:-translate-y-1 hover:shadow-soft transition group"
        >
          <span className="h-12 w-12 rounded-2xl bg-brand-100 text-brand-700 flex items-center justify-center mb-4">
            <IconBolt />
          </span>
          <h2 className="text-xl font-bold text-ink-900">მე ვარ კრეატორი</h2>
          <p className="text-sm muted mt-1">
            შექმენი პროფესიონალური პროფილი, დაამატე სერვისები და მიიღე შეკვეთები ბრენდებისგან.
          </p>
          <span className="mt-5 inline-flex items-center text-brand-700 font-semibold text-sm group-hover:underline">
            დარეგისტრირდი როგორც კრეატორი →
          </span>
        </Link>

        <Link
          href="/auth/register/client"
          className="card p-7 hover:-translate-y-1 hover:shadow-soft transition group"
        >
          <span className="h-12 w-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center mb-4">
            <IconUsers />
          </span>
          <h2 className="text-xl font-bold text-ink-900">მე ვარ კლიენტი / ბიზნესი</h2>
          <p className="text-sm muted mt-1">
            იპოვე და შეუკვეთე საუკეთესო ქართველი კონტენტ კრეატორი თქვენი ბრენდისთვის.
          </p>
          <span className="mt-5 inline-flex items-center text-brand-700 font-semibold text-sm group-hover:underline">
            დარეგისტრირდი როგორც ბიზნესი →
          </span>
        </Link>
      </div>

      <p className="text-center text-sm muted mt-8">
        უკვე გაქვს ანგარიში?{' '}
        <Link href="/auth/login" className="link">შესვლა</Link>
      </p>
    </section>
  );
}
