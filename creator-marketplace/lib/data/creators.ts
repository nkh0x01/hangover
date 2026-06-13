import type { Creator } from '../types';

// Sample Georgian creators — diverse, polished, marketplace-ready.
export const creators: Creator[] = [
  {
    id: 'c-001',
    slug: 'nino-beridze',
    name: 'Nino Beridze',
    nameKa: 'ნინო ბერიძე',
    avatar: 'https://i.pravatar.cc/400?img=47',
    cover:
      'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=1600&q=80&auto=format&fit=crop',
    city: 'Tbilisi',
    cityKa: 'თბილისი',
    bio: 'UGC creator and lifestyle storyteller. I make ad-style videos that look like real customer reviews — perfect for DTC brands and beauty products.',
    bioKa:
      'UGC კრეატორი და ლაიფსტაილ მთხრობელი. ვქმნი სარეკლამო ვიდეოებს, რომლებიც გამოიყურება როგორც ნამდვილი მომხმარებლის მიმოხილვა — იდეალურია D2C ბრენდებისთვის და სილამაზის პროდუქტებისთვის.',
    category: 'ugc',
    niches: ['Beauty', 'Skincare', 'Lifestyle', 'Wellness'],
    nichesKa: ['სილამაზე', 'კანის მოვლა', 'ლაიფსტაილი', 'ველნესი'],
    platforms: ['tiktok', 'instagram', 'youtube'],
    socialLinks: {
      tiktok: 'https://tiktok.com/@nino.beridze',
      instagram: 'https://instagram.com/nino.beridze',
      youtube: 'https://youtube.com/@nino-beridze',
    },
    followers: { tiktok: 58400, instagram: 42100, youtube: 7800 },
    totalFollowers: 108300,
    verified: true,
    featured: true,
    rating: 4.9,
    reviewCount: 87,
    startingPrice: 350,
    responseTimeHours: 2,
    avgDeliveryDays: 4,
    audienceDemographics: {
      genderFemalePct: 71,
      genderMalePct: 29,
      topCountries: ['Georgia', 'Azerbaijan', 'Armenia'],
      ageGroups: [
        { ageGroup: '18-24', percent: 38 },
        { ageGroup: '25-34', percent: 41 },
        { ageGroup: '35-44', percent: 15 },
        { ageGroup: '45+', percent: 6 },
      ],
    },
    portfolio: [
      {
        id: 'p-001',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1522335789203-aaa2f6d3f8d3?w=800&q=80&auto=format&fit=crop',
        title: 'Skincare brand UGC',
        titleKa: 'სკინკეარ ბრენდის UGC',
      },
      {
        id: 'p-002',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=800&q=80&auto=format&fit=crop',
        title: 'Product flatlay',
        titleKa: 'პროდუქტის ფლეტლეი',
      },
      {
        id: 'p-003',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1503236823255-94609f598e71?w=800&q=80&auto=format&fit=crop',
        title: 'Morning routine reel',
        titleKa: 'დილის რუტინის რილი',
      },
      {
        id: 'p-004',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=800&q=80&auto=format&fit=crop',
        title: 'Lifestyle shoot',
        titleKa: 'ლაიფსტაილ ფოტოსესია',
      },
    ],
    joinedAt: '2024-03-12',
    languages: ['ქართული', 'English', 'Русский'],
  },
  {
    id: 'c-002',
    slug: 'giorgi-kapanadze',
    name: 'Giorgi Kapanadze',
    nameKa: 'გიორგი კაპანაძე',
    avatar: 'https://i.pravatar.cc/400?img=12',
    cover:
      'https://images.unsplash.com/photo-1493514789931-586cb221d7a7?w=1600&q=80&auto=format&fit=crop',
    city: 'Tbilisi',
    cityKa: 'თბილისი',
    bio: 'Tech reviewer and YouTuber. Reviewing gadgets, software and Georgian startups for 4 years.',
    bioKa:
      'ტექ მიმომხილველი და YouTuber. ვაკეთებ gadget-ების, პროგრამული უზრუნველყოფისა და ქართული სტარტაპების მიმოხილვებს უკვე 4 წელია.',
    category: 'tech',
    niches: ['Gadgets', 'Software', 'Startups', 'SaaS'],
    nichesKa: ['Gadget-ები', 'პროგრამები', 'სტარტაპები', 'SaaS'],
    platforms: ['youtube', 'instagram', 'linkedin'],
    socialLinks: {
      youtube: 'https://youtube.com/@giokapanadze',
      instagram: 'https://instagram.com/giokapanadze',
      linkedin: 'https://linkedin.com/in/giokapanadze',
    },
    followers: { youtube: 84000, instagram: 18200, linkedin: 9100 },
    totalFollowers: 111300,
    verified: true,
    featured: true,
    rating: 4.8,
    reviewCount: 64,
    startingPrice: 800,
    responseTimeHours: 4,
    avgDeliveryDays: 7,
    audienceDemographics: {
      genderFemalePct: 22,
      genderMalePct: 78,
      topCountries: ['Georgia', 'Ukraine', 'Russia'],
      ageGroups: [
        { ageGroup: '18-24', percent: 24 },
        { ageGroup: '25-34', percent: 48 },
        { ageGroup: '35-44', percent: 22 },
        { ageGroup: '45+', percent: 6 },
      ],
    },
    portfolio: [
      {
        id: 'p-101',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&q=80&auto=format&fit=crop',
        title: 'iPhone 16 review',
        titleKa: 'iPhone 16-ის მიმოხილვა',
      },
      {
        id: 'p-102',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=800&q=80&auto=format&fit=crop',
        title: 'Georgian SaaS deep dive',
        titleKa: 'ქართული SaaS — ღრმა მიმოხილვა',
      },
      {
        id: 'p-103',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=800&q=80&auto=format&fit=crop',
        title: 'Studio setup',
        titleKa: 'სტუდიის სეტაპი',
      },
    ],
    joinedAt: '2024-01-09',
    languages: ['ქართული', 'English'],
  },
  {
    id: 'c-003',
    slug: 'mariam-tsereteli',
    name: 'Mariam Tsereteli',
    nameKa: 'მარიამ წერეთელი',
    avatar: 'https://i.pravatar.cc/400?img=49',
    cover:
      'https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=1600&q=80&auto=format&fit=crop',
    city: 'Batumi',
    cityKa: 'ბათუმი',
    bio: 'Travel & lifestyle creator. Showcasing the best of Georgia from Tusheti to Batumi.',
    bioKa:
      'მოგზაურობისა და ლაიფსტაილის კრეატორი. ვაჩვენებ საქართველოს — თუშეთიდან ბათუმამდე.',
    category: 'travel',
    niches: ['Travel', 'Hospitality', 'Adventure'],
    nichesKa: ['მოგზაურობა', 'სასტუმროები', 'ექსტრემალური'],
    platforms: ['instagram', 'tiktok', 'youtube'],
    socialLinks: {
      instagram: 'https://instagram.com/mariam.travels',
      tiktok: 'https://tiktok.com/@mariam.travels',
      youtube: 'https://youtube.com/@mariamtravels',
    },
    followers: { instagram: 96400, tiktok: 132000, youtube: 24000 },
    totalFollowers: 252400,
    verified: true,
    featured: true,
    rating: 5.0,
    reviewCount: 112,
    startingPrice: 600,
    responseTimeHours: 3,
    avgDeliveryDays: 6,
    audienceDemographics: {
      genderFemalePct: 58,
      genderMalePct: 42,
      topCountries: ['Georgia', 'Israel', 'Germany', 'Poland'],
      ageGroups: [
        { ageGroup: '18-24', percent: 30 },
        { ageGroup: '25-34', percent: 44 },
        { ageGroup: '35-44', percent: 19 },
        { ageGroup: '45+', percent: 7 },
      ],
    },
    portfolio: [
      {
        id: 'p-201',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1502786129293-79981df4e689?w=800&q=80&auto=format&fit=crop',
        title: 'Kazbegi sunrise',
        titleKa: 'ყაზბეგის მზის ამოსვლა',
      },
      {
        id: 'p-202',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1488646953014-85cb44e25828?w=800&q=80&auto=format&fit=crop',
        title: 'Batumi 48-hour vlog',
        titleKa: 'ბათუმი — 48 საათიანი ვლოგი',
      },
      {
        id: 'p-203',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1473625247510-8ceb1760943f?w=800&q=80&auto=format&fit=crop',
        title: 'Mountain resort campaign',
        titleKa: 'მთის კურორტის კამპანია',
      },
      {
        id: 'p-204',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=800&q=80&auto=format&fit=crop',
        title: 'Travel reel — Svaneti',
        titleKa: 'მოგზაურობის რილი — სვანეთი',
      },
    ],
    joinedAt: '2023-11-22',
    languages: ['ქართული', 'English', 'Türkçe'],
  },
  {
    id: 'c-004',
    slug: 'luka-jincharadze',
    name: 'Luka Jincharadze',
    nameKa: 'ლუკა ჯინჭარაძე',
    avatar: 'https://i.pravatar.cc/400?img=15',
    cover:
      'https://images.unsplash.com/photo-1517242810446-cc8951b2be40?w=1600&q=80&auto=format&fit=crop',
    city: 'Tbilisi',
    cityKa: 'თბილისი',
    bio: 'Food content creator covering Georgian cuisine, Tbilisi restaurants and home cooking.',
    bioKa:
      'ფუდ კრეატორი — ვაშუქებ ქართულ სამზარეულოს, თბილისის რესტორნებსა და სახლის კერძებს.',
    category: 'food',
    niches: ['Food', 'Restaurants', 'Cooking'],
    nichesKa: ['ფუდი', 'რესტორნები', 'მზარეულობა'],
    platforms: ['instagram', 'tiktok'],
    socialLinks: {
      instagram: 'https://instagram.com/luka.foodie',
      tiktok: 'https://tiktok.com/@luka.foodie',
    },
    followers: { instagram: 72100, tiktok: 195000 },
    totalFollowers: 267100,
    verified: true,
    featured: false,
    rating: 4.7,
    reviewCount: 53,
    startingPrice: 450,
    responseTimeHours: 5,
    avgDeliveryDays: 5,
    audienceDemographics: {
      genderFemalePct: 54,
      genderMalePct: 46,
      topCountries: ['Georgia', 'Russia', 'Israel'],
      ageGroups: [
        { ageGroup: '18-24', percent: 36 },
        { ageGroup: '25-34', percent: 39 },
        { ageGroup: '35-44', percent: 17 },
        { ageGroup: '45+', percent: 8 },
      ],
    },
    portfolio: [
      {
        id: 'p-301',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=800&q=80&auto=format&fit=crop',
        title: 'Khinkali shoot',
        titleKa: 'ხინკლის ფოტოსესია',
      },
      {
        id: 'p-302',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=800&q=80&auto=format&fit=crop',
        title: 'Restaurant review reel',
        titleKa: 'რესტორნის მიმოხილვის რილი',
      },
      {
        id: 'p-303',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=800&q=80&auto=format&fit=crop',
        title: 'Wine pairing series',
        titleKa: 'ღვინის სერია',
      },
    ],
    joinedAt: '2024-02-18',
    languages: ['ქართული', 'English'],
  },
  {
    id: 'c-005',
    slug: 'salome-gabunia',
    name: 'Salome Gabunia',
    nameKa: 'სალომე გაბუნია',
    avatar: 'https://i.pravatar.cc/400?img=44',
    cover:
      'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=1600&q=80&auto=format&fit=crop',
    city: 'Tbilisi',
    cityKa: 'თბილისი',
    bio: 'Fashion & beauty creator, brand collaborator, Reels specialist.',
    bioKa:
      'ფეშენ და სილამაზის კრეატორი, ბრენდის კოლაბორატორი, Reels-ის სპეციალისტი.',
    category: 'fashion',
    niches: ['Fashion', 'Beauty', 'Lifestyle'],
    nichesKa: ['მოდა', 'სილამაზე', 'ლაიფსტაილი'],
    platforms: ['instagram', 'tiktok', 'youtube'],
    socialLinks: {
      instagram: 'https://instagram.com/salome.gabunia',
      tiktok: 'https://tiktok.com/@salome.gabunia',
      youtube: 'https://youtube.com/@salomegabunia',
    },
    followers: { instagram: 184000, tiktok: 220000, youtube: 32000 },
    totalFollowers: 436000,
    verified: true,
    featured: true,
    rating: 4.9,
    reviewCount: 134,
    startingPrice: 950,
    responseTimeHours: 3,
    avgDeliveryDays: 6,
    audienceDemographics: {
      genderFemalePct: 82,
      genderMalePct: 18,
      topCountries: ['Georgia', 'Ukraine', 'Russia', 'Belarus'],
      ageGroups: [
        { ageGroup: '18-24', percent: 42 },
        { ageGroup: '25-34', percent: 39 },
        { ageGroup: '35-44', percent: 14 },
        { ageGroup: '45+', percent: 5 },
      ],
    },
    portfolio: [
      {
        id: 'p-401',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1469334031218-e382a71b716b?w=800&q=80&auto=format&fit=crop',
        title: 'Editorial shoot',
        titleKa: 'ედიტორიალ ფოტოსესია',
      },
      {
        id: 'p-402',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=800&q=80&auto=format&fit=crop',
        title: 'Brand collab — Reel',
        titleKa: 'ბრენდის კოლაბი — Reel',
      },
      {
        id: 'p-403',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=800&q=80&auto=format&fit=crop',
        title: 'Lookbook',
        titleKa: 'ლუქბუქი',
      },
      {
        id: 'p-404',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1485518882345-15568b007407?w=800&q=80&auto=format&fit=crop',
        title: 'Beauty tutorial',
        titleKa: 'სილამაზის ტუტორიალი',
      },
    ],
    joinedAt: '2023-09-05',
    languages: ['ქართული', 'English', 'Italiano'],
  },
  {
    id: 'c-006',
    slug: 'davit-meladze',
    name: 'Davit Meladze',
    nameKa: 'დავით მელაძე',
    avatar: 'https://i.pravatar.cc/400?img=33',
    cover:
      'https://images.unsplash.com/photo-1521737604893-d14cc237f11d?w=1600&q=80&auto=format&fit=crop',
    city: 'Kutaisi',
    cityKa: 'ქუთაისი',
    bio: 'Fitness coach and content creator. Workout reels, nutrition tips, supplement reviews.',
    bioKa:
      'ფიტნეს მწვრთნელი და კონტენტ კრეატორი. ვარჯიშის რილები, კვების რჩევები, დანამატების მიმოხილვები.',
    category: 'fitness',
    niches: ['Fitness', 'Nutrition', 'Wellness'],
    nichesKa: ['ფიტნესი', 'კვება', 'ველნესი'],
    platforms: ['instagram', 'youtube', 'tiktok'],
    socialLinks: {
      instagram: 'https://instagram.com/davit.fit',
      youtube: 'https://youtube.com/@davitfit',
      tiktok: 'https://tiktok.com/@davit.fit',
    },
    followers: { instagram: 41200, youtube: 12800, tiktok: 87000 },
    totalFollowers: 141000,
    verified: false,
    featured: false,
    rating: 4.6,
    reviewCount: 28,
    startingPrice: 280,
    responseTimeHours: 6,
    avgDeliveryDays: 4,
    audienceDemographics: {
      genderFemalePct: 35,
      genderMalePct: 65,
      topCountries: ['Georgia', 'Ukraine', 'Turkey'],
      ageGroups: [
        { ageGroup: '18-24', percent: 41 },
        { ageGroup: '25-34', percent: 38 },
        { ageGroup: '35-44', percent: 16 },
        { ageGroup: '45+', percent: 5 },
      ],
    },
    portfolio: [
      {
        id: 'p-501',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=800&q=80&auto=format&fit=crop',
        title: 'Gym reel',
        titleKa: 'სავარჯიშოს რილი',
      },
      {
        id: 'p-502',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1518611012118-696072aa579a?w=800&q=80&auto=format&fit=crop',
        title: 'Supplement campaign',
        titleKa: 'დანამატის კამპანია',
      },
      {
        id: 'p-503',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=800&q=80&auto=format&fit=crop',
        title: 'Outdoor training',
        titleKa: 'გარე ვარჯიში',
      },
    ],
    joinedAt: '2024-05-30',
    languages: ['ქართული', 'English', 'Русский'],
  },
  {
    id: 'c-007',
    slug: 'ana-kvaratskhelia',
    name: 'Ana Kvaratskhelia',
    nameKa: 'ანა კვარაცხელია',
    avatar: 'https://i.pravatar.cc/400?img=45',
    cover:
      'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?w=1600&q=80&auto=format&fit=crop',
    city: 'Tbilisi',
    cityKa: 'თბილისი',
    bio: 'Professional photographer. Product, brand, and lifestyle shoots in studio and on location.',
    bioKa:
      'პროფესიონალი ფოტოგრაფი. პროდუქტის, ბრენდისა და ლაიფსტაილის ფოტოსესიები სტუდიაში და გარეთ.',
    category: 'photographer',
    niches: ['Product', 'Brand', 'Lifestyle', 'E-commerce'],
    nichesKa: ['პროდუქტი', 'ბრენდი', 'ლაიფსტაილი', 'ელ-კომერცია'],
    platforms: ['instagram', 'facebook'],
    socialLinks: {
      instagram: 'https://instagram.com/ana.kphoto',
      facebook: 'https://facebook.com/anakphoto',
    },
    followers: { instagram: 28400, facebook: 6100 },
    totalFollowers: 34500,
    verified: true,
    featured: false,
    rating: 4.9,
    reviewCount: 71,
    startingPrice: 500,
    responseTimeHours: 4,
    avgDeliveryDays: 7,
    audienceDemographics: {
      genderFemalePct: 64,
      genderMalePct: 36,
      topCountries: ['Georgia'],
      ageGroups: [
        { ageGroup: '18-24', percent: 18 },
        { ageGroup: '25-34', percent: 52 },
        { ageGroup: '35-44', percent: 22 },
        { ageGroup: '45+', percent: 8 },
      ],
    },
    portfolio: [
      {
        id: 'p-601',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1503602642458-232111445657?w=800&q=80&auto=format&fit=crop',
        title: 'Watch product shoot',
        titleKa: 'საათის ფოტოსესია',
      },
      {
        id: 'p-602',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1542838132-92c53300491e?w=800&q=80&auto=format&fit=crop',
        title: 'Coffee brand',
        titleKa: 'ყავის ბრენდი',
      },
      {
        id: 'p-603',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=800&q=80&auto=format&fit=crop',
        title: 'Cosmetics flatlay',
        titleKa: 'კოსმეტიკის ფლეტლეი',
      },
      {
        id: 'p-604',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&q=80&auto=format&fit=crop',
        title: 'Tech accessory',
        titleKa: 'ტექ აქსესუარი',
      },
    ],
    joinedAt: '2023-12-01',
    languages: ['ქართული', 'English'],
  },
  {
    id: 'c-008',
    slug: 'tornike-asanidze',
    name: 'Tornike Asanidze',
    nameKa: 'თორნიკე ასანიძე',
    avatar: 'https://i.pravatar.cc/400?img=68',
    cover:
      'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=1600&q=80&auto=format&fit=crop',
    city: 'Tbilisi',
    cityKa: 'თბილისი',
    bio: 'Business & finance content creator. Helping Georgian SMEs understand startup investing and personal finance.',
    bioKa:
      'ბიზნესისა და ფინანსების კონტენტ კრეატორი. ქართულ მცირე და საშუალო ბიზნესს ვეხმარები სტარტაპებსა და პერსონალურ ფინანსებში გარკვევაში.',
    category: 'business',
    niches: ['Finance', 'Startups', 'B2B', 'SaaS'],
    nichesKa: ['ფინანსები', 'სტარტაპები', 'B2B', 'SaaS'],
    platforms: ['linkedin', 'youtube', 'instagram'],
    socialLinks: {
      linkedin: 'https://linkedin.com/in/tornike-asanidze',
      youtube: 'https://youtube.com/@tornikeasanidze',
      instagram: 'https://instagram.com/tornike.biz',
    },
    followers: { linkedin: 24500, youtube: 18000, instagram: 9200 },
    totalFollowers: 51700,
    verified: true,
    featured: false,
    rating: 4.8,
    reviewCount: 41,
    startingPrice: 750,
    responseTimeHours: 5,
    avgDeliveryDays: 7,
    audienceDemographics: {
      genderFemalePct: 31,
      genderMalePct: 69,
      topCountries: ['Georgia', 'Armenia', 'Azerbaijan'],
      ageGroups: [
        { ageGroup: '18-24', percent: 12 },
        { ageGroup: '25-34', percent: 51 },
        { ageGroup: '35-44', percent: 28 },
        { ageGroup: '45+', percent: 9 },
      ],
    },
    portfolio: [
      {
        id: 'p-701',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=800&q=80&auto=format&fit=crop',
        title: 'Startup investing 101',
        titleKa: 'სტარტაპებში ინვესტირება 101',
      },
      {
        id: 'p-702',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1559526324-4b87b5e36e44?w=800&q=80&auto=format&fit=crop',
        title: 'LinkedIn campaign',
        titleKa: 'LinkedIn კამპანია',
      },
      {
        id: 'p-703',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1556761175-b413da4baf72?w=800&q=80&auto=format&fit=crop',
        title: 'Founder interview',
        titleKa: 'დამფუძნებლის ინტერვიუ',
      },
    ],
    joinedAt: '2024-04-14',
    languages: ['ქართული', 'English'],
  },
  {
    id: 'c-009',
    slug: 'tako-shengelia',
    name: 'Tako Shengelia',
    nameKa: 'თაკო შენგელია',
    avatar: 'https://i.pravatar.cc/400?img=48',
    cover:
      'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=1600&q=80&auto=format&fit=crop',
    city: 'Tbilisi',
    cityKa: 'თბილისი',
    bio: 'Beauty creator — makeup, skincare reviews, tutorials and brand campaigns.',
    bioKa:
      'სილამაზის კრეატორი — მაკიაჟი, კანის მოვლის მიმოხილვები, ტუტორიალები და ბრენდის კამპანიები.',
    category: 'beauty',
    niches: ['Makeup', 'Skincare', 'Haircare'],
    nichesKa: ['მაკიაჟი', 'კანის მოვლა', 'თმის მოვლა'],
    platforms: ['instagram', 'tiktok', 'youtube'],
    socialLinks: {
      instagram: 'https://instagram.com/tako.beauty',
      tiktok: 'https://tiktok.com/@tako.beauty',
      youtube: 'https://youtube.com/@takobeauty',
    },
    followers: { instagram: 73400, tiktok: 145000, youtube: 19000 },
    totalFollowers: 237400,
    verified: true,
    featured: true,
    rating: 4.9,
    reviewCount: 97,
    startingPrice: 550,
    responseTimeHours: 2,
    avgDeliveryDays: 5,
    audienceDemographics: {
      genderFemalePct: 88,
      genderMalePct: 12,
      topCountries: ['Georgia', 'Russia', 'Ukraine'],
      ageGroups: [
        { ageGroup: '18-24', percent: 45 },
        { ageGroup: '25-34', percent: 36 },
        { ageGroup: '35-44', percent: 14 },
        { ageGroup: '45+', percent: 5 },
      ],
    },
    portfolio: [
      {
        id: 'p-801',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1457972729786-0411a3b2b626?w=800&q=80&auto=format&fit=crop',
        title: 'Lipstick haul',
        titleKa: 'ლიფსტიკ ჰოლი',
      },
      {
        id: 'p-802',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=800&q=80&auto=format&fit=crop',
        title: 'Beauty campaign',
        titleKa: 'სილამაზის კამპანია',
      },
      {
        id: 'p-803',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?w=800&q=80&auto=format&fit=crop',
        title: 'Tutorial — wedding makeup',
        titleKa: 'ტუტორიალი — საქორწინო მაკიაჟი',
      },
    ],
    joinedAt: '2023-10-19',
    languages: ['ქართული', 'English', 'Русский'],
  },
  {
    id: 'c-010',
    slug: 'irakli-chkhetiani',
    name: 'Irakli Chkhetiani',
    nameKa: 'ირაკლი ჩხეტიანი',
    avatar: 'https://i.pravatar.cc/400?img=11',
    cover:
      'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?w=1600&q=80&auto=format&fit=crop',
    city: 'Tbilisi',
    cityKa: 'თბილისი',
    bio: 'Videographer and editor. Cinematic brand films, event coverage, promo videos.',
    bioKa:
      'ვიდეოგრაფი და მონტაჟიორი. კინემატოგრაფიული ბრენდ ფილმები, ღონისძიებების გადაღება, პრომო ვიდეოები.',
    category: 'videographer',
    niches: ['Brand films', 'Events', 'Music videos', 'Commercials'],
    nichesKa: ['ბრენდ ფილმები', 'ღონისძიებები', 'მუსიკალური ვიდეო', 'რეკლამები'],
    platforms: ['instagram', 'youtube'],
    socialLinks: {
      instagram: 'https://instagram.com/irakli.films',
      youtube: 'https://youtube.com/@iraklifilms',
    },
    followers: { instagram: 14300, youtube: 7200 },
    totalFollowers: 21500,
    verified: false,
    featured: false,
    rating: 4.8,
    reviewCount: 33,
    startingPrice: 900,
    responseTimeHours: 6,
    avgDeliveryDays: 10,
    audienceDemographics: {
      genderFemalePct: 38,
      genderMalePct: 62,
      topCountries: ['Georgia'],
      ageGroups: [
        { ageGroup: '18-24', percent: 26 },
        { ageGroup: '25-34', percent: 47 },
        { ageGroup: '35-44', percent: 21 },
        { ageGroup: '45+', percent: 6 },
      ],
    },
    portfolio: [
      {
        id: 'p-901',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?w=800&q=80&auto=format&fit=crop',
        title: 'Brand commercial',
        titleKa: 'ბრენდის რეკლამა',
      },
      {
        id: 'p-902',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1542038784456-1ea8e935640e?w=800&q=80&auto=format&fit=crop',
        title: 'Event coverage',
        titleKa: 'ღონისძიების გადაღება',
      },
      {
        id: 'p-903',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1554941829-202a0b2403b8?w=800&q=80&auto=format&fit=crop',
        title: 'Music video',
        titleKa: 'მუსიკალური ვიდეო',
      },
    ],
    joinedAt: '2024-06-08',
    languages: ['ქართული', 'English'],
  },
  {
    id: 'c-011',
    slug: 'sopo-mgeladze',
    name: 'Sopo Mgeladze',
    nameKa: 'სოფო მღელაძე',
    avatar: 'https://i.pravatar.cc/400?img=46',
    cover:
      'https://images.unsplash.com/photo-1432888622747-4eb9a8efeb07?w=1600&q=80&auto=format&fit=crop',
    city: 'Batumi',
    cityKa: 'ბათუმი',
    bio: 'Lifestyle and family creator. Soft, warm, premium aesthetic. Great for home, parenting and wellness brands.',
    bioKa:
      'ლაიფსტაილისა და ოჯახის კრეატორი. რბილი, თბილი, პრემიუმ ესთეტიკა. შესანიშნავია სახლის, მშობელთა და ველნეს ბრენდებისთვის.',
    category: 'lifestyle',
    niches: ['Family', 'Home', 'Wellness'],
    nichesKa: ['ოჯახი', 'სახლი', 'ველნესი'],
    platforms: ['instagram', 'tiktok'],
    socialLinks: {
      instagram: 'https://instagram.com/sopo.lifestyle',
      tiktok: 'https://tiktok.com/@sopo.lifestyle',
    },
    followers: { instagram: 38100, tiktok: 64200 },
    totalFollowers: 102300,
    verified: false,
    featured: false,
    rating: 4.7,
    reviewCount: 22,
    startingPrice: 380,
    responseTimeHours: 8,
    avgDeliveryDays: 5,
    audienceDemographics: {
      genderFemalePct: 78,
      genderMalePct: 22,
      topCountries: ['Georgia', 'Russia'],
      ageGroups: [
        { ageGroup: '18-24', percent: 22 },
        { ageGroup: '25-34', percent: 49 },
        { ageGroup: '35-44', percent: 22 },
        { ageGroup: '45+', percent: 7 },
      ],
    },
    portfolio: [
      {
        id: 'p-a01',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1432888622747-4eb9a8efeb07?w=800&q=80&auto=format&fit=crop',
        title: 'Home shoot',
        titleKa: 'სახლის ფოტოსესია',
      },
      {
        id: 'p-a02',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1503944168849-8bf86875b08e?w=800&q=80&auto=format&fit=crop',
        title: 'Family reel',
        titleKa: 'ოჯახური რილი',
      },
      {
        id: 'p-a03',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1452860606245-08befc0ff44b?w=800&q=80&auto=format&fit=crop',
        title: 'Brand collaboration',
        titleKa: 'ბრენდის კოლაბორაცია',
      },
    ],
    joinedAt: '2024-08-02',
    languages: ['ქართული', 'English'],
  },
  {
    id: 'c-012',
    slug: 'beka-tatishvili',
    name: 'Beka Tatishvili',
    nameKa: 'ბექა ტატიშვილი',
    avatar: 'https://i.pravatar.cc/400?img=14',
    cover:
      'https://images.unsplash.com/photo-1611162617474-5b21e879e113?w=1600&q=80&auto=format&fit=crop',
    city: 'Tbilisi',
    cityKa: 'თბილისი',
    bio: 'TikTok creator with viral comedy and product placement experience.',
    bioKa:
      'TikTok კრეატორი ვირუსული კომედიითა და პროდუქტის განთავსების გამოცდილებით.',
    category: 'tiktok',
    niches: ['Comedy', 'Trends', 'Lifestyle'],
    nichesKa: ['კომედია', 'ტრენდები', 'ლაიფსტაილი'],
    platforms: ['tiktok', 'instagram'],
    socialLinks: {
      tiktok: 'https://tiktok.com/@bekaa',
      instagram: 'https://instagram.com/bekaa',
    },
    followers: { tiktok: 412000, instagram: 88000 },
    totalFollowers: 500000,
    verified: true,
    featured: true,
    rating: 4.8,
    reviewCount: 76,
    startingPrice: 1200,
    responseTimeHours: 3,
    avgDeliveryDays: 5,
    audienceDemographics: {
      genderFemalePct: 48,
      genderMalePct: 52,
      topCountries: ['Georgia', 'Russia', 'Ukraine', 'Armenia'],
      ageGroups: [
        { ageGroup: '18-24', percent: 54 },
        { ageGroup: '25-34', percent: 31 },
        { ageGroup: '35-44', percent: 11 },
        { ageGroup: '45+', percent: 4 },
      ],
    },
    portfolio: [
      {
        id: 'p-b01',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1611162617474-5b21e879e113?w=800&q=80&auto=format&fit=crop',
        title: 'Viral TikTok',
        titleKa: 'ვირუსული TikTok',
      },
      {
        id: 'p-b02',
        type: 'video',
        thumbnail:
          'https://images.unsplash.com/photo-1611605698335-8b1569810432?w=800&q=80&auto=format&fit=crop',
        title: 'Sponsored placement',
        titleKa: 'სპონსორირებული განთავსება',
      },
      {
        id: 'p-b03',
        type: 'image',
        thumbnail:
          'https://images.unsplash.com/photo-1612831455540-7449302a09b5?w=800&q=80&auto=format&fit=crop',
        title: 'BTS shot',
        titleKa: 'BTS კადრი',
      },
    ],
    joinedAt: '2023-07-11',
    languages: ['ქართული', 'English', 'Русский'],
  },
];

export function getCreator(slug: string) {
  return creators.find((c) => c.slug === slug);
}

export function getCreatorById(id: string) {
  return creators.find((c) => c.id === id);
}

export const cities = Array.from(new Set(creators.map((c) => c.cityKa))).sort();
