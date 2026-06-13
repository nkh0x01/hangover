import Link from 'next/link';
import { CreatorCard } from '@/components/CreatorCard';
import { SearchBar } from '@/components/SearchBar';
import { creators } from '@/lib/data/creators';
import { categories } from '@/lib/data/categories';
import { reviews } from '@/lib/data/reviews';
import { IconBolt, IconCheck, IconShield, IconStar, IconUsers } from '@/components/Icons';

export default function HomePage() {
  const featured = creators.filter((c) => c.featured).slice(0, 6);
  const popularCategories = categories.slice(0, 8);
  const topReviews = reviews.slice(0, 3);

  return (
    <>
      {/* HERO */}
      <section className="hero-gradient">
        <div className="container-page pt-16 pb-20 sm:pt-24 sm:pb-28">
          <div className="max-w-3xl mx-auto text-center">
            <div className="inline-flex items-center gap-2 chip-brand mb-6">
              <span className="h-1.5 w-1.5 rounded-full bg-brand-600" />
              ქართული კრეატორების მარკეტფლეისი
            </div>
            <h1 className="h-display">
              იპოვე საუკეთესო კონტენტ კრეატორები{' '}
              <span className="text-brand-600">ქართული ბაზრისთვის</span>
            </h1>
            <p className="muted text-lg mt-5 leading-relaxed">
              ბიზნესს, ბრენდებსა და სტარტაპებს შეუძლიათ შეუკვეთონ ვიდეოები, TikTok-ები,
              Reels-ები, UGC კონტენტი, პროდუქტის მიმოხილვები, ფოტოსესიები და
              ინფლუენსერ კოლაბორაციები — ერთ ადგილზე.
            </p>

            <div className="mt-8 max-w-2xl mx-auto">
              <SearchBar />
            </div>

            <div className="mt-6 flex flex-col sm:flex-row items-center justify-center gap-3">
              <Link href="/marketplace" className="btn-primary px-6 py-3 text-base">
                იპოვე კრეატორი
              </Link>
              <Link href="/auth/register/creator" className="btn-secondary px-6 py-3 text-base">
                გახდი კრეატორი
              </Link>
            </div>

            <div className="mt-10 flex flex-wrap items-center justify-center gap-6 text-sm muted">
              <span className="flex items-center gap-2">
                <IconUsers className="text-brand-600" /> 247+ კრეატორი
              </span>
              <span className="flex items-center gap-2">
                <IconStar className="text-amber-500" /> 4.9 საშუალო რეიტინგი
              </span>
              <span className="flex items-center gap-2">
                <IconShield className="text-brand-600" /> Escrow გადახდები
              </span>
            </div>
          </div>
        </div>
      </section>

      {/* FEATURED CREATORS */}
      <section className="section">
        <div className="container-page">
          <div className="flex items-end justify-between mb-8">
            <div>
              <h2 className="h-section">რჩეული კრეატორები</h2>
              <p className="muted mt-1">პლატფორმის საუკეთესო, დადასტურებული კრეატორები.</p>
            </div>
            <Link href="/marketplace" className="link hidden sm:inline">
              ყველას ნახვა →
            </Link>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {featured.map((c) => (
              <CreatorCard key={c.id} creator={c} />
            ))}
          </div>
        </div>
      </section>

      {/* CATEGORIES */}
      <section className="section bg-ink-50">
        <div className="container-page">
          <div className="text-center max-w-2xl mx-auto mb-10">
            <h2 className="h-section">პოპულარული კატეგორიები</h2>
            <p className="muted mt-1">
              მოძებნე კრეატორი შენი ბრენდისთვის შესაბამის კატეგორიაში.
            </p>
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            {popularCategories.map((cat) => (
              <Link
                key={cat.id}
                href={`/marketplace?category=${cat.id}`}
                className="card p-5 hover:shadow-soft hover:-translate-y-0.5 transition group"
              >
                <div className="text-3xl mb-2">{cat.emoji}</div>
                <h3 className="font-semibold text-ink-900 group-hover:text-brand-700 transition">
                  {cat.ka}
                </h3>
                <p className="text-xs muted mt-1 line-clamp-2">{cat.description.ka}</p>
              </Link>
            ))}
          </div>
          <div className="text-center mt-8">
            <Link href="/marketplace" className="link">
              ყველა კატეგორიის ნახვა →
            </Link>
          </div>
        </div>
      </section>

      {/* HOW IT WORKS */}
      <section className="section">
        <div className="container-page">
          <div className="text-center max-w-2xl mx-auto mb-10">
            <h2 className="h-section">როგორ მუშაობს</h2>
            <p className="muted mt-1">4 მარტივი ნაბიჯი ბრენდისთვის შესაბამისი კონტენტის შესაკვეთად.</p>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {[
              { n: 1, t: 'აირჩიე კრეატორი', d: 'დაათვალიერე პროფილები, პორტფოლიო და მიმდევრების სტატისტიკა.' },
              { n: 2, t: 'შეარჩიე სერვისი', d: 'შეადარე ფასები, პაკეტები და მიწოდების ვადები.' },
              { n: 3, t: 'შეუკვეთე კონტენტი', d: 'შეავსე ბრიფი, გადაიხადე უსაფრთხო escrow-ით.' },
              { n: 4, t: 'მიიღე შედეგი', d: 'მიიღე კონტენტი, შეაფასე და გამოიყენე კამპანიაში.' },
            ].map((s) => (
              <div key={s.n} className="card p-6">
                <div className="h-10 w-10 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center font-bold">
                  {s.n}
                </div>
                <h3 className="mt-4 font-semibold text-ink-900">{s.t}</h3>
                <p className="text-sm muted mt-1">{s.d}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* TWO-COLUMN: business + creators */}
      <section className="section bg-ink-50">
        <div className="container-page grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="card p-8">
            <span className="chip-brand mb-4">ბიზნესისთვის</span>
            <h3 className="text-2xl font-bold text-ink-900">
              მოიპოვე ნამდვილი შედეგი ქართველი კრეატორებისგან
            </h3>
            <ul className="mt-5 space-y-3 text-sm">
              {[
                'ვერიფიცირებული ქართველი კრეატორები ყველა კატეგორიაში',
                'ფიქსირებული ფასები ლარში — დამატებითი მოლაპარაკების გარეშე',
                'უსაფრთხო Escrow გადახდები — თანხა მიდის მხოლოდ მიწოდების შემდეგ',
                'შიდა მესენჯერი, ბრიფი და კონტენტის მიწოდება ერთ ადგილზე',
                'საშუალო პასუხის დრო 3 საათამდე',
              ].map((b) => (
                <li key={b} className="flex items-start gap-2">
                  <span className="h-5 w-5 mt-0.5 rounded-full bg-emerald-100 text-emerald-700 inline-flex items-center justify-center shrink-0">
                    <IconCheck />
                  </span>
                  <span className="text-ink-700">{b}</span>
                </li>
              ))}
            </ul>
            <Link href="/auth/register/client" className="btn-primary mt-6">
              ბიზნეს რეგისტრაცია
            </Link>
          </div>

          <div className="card p-8">
            <span className="chip-brand mb-4">კრეატორებისთვის</span>
            <h3 className="text-2xl font-bold text-ink-900">
              გადააქციე შენი კონტენტი სტაბილურ შემოსავლად
            </h3>
            <ul className="mt-5 space-y-3 text-sm">
              {[
                'შექმენი პროფესიონალური პროფილი 10 წუთში',
                'მიიღე შეკვეთები ბრენდებისგან ყოველდღე',
                'შენ წყვეტ ფასს, კატეგორიას და ვადებს',
                'პლატფორმის საკომისიო მხოლოდ 12% — ყველაფერი გამჭვირვალე',
                'შემოსავლის გაყვანა ლარში პირდაპირ ანგარიშზე',
              ].map((b) => (
                <li key={b} className="flex items-start gap-2">
                  <span className="h-5 w-5 mt-0.5 rounded-full bg-emerald-100 text-emerald-700 inline-flex items-center justify-center shrink-0">
                    <IconCheck />
                  </span>
                  <span className="text-ink-700">{b}</span>
                </li>
              ))}
            </ul>
            <Link href="/auth/register/creator" className="btn-dark mt-6">
              გახდი კრეატორი
            </Link>
          </div>
        </div>
      </section>

      {/* TESTIMONIALS */}
      <section className="section">
        <div className="container-page">
          <div className="text-center max-w-2xl mx-auto mb-10">
            <h2 className="h-section">რას ამბობენ ჩვენი მომხმარებლები</h2>
            <p className="muted mt-1">ბრენდები, რომლებიც გვენდობიან.</p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {topReviews.map((r) => (
              <div key={r.id} className="card p-6">
                <div className="flex gap-0.5 text-amber-500 mb-3">
                  {Array.from({ length: r.rating }).map((_, i) => (
                    <IconStar key={i} />
                  ))}
                </div>
                <p className="text-ink-800 leading-relaxed">"{r.commentKa}"</p>
                <div className="flex items-center gap-3 mt-5 pt-5 border-t border-ink-100">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={r.clientAvatar}
                    alt={r.clientName}
                    className="h-10 w-10 rounded-full object-cover"
                  />
                  <div>
                    <p className="text-sm font-semibold text-ink-900">{r.clientName}</p>
                    <p className="text-xs muted">დადასტურებული კლიენტი</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="section">
        <div className="container-page">
          <div className="rounded-3xl bg-ink-900 text-white p-10 sm:p-14 overflow-hidden relative">
            <div className="absolute inset-0 opacity-30 pointer-events-none"
              style={{
                background:
                  'radial-gradient(40% 60% at 80% 30%, rgba(167,139,250,0.6), transparent), radial-gradient(50% 60% at 10% 80%, rgba(236,72,153,0.4), transparent)',
              }}
            />
            <div className="relative max-w-2xl">
              <span className="chip bg-white/10 text-white border border-white/10 mb-4">
                <IconBolt /> სტარტი 5 წუთში
              </span>
              <h3 className="text-3xl sm:text-4xl font-extrabold tracking-tight">
                მზად ხარ პირველი კამპანიისთვის?
              </h3>
              <p className="text-white/70 mt-3 text-lg">
                დარეგისტრირდი უფასოდ, აირჩიე კრეატორი და მიიღე ხარისხიანი კონტენტი
                7 დღეში.
              </p>
              <div className="flex flex-wrap gap-3 mt-6">
                <Link href="/marketplace" className="btn-primary px-6 py-3 text-base">
                  იპოვე კრეატორი
                </Link>
                <Link href="/auth/register/client" className="btn bg-white text-ink-900 hover:bg-ink-100 px-6 py-3 text-base">
                  დაიწყე უფასოდ
                </Link>
              </div>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
