import Link from 'next/link';
import { IconBolt, IconShield, IconUsers } from '@/components/Icons';

export default function AboutPage() {
  return (
    <section className="container-page py-16">
      <div className="max-w-3xl">
        <span className="chip-brand mb-4">ჩვენ შესახებ</span>
        <h1 className="h-display">
          ვაშენებთ ქართველი კრეატორების ეკონომიკას
        </h1>
        <p className="muted text-lg mt-5 leading-relaxed">
          კრეატორები.ge არის ქართული კონტენტ კრეატორების მარკეტფლეისი — ერთი
          ადგილი, სადაც ბრენდები, ბიზნესები და ინდივიდუალური კლიენტები პოულობენ
          საუკეთესო კრეატორებს და უკვეთავენ კონტენტს გამჭვირვალე, ფიქსირებული
          ფასით.
        </p>
        <p className="muted text-lg mt-4 leading-relaxed">
          ჩვენი მისიაა — ქართველი კრეატორის შრომა გადააქცი მდგრად შემოსავლად,
          ხოლო ბრენდს მისცე საშუალება იპოვოს ის კრეატორი, ვინც მართლა ემთხვევა
          მის სამიზნე აუდიტორიას.
        </p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-5 mt-12">
        {[
          { icon: <IconUsers />, t: 'საზოგადოება', d: '247+ ვერიფიცირებული კრეატორი ყველა მთავარი კატეგორიიდან.' },
          { icon: <IconShield />, t: 'ნდობა', d: 'Escrow გადახდები — თანხა გადაირიცხება მხოლოდ მიწოდების შემდეგ.' },
          { icon: <IconBolt />, t: 'სიჩქარე', d: 'საშუალო პასუხის დრო 3 საათამდე, მიწოდება 7 დღემდე.' },
        ].map((b) => (
          <div key={b.t} className="card p-6">
            <span className="h-10 w-10 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center">
              {b.icon}
            </span>
            <h3 className="mt-4 font-bold text-ink-900">{b.t}</h3>
            <p className="text-sm muted mt-1">{b.d}</p>
          </div>
        ))}
      </div>

      <div className="card p-8 mt-12">
        <h2 className="text-xl font-bold text-ink-900">გუნდი</h2>
        <p className="muted text-sm mt-2">
          ჩვენ ვართ თბილისში დაფუძნებული გუნდი, რომელიც აერთიანებს მარკეტინგის,
          პროდუქტისა და ინჟინერიის გამოცდილებას. ერთად ვმუშაობთ Bolt, Wolt,
          Adjara Group და სხვა ქართულ კომპანიებთან.
        </p>
        <div className="flex gap-3 mt-6">
          <Link href="/contact" className="btn-primary">დაგვიკავშირდი</Link>
          <Link href="/marketplace" className="btn-secondary">ნახე კრეატორები</Link>
        </div>
      </div>
    </section>
  );
}
