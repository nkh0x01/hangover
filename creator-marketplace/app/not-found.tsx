import Link from 'next/link';

export default function NotFound() {
  return (
    <section className="container-page py-24 text-center">
      <div className="text-7xl font-extrabold text-brand-600">404</div>
      <h1 className="h-section mt-4">გვერდი ვერ მოიძებნა</h1>
      <p className="muted mt-2 max-w-md mx-auto">
        გვერდი, რომელსაც ეძებ, არ არსებობს ან გადატანილია.
      </p>
      <div className="flex justify-center gap-3 mt-6">
        <Link href="/" className="btn-primary">დაბრუნდი მთავარზე</Link>
        <Link href="/marketplace" className="btn-secondary">კრეატორების ძებნა</Link>
      </div>
    </section>
  );
}
