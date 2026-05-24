import Link from 'next/link';
import { categories } from '@/lib/data/categories';

const PLATFORMS = ['TikTok', 'Instagram', 'YouTube', 'Facebook', 'LinkedIn'];

export default function CreatorRegisterPage() {
  return (
    <section className="container-page py-12 max-w-3xl">
      <div className="text-center mb-10">
        <span className="chip-brand mb-3">კრეატორის რეგისტრაცია</span>
        <h1 className="text-3xl font-extrabold tracking-tight text-ink-900">გახდი კრეატორი</h1>
        <p className="muted mt-2">დააარსე შენი პროფესიული პროფილი 10 წუთში.</p>
      </div>

      <form className="card p-6 sm:p-8 space-y-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="label">სრული სახელი *</label>
            <input className="input" placeholder="გვარი სახელი" />
          </div>
          <div>
            <label className="label">ელ-ფოსტა *</label>
            <input className="input" type="email" placeholder="you@example.com" />
          </div>
          <div>
            <label className="label">ტელეფონის ნომერი *</label>
            <input className="input" placeholder="+995 5XX XX XX XX" />
          </div>
          <div>
            <label className="label">ქალაქი *</label>
            <select className="input">
              <option>თბილისი</option>
              <option>ბათუმი</option>
              <option>ქუთაისი</option>
              <option>რუსთავი</option>
              <option>გორი</option>
              <option>ზუგდიდი</option>
              <option>ფოთი</option>
            </select>
          </div>
        </div>

        <div>
          <label className="label">კატეგორია / ნიშა *</label>
          <select className="input">
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.emoji} {c.ka}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="label">ძირითადი პლატფორმები *</label>
          <div className="flex flex-wrap gap-2">
            {PLATFORMS.map((p) => (
              <label
                key={p}
                className="inline-flex items-center gap-2 rounded-xl border border-ink-200 px-3 py-2 text-sm font-medium text-ink-700 cursor-pointer hover:bg-ink-50"
              >
                <input type="checkbox" className="accent-brand-600 h-4 w-4" /> {p}
              </label>
            ))}
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="label">TikTok ბმული</label>
            <input className="input" placeholder="https://tiktok.com/@yourname" />
          </div>
          <div>
            <label className="label">Instagram ბმული</label>
            <input className="input" placeholder="https://instagram.com/yourname" />
          </div>
          <div>
            <label className="label">YouTube ბმული</label>
            <input className="input" placeholder="https://youtube.com/@yourname" />
          </div>
          <div>
            <label className="label">სხვა ბმული</label>
            <input className="input" placeholder="LinkedIn / Facebook" />
          </div>
        </div>

        <div>
          <label className="label">მოკლე ბიო *</label>
          <textarea
            className="input min-h-[100px]"
            placeholder="რას აკეთებ, რა გამოცდილება გაქვს, რა ბრენდებთან გიმუშავია..."
          />
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="label">საწყისი ფასი (₾) *</label>
            <input className="input" type="number" min={50} placeholder="350" />
          </div>
          <div>
            <label className="label">საშ. პასუხის დრო (სთ)</label>
            <input className="input" type="number" min={1} placeholder="4" />
          </div>
        </div>

        <div>
          <label className="label">პორტფოლიო (ვიდეო/ფოტო ბმულები)</label>
          <textarea
            className="input min-h-[80px]"
            placeholder="ჩამოწერე საუკეთესო 3–5 ნიმუშის ბმული — ან ატვირთე ფაილები მოგვიანებით დაშბორდიდან."
          />
        </div>

        <div>
          <label className="label">პაროლი *</label>
          <input className="input" type="password" placeholder="მინ. 8 სიმბოლო" />
        </div>

        <label className="flex items-start gap-2 text-sm text-ink-700">
          <input type="checkbox" className="accent-brand-600 h-4 w-4 mt-1" />
          ვადასტურებ, რომ ვეთანხმები{' '}
          <Link href="#" className="link">წესებსა და პირობებს</Link>.
        </label>

        <div className="flex flex-col sm:flex-row gap-3">
          <Link href="/auth/register/contract?type=creator" className="btn-primary flex-1 py-3 text-base text-center">
            შემდეგი — ხელშეკრულება
          </Link>
          <Link href="/auth/login" className="btn-secondary flex-1 py-3 text-base text-center">
            უკვე მაქვს ანგარიში
          </Link>
        </div>

        <p className="text-xs muted text-center">
          რეგისტრაცია მთავრდება პლატფორმის ხელშეკრულების ხელის მოწერით. ამის შემდეგ შენი
          პროფილი გადადის ადმინისტრაციის დასადასტურებლად — 24 საათში მიიღებ პასუხს.
        </p>
      </form>
    </section>
  );
}
