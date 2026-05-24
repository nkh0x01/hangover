import Link from 'next/link';

export default function LoginPage() {
  return (
    <section className="container-page py-16 max-w-md">
      <div className="text-center mb-8">
        <h1 className="text-2xl font-extrabold tracking-tight text-ink-900">
          შესვლა ანგარიშში
        </h1>
        <p className="muted mt-1 text-sm">
          არ გაქვს ანგარიში?{' '}
          <Link href="/auth/register" className="link">
            დარეგისტრირდი
          </Link>
        </p>
      </div>

      <form className="card p-6 space-y-4">
        <div>
          <label className="label">ელ-ფოსტა</label>
          <input type="email" className="input" placeholder="you@example.com" />
        </div>
        <div>
          <div className="flex items-center justify-between">
            <label className="label !mb-0">პაროლი</label>
            <Link href="#" className="text-xs link">პაროლის აღდგენა</Link>
          </div>
          <input type="password" className="input mt-1.5" placeholder="••••••••" />
        </div>
        <label className="flex items-center gap-2 text-sm text-ink-700">
          <input type="checkbox" className="accent-brand-600 h-4 w-4" /> დამიმახსოვრე
        </label>
        <Link href="/dashboard/client" className="btn-primary w-full py-3 text-base block text-center">
          შესვლა
        </Link>
        <div className="relative my-2">
          <hr className="border-ink-200" />
          <span className="absolute inset-x-0 -top-2.5 text-center">
            <span className="bg-white px-2 text-xs muted">ან</span>
          </span>
        </div>
        <button type="button" className="btn-secondary w-full py-2.5">
          <span className="font-bold">G</span> შესვლა Google-ით
        </button>
      </form>
    </section>
  );
}
