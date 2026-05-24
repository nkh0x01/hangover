import Link from 'next/link';

const INDUSTRIES = [
  'E-commerce / DTC',
  'სილამაზე და კოსმეტიკა',
  'მოდა',
  'რესტორანი / HoReCa',
  'ტექნოლოგია / SaaS',
  'ფინანსები / ბანკი',
  'მოგზაურობა / სასტუმრო',
  'სპორტი / ფიტნესი',
  'ჯანდაცვა',
  'განათლება',
  'სხვა',
];

export default function ClientRegisterPage() {
  return (
    <section className="container-page py-12 max-w-2xl">
      <div className="text-center mb-10">
        <span className="chip-brand mb-3">ბიზნეს რეგისტრაცია</span>
        <h1 className="text-3xl font-extrabold tracking-tight text-ink-900">დაიწყე უფასოდ</h1>
        <p className="muted mt-2">შექმენი ანგარიში და შეუკვეთე პირველი კონტენტი 5 წუთში.</p>
      </div>

      <form className="card p-6 sm:p-8 space-y-5">
        <div>
          <label className="label">სახელი *</label>
          <input className="input" placeholder="გვარი სახელი" />
        </div>
        <div>
          <label className="label">კომპანიის სახელი (არჩევითი)</label>
          <input className="input" placeholder="მაგ.: Mera Cosmetics" />
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="label">ელ-ფოსტა *</label>
            <input className="input" type="email" placeholder="you@brand.ge" />
          </div>
          <div>
            <label className="label">ტელეფონის ნომერი *</label>
            <input className="input" placeholder="+995 5XX XX XX XX" />
          </div>
        </div>
        <div>
          <label className="label">ინდუსტრია *</label>
          <select className="input">
            {INDUSTRIES.map((i) => (
              <option key={i}>{i}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="label">პაროლი *</label>
          <input className="input" type="password" placeholder="მინ. 8 სიმბოლო" />
        </div>

        <label className="flex items-start gap-2 text-sm text-ink-700">
          <input type="checkbox" className="accent-brand-600 h-4 w-4 mt-1" />
          ვეთანხმები{' '}
          <Link href="#" className="link">წესებსა და პირობებს</Link>.
        </label>

        <Link href="/auth/register/contract?type=client" className="btn-primary w-full py-3 text-base block text-center">
          შემდეგი — ხელშეკრულება
        </Link>
        <p className="text-xs muted text-center -mt-2">
          რეგისტრაცია მთავრდება პლატფორმის ხელშეკრულების ხელის მოწერით.
        </p>
        <p className="text-sm muted text-center">
          უკვე გაქვს ანგარიში?{' '}
          <Link href="/auth/login" className="link">შესვლა</Link>
        </p>
      </form>
    </section>
  );
}
