import Link from 'next/link';

export function Footer() {
  return (
    <footer className="mt-20 border-t border-ink-100 bg-ink-50">
      <div className="container-page py-14 grid grid-cols-2 sm:grid-cols-4 gap-8 text-sm">
        <div className="col-span-2 sm:col-span-1">
          <div className="flex items-center gap-2 mb-3">
            <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white font-bold">
              კ
            </span>
            <span className="text-lg font-extrabold text-ink-900">
              კრეატორები<span className="text-brand-600">.</span>ge
            </span>
          </div>
          <p className="muted text-sm">
            ქართული კონტენტ კრეატორების მარკეტფლეისი — შეუკვეთე ვიდეო, UGC, ფოტო
            და ინფლუენსერ კოლაბორაცია ერთ ადგილზე.
          </p>
        </div>

        <div>
          <h4 className="font-semibold text-ink-900 mb-3">ბიზნესისთვის</h4>
          <ul className="space-y-2 text-ink-600">
            <li><Link href="/marketplace" className="hover:text-ink-900">კრეატორების ძებნა</Link></li>
            <li><Link href="/marketplace?for=business" className="hover:text-ink-900">კამპანიის შექმნა</Link></li>
            <li><Link href="/auth/register/client" className="hover:text-ink-900">ბიზნეს რეგისტრაცია</Link></li>
            <li><Link href="/faq" className="hover:text-ink-900">FAQ</Link></li>
          </ul>
        </div>

        <div>
          <h4 className="font-semibold text-ink-900 mb-3">კრეატორებისთვის</h4>
          <ul className="space-y-2 text-ink-600">
            <li><Link href="/auth/register/creator" className="hover:text-ink-900">გახდი კრეატორი</Link></li>
            <li><Link href="/dashboard/creator" className="hover:text-ink-900">კრეატორის დაშბორდი</Link></li>
            <li><Link href="/faq" className="hover:text-ink-900">როგორ ვმუშაობთ</Link></li>
          </ul>
        </div>

        <div>
          <h4 className="font-semibold text-ink-900 mb-3">კომპანია</h4>
          <ul className="space-y-2 text-ink-600">
            <li><Link href="/about" className="hover:text-ink-900">ჩვენ შესახებ</Link></li>
            <li><Link href="/contact" className="hover:text-ink-900">კონტაქტი</Link></li>
            <li><Link href="/faq" className="hover:text-ink-900">დახმარება</Link></li>
            <li><Link href="/admin" className="hover:text-ink-900">ადმინ პანელი</Link></li>
          </ul>
        </div>
      </div>
      <div className="border-t border-ink-200">
        <div className="container-page py-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs muted">
          <p>© {new Date().getFullYear()} კრეატორები.ge — ყველა უფლება დაცულია.</p>
          <div className="flex gap-4">
            <Link href="#" className="hover:text-ink-900">წესები და პირობები</Link>
            <Link href="#" className="hover:text-ink-900">კონფიდენციალურობა</Link>
          </div>
        </div>
      </div>
    </footer>
  );
}
