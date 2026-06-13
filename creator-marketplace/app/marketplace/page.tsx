import { CreatorCard } from '@/components/CreatorCard';
import { FilterSidebar } from '@/components/FilterSidebar';
import { SearchBar } from '@/components/SearchBar';
import { creators } from '@/lib/data/creators';
import { categories } from '@/lib/data/categories';

type SearchParams = {
  q?: string;
  category?: string;
  platform?: string;
  city?: string;
  audience?: string;
  maxPrice?: string;
  rating?: string;
  delivery?: string;
  verified?: string;
};

const AUDIENCE_BANDS: Record<string, [number, number]> = {
  nano: [0, 10000],
  micro: [10000, 50000],
  mid: [50000, 200000],
  macro: [200000, 1_000_000],
  mega: [1_000_000, Infinity],
};

export default function MarketplacePage({ searchParams }: { searchParams: SearchParams }) {
  let list = [...creators];

  if (searchParams.q) {
    const q = searchParams.q.toLowerCase();
    list = list.filter(
      (c) =>
        c.nameKa.toLowerCase().includes(q) ||
        c.name.toLowerCase().includes(q) ||
        c.cityKa.toLowerCase().includes(q) ||
        c.bioKa.toLowerCase().includes(q) ||
        c.nichesKa.some((n) => n.toLowerCase().includes(q)) ||
        c.niches.some((n) => n.toLowerCase().includes(q)) ||
        c.category.includes(q),
    );
  }
  if (searchParams.category) list = list.filter((c) => c.category === searchParams.category);
  if (searchParams.platform)
    list = list.filter((c) => c.platforms.includes(searchParams.platform as never));
  if (searchParams.city) list = list.filter((c) => c.cityKa === searchParams.city);
  if (searchParams.maxPrice)
    list = list.filter((c) => c.startingPrice <= Number(searchParams.maxPrice));
  if (searchParams.rating) list = list.filter((c) => c.rating >= Number(searchParams.rating));
  if (searchParams.delivery)
    list = list.filter((c) => c.avgDeliveryDays <= Number(searchParams.delivery));
  if (searchParams.verified === '1') list = list.filter((c) => c.verified);
  if (searchParams.audience && AUDIENCE_BANDS[searchParams.audience]) {
    const [min, max] = AUDIENCE_BANDS[searchParams.audience];
    list = list.filter((c) => c.totalFollowers >= min && c.totalFollowers < max);
  }

  const activeCategory = searchParams.category
    ? categories.find((c) => c.id === searchParams.category)
    : null;

  return (
    <>
      <section className="bg-gradient-to-b from-brand-50 to-white border-b border-ink-100">
        <div className="container-page py-10">
          <div className="max-w-3xl">
            <h1 className="h-section">
              {activeCategory ? `${activeCategory.emoji} ${activeCategory.ka}` : 'კრეატორების კატალოგი'}
            </h1>
            <p className="muted mt-2">
              {activeCategory
                ? activeCategory.description.ka
                : 'იპოვე საუკეთესო ქართველი კონტენტ კრეატორი შენი ბრენდისთვის.'}
            </p>
          </div>
          <div className="mt-6 max-w-3xl">
            <SearchBar />
          </div>
        </div>
      </section>

      <section className="container-page py-10 grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-8">
        <FilterSidebar />

        <div>
          <div className="flex items-center justify-between mb-5">
            <p className="muted text-sm">
              ნაპოვნია <span className="font-semibold text-ink-900">{list.length}</span>{' '}
              კრეატორი
            </p>
            <select className="input max-w-[200px] py-2 text-sm">
              <option>რეკომენდირებული</option>
              <option>დაბალი ფასი → მაღალი</option>
              <option>მაღალი ფასი → დაბალი</option>
              <option>ყველაზე მაღალი რეიტინგი</option>
              <option>ყველაზე ბევრი მიმდევარი</option>
            </select>
          </div>

          {list.length === 0 ? (
            <div className="card p-12 text-center">
              <h3 className="text-xl font-semibold text-ink-900">შედეგი არ მოიძებნა</h3>
              <p className="muted mt-2">
                სცადე ფილტრების შეცვლა ან გასუფთავება.
              </p>
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
              {list.map((c) => (
                <CreatorCard key={c.id} creator={c} />
              ))}
            </div>
          )}
        </div>
      </section>
    </>
  );
}
