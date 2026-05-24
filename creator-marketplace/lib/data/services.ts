import type { Service } from '../types';

export const services: Service[] = [
  // Nino Beridze (UGC)
  {
    id: 's-001',
    creatorId: 'c-001',
    title: '1 UGC video for brand',
    titleKa: '1 UGC ვიდეო ბრენდისთვის',
    description:
      'A 30–60 second authentic UGC-style video shot vertically for TikTok, Reels or ads. Includes script, shooting and edit.',
    descriptionKa:
      '30–60 წამიანი ავთენტური UGC სტილის ვიდეო, გადაღებული ვერტიკალურად TikTok-ისთვის, Reels-ისთვის ან რეკლამისთვის. შედის სცენარი, გადაღება და მონტაჟი.',
    category: 'ugc',
    price: 350,
    deliveryDays: 4,
    revisions: 2,
    includes: [
      'Script written by creator',
      'Shooting & editing',
      '1080p vertical export',
      'Royalty-free background music',
      '2 revisions',
    ],
    includesKa: [
      'სცენარი დაწერილი კრეატორის მიერ',
      'გადაღება და მონტაჟი',
      '1080p ვერტიკალური ექსპორტი',
      'უფასო ფონური მუსიკა',
      '2 შესწორება',
    ],
    requirements: [
      'Product sample sent to creator',
      'Brief with brand tone and key message',
      'Logo file (PNG/SVG)',
    ],
    requirementsKa: [
      'პროდუქტის ნიმუში გაგზავნილი კრეატორთან',
      'ბრიფი ბრენდის ტონითა და მთავარი მესიჯით',
      'ლოგო ფაილი (PNG/SVG)',
    ],
    addons: [
      { title: 'Extra 30s of footage', titleKa: 'დამატებითი 30 წმ', price: 120 },
      { title: 'Rush delivery (48h)', titleKa: 'სწრაფი მიწოდება (48სთ)', price: 150 },
      { title: 'Full usage rights (6 mo)', titleKa: 'სრული გამოყენების უფლება (6 თვე)', price: 250 },
    ],
    thumbnail:
      'https://images.unsplash.com/photo-1522335789203-aaa2f6d3f8d3?w=800&q=80&auto=format&fit=crop',
  },
  {
    id: 's-002',
    creatorId: 'c-001',
    title: 'UGC bundle — 3 videos',
    titleKa: 'UGC პაკეტი — 3 ვიდეო',
    description: '3 UGC videos with different hooks. Perfect for A/B testing ads.',
    descriptionKa: '3 UGC ვიდეო სხვადასხვა hook-ით. იდეალურია სარეკლამო A/B ტესტირებისთვის.',
    category: 'ugc',
    price: 900,
    deliveryDays: 7,
    revisions: 2,
    includes: ['3 unique videos', '3 different hooks', '2 revisions per video'],
    includesKa: ['3 უნიკალური ვიდეო', '3 სხვადასხვა hook', '2 შესწორება თითო ვიდეოზე'],
    requirements: ['Product sample', 'Creative brief'],
    requirementsKa: ['პროდუქტის ნიმუში', 'კრეატიული ბრიფი'],
    addons: [
      { title: 'Whitelisting for paid ads', titleKa: 'Whitelisting-ი ფასიანი რეკლამისთვის', price: 400 },
    ],
    thumbnail:
      'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=800&q=80&auto=format&fit=crop',
  },
  // Giorgi (Tech)
  {
    id: 's-003',
    creatorId: 'c-002',
    title: 'YouTube product review (long-form)',
    titleKa: 'YouTube პროდუქტის მიმოხილვა (გრძელი ფორმატი)',
    description:
      '7–12 minute in-depth review of your tech product or SaaS, published on the channel.',
    descriptionKa:
      '7–12 წუთიანი ღრმა მიმოხილვა თქვენი ტექ პროდუქტის ან SaaS-ის, გამოქვეყნებული არხზე.',
    category: 'youtube',
    price: 1800,
    deliveryDays: 12,
    revisions: 1,
    includes: [
      'Hands-on review',
      'Branded intro/outro',
      'Pinned link in description',
      'Cross-post to Shorts',
    ],
    includesKa: [
      'პრაქტიკული ტესტი',
      'ბრენდირებული ინტრო/აუტრო',
      'მიმაგრებული ბმული აღწერაში',
      'Shorts-ის ვერსიაც',
    ],
    requirements: ['Demo account or sample unit', 'Talking points'],
    requirementsKa: ['სატესტო ანგარიში ან ნიმუში', 'ძირითადი მესიჯები'],
    addons: [
      { title: 'Sponsored mid-roll', titleKa: 'სპონსორირებული mid-roll', price: 500 },
      { title: 'Cross-post to LinkedIn', titleKa: 'LinkedIn-ზე გადატანა', price: 200 },
    ],
    thumbnail:
      'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=800&q=80&auto=format&fit=crop',
  },
  // Mariam (Travel)
  {
    id: 's-004',
    creatorId: 'c-003',
    title: 'Hotel / destination Reel',
    titleKa: 'სასტუმროს / ლოკაციის Reel',
    description:
      'A cinematic 30s Reel showcasing your hotel, restaurant or travel destination.',
    descriptionKa:
      'კინემატოგრაფიული 30 წამიანი Reel, რომელიც წარმოაჩენს თქვენს სასტუმროს, რესტორანს ან ლოკაციას.',
    category: 'travel',
    price: 700,
    deliveryDays: 8,
    revisions: 2,
    includes: ['On-location shooting', 'Edit + color', 'Posted on @mariam.travels'],
    includesKa: ['ლოკაციაზე გადაღება', 'მონტაჟი + ფერთა კორექცია', 'პოსტი @mariam.travels-ზე'],
    requirements: ['Access to location', 'Tagging guidelines'],
    requirementsKa: ['ლოკაციაზე წვდომა', 'ტეგირების ინსტრუქცია'],
    addons: [
      { title: 'TikTok cross-post', titleKa: 'TikTok-ზე გადატანა', price: 200 },
      { title: 'Story package (5 stories)', titleKa: 'Story პაკეტი (5 სტორი)', price: 250 },
    ],
    thumbnail:
      'https://images.unsplash.com/photo-1488646953014-85cb44e25828?w=800&q=80&auto=format&fit=crop',
  },
  // Luka (Food)
  {
    id: 's-005',
    creatorId: 'c-004',
    title: 'Restaurant review reel',
    titleKa: 'რესტორნის მიმოხილვის რილი',
    description:
      'Authentic food review reel for your restaurant or cafe — published on Instagram and TikTok.',
    descriptionKa:
      'ავთენტური საკვების მიმოხილვის რილი თქვენი რესტორნისთვის ან კაფესთვის — გამოქვეყნდება Instagram-სა და TikTok-ზე.',
    category: 'food',
    price: 480,
    deliveryDays: 5,
    revisions: 1,
    includes: ['On-site visit', '1 Reel + 1 TikTok', '3 Stories'],
    includesKa: ['ვიზიტი ადგილზე', '1 Reel + 1 TikTok', '3 Story'],
    requirements: ['Free meal for shoot', 'Menu in advance'],
    requirementsKa: ['უფასო კერძი გადაღებისთვის', 'მენიუ წინასწარ'],
    addons: [
      { title: 'Extra TikTok', titleKa: 'დამატებითი TikTok', price: 180 },
    ],
    thumbnail:
      'https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=800&q=80&auto=format&fit=crop',
  },
  // Salome (Fashion)
  {
    id: 's-006',
    creatorId: 'c-005',
    title: 'Full campaign package',
    titleKa: 'სრული კამპანიის პაკეტი',
    description:
      'Premium influencer campaign: 1 Reel, 1 TikTok, 5 Stories, 3 feed photos. Published across all platforms.',
    descriptionKa:
      'პრემიუმ ინფლუენსერ კამპანია: 1 Reel, 1 TikTok, 5 Story, 3 ფიდ ფოტო. გამოქვეყნდება ყველა პლატფორმაზე.',
    category: 'fashion',
    price: 2400,
    deliveryDays: 10,
    revisions: 2,
    includes: [
      '1 Instagram Reel',
      '1 TikTok video',
      '5 Stories with link',
      '3 feed photos',
      'Cross-platform publishing',
    ],
    includesKa: [
      '1 Instagram Reel',
      '1 TikTok ვიდეო',
      '5 Story ბმულით',
      '3 ფიდ ფოტო',
      'ყველა პლატფორმაზე გამოქვეყნება',
    ],
    requirements: ['Product samples', 'Brand guidelines', 'Campaign timeline'],
    requirementsKa: ['პროდუქტის ნიმუშები', 'ბრენდის გაიდლაინი', 'კამპანიის ვადები'],
    addons: [
      { title: 'YouTube haul video', titleKa: 'YouTube haul ვიდეო', price: 700 },
      { title: 'Exclusivity (30 days)', titleKa: 'ექსკლუზიურობა (30 დღე)', price: 800 },
    ],
    thumbnail:
      'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=800&q=80&auto=format&fit=crop',
  },
  {
    id: 's-007',
    creatorId: 'c-005',
    title: '1 Instagram Reel',
    titleKa: '1 Instagram Reel',
    description: 'High-production Reel posted on Instagram.',
    descriptionKa: 'მაღალი ხარისხის Reel გამოქვეყნებული Instagram-ზე.',
    category: 'reels',
    price: 950,
    deliveryDays: 6,
    revisions: 2,
    includes: ['Concept + script', 'Shoot & edit', 'Posted with caption + tags'],
    includesKa: ['კონცეფცია + სცენარი', 'გადაღება და მონტაჟი', 'პოსტი წარწერითა და ტეგებით'],
    requirements: ['Product', 'Brand brief'],
    requirementsKa: ['პროდუქტი', 'ბრენდის ბრიფი'],
    addons: [],
    thumbnail:
      'https://images.unsplash.com/photo-1485518882345-15568b007407?w=800&q=80&auto=format&fit=crop',
  },
  // Davit (Fitness)
  {
    id: 's-008',
    creatorId: 'c-006',
    title: 'Supplement review video',
    titleKa: 'დანამატის მიმოხილვის ვიდეო',
    description: 'Honest fitness supplement / sportswear review.',
    descriptionKa: 'გულახდილი ფიტნეს დანამატის / სპორტული ტანსაცმლის მიმოხილვა.',
    category: 'fitness',
    price: 320,
    deliveryDays: 5,
    revisions: 1,
    includes: ['1 Reel + 1 TikTok', 'Honest testimonial'],
    includesKa: ['1 Reel + 1 TikTok', 'გულახდილი მიმოხილვა'],
    requirements: ['Product sample (2 week supply minimum)'],
    requirementsKa: ['პროდუქტის ნიმუში (მინ. 2 კვირის მარაგი)'],
    addons: [
      { title: 'Story takeover', titleKa: 'Story-ის ერთდღიანი takeover', price: 200 },
    ],
    thumbnail:
      'https://images.unsplash.com/photo-1518611012118-696072aa579a?w=800&q=80&auto=format&fit=crop',
  },
  // Ana (Photographer)
  {
    id: 's-009',
    creatorId: 'c-007',
    title: 'Product photoshoot — 10 photos',
    titleKa: 'პროდუქტის ფოტოსესია — 10 ფოტო',
    description: 'Studio product photography with white and lifestyle backgrounds.',
    descriptionKa: 'სტუდიური პროდუქტ ფოტოგრაფია თეთრი და ლაიფსტაილ ფონებით.',
    category: 'photographer',
    price: 650,
    deliveryDays: 7,
    revisions: 2,
    includes: ['10 final retouched photos', 'White + lifestyle backgrounds', 'Web + print sizes'],
    includesKa: ['10 საბოლოო რეტუშირებული ფოტო', 'თეთრი + ლაიფსტაილ ფონები', 'ვებ + ბეჭდვის ზომები'],
    requirements: ['Products shipped to studio'],
    requirementsKa: ['პროდუქტი მოწოდებული სტუდიაში'],
    addons: [
      { title: '+10 photos', titleKa: 'დამატებითი 10 ფოტო', price: 450 },
      { title: 'Model shots', titleKa: 'მოდელის კადრები', price: 600 },
    ],
    thumbnail:
      'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=800&q=80&auto=format&fit=crop',
  },
  // Tornike (Business)
  {
    id: 's-010',
    creatorId: 'c-008',
    title: 'LinkedIn thought-leadership post',
    titleKa: 'LinkedIn thought-leadership პოსტი',
    description: 'A high-engagement LinkedIn post about your B2B product or SaaS.',
    descriptionKa: 'მაღალი ჩართულობის LinkedIn პოსტი თქვენი B2B პროდუქტის ან SaaS-ის შესახებ.',
    category: 'business',
    price: 800,
    deliveryDays: 7,
    revisions: 2,
    includes: ['Custom-written post', 'Visual asset', 'Posted on LinkedIn'],
    includesKa: ['ინდივიდუალური დაწერილი პოსტი', 'ვიზუალური მასალა', 'პოსტი LinkedIn-ზე'],
    requirements: ['Key messaging', 'Optional case study'],
    requirementsKa: ['ძირითადი მესიჯები', 'ოპციური case study'],
    addons: [
      { title: 'YouTube short version', titleKa: 'YouTube მოკლე ვერსია', price: 400 },
    ],
    thumbnail:
      'https://images.unsplash.com/photo-1559526324-4b87b5e36e44?w=800&q=80&auto=format&fit=crop',
  },
  // Tako (Beauty)
  {
    id: 's-011',
    creatorId: 'c-009',
    title: 'Beauty product tutorial',
    titleKa: 'სილამაზის პროდუქტის ტუტორიალი',
    description: 'A tutorial video showcasing your cosmetic product in use.',
    descriptionKa: 'ტუტორიალ ვიდეო, რომელიც აჩვენებს თქვენი კოსმეტიკური პროდუქტის გამოყენებას.',
    category: 'beauty',
    price: 600,
    deliveryDays: 6,
    revisions: 2,
    includes: ['Tutorial reel', '3 Stories', 'Posted with discount code'],
    includesKa: ['ტუტორიალ რილი', '3 Story', 'პოსტი ფასდაკლების კოდით'],
    requirements: ['Product samples', 'Discount code (optional)'],
    requirementsKa: ['პროდუქტის ნიმუშები', 'ფასდაკლების კოდი (არჩევითი)'],
    addons: [
      { title: 'YouTube long version', titleKa: 'YouTube გრძელი ვერსია', price: 600 },
    ],
    thumbnail:
      'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?w=800&q=80&auto=format&fit=crop',
  },
  // Irakli (Videographer)
  {
    id: 's-012',
    creatorId: 'c-010',
    title: 'Brand film — 60s',
    titleKa: 'ბრენდ ფილმი — 60 წმ',
    description: 'Cinematic 60-second brand film for web, social and ads.',
    descriptionKa: 'კინემატოგრაფიული 60 წამიანი ბრენდ ფილმი ვებისთვის, სოციალური ქსელებისთვის და რეკლამისთვის.',
    category: 'videographer',
    price: 1500,
    deliveryDays: 14,
    revisions: 2,
    includes: ['Concept + storyboard', 'Shooting day', 'Edit + grade + sound design', 'Multiple aspect ratios'],
    includesKa: ['კონცეფცია + storyboard', 'გადაღების დღე', 'მონტაჟი + grade + ხმის დიზაინი', 'მრავალი ფორმატი'],
    requirements: ['Brand assets', 'Locations or willingness to scout'],
    requirementsKa: ['ბრენდის მასალა', 'ლოკაციები ან მზადყოფნა მოვძებნოთ ერთად'],
    addons: [
      { title: 'Extra shooting day', titleKa: 'დამატებითი გადაღების დღე', price: 1200 },
      { title: 'Drone footage', titleKa: 'დრონის კადრები', price: 400 },
    ],
    thumbnail:
      'https://images.unsplash.com/photo-1554941829-202a0b2403b8?w=800&q=80&auto=format&fit=crop',
  },
  // Beka (TikTok)
  {
    id: 's-013',
    creatorId: 'c-012',
    title: '1 TikTok video',
    titleKa: '1 TikTok ვიდეო',
    description: 'A viral-style TikTok with native product placement.',
    descriptionKa: 'ვირუსული სტილის TikTok ბუნებრივი პროდუქტის განთავსებით.',
    category: 'tiktok',
    price: 1200,
    deliveryDays: 5,
    revisions: 1,
    includes: ['Concept', 'Shoot & edit', 'Posted on @bekaa'],
    includesKa: ['კონცეფცია', 'გადაღება და მონტაჟი', 'პოსტი @bekaa-ზე'],
    requirements: ['Product', 'Brief'],
    requirementsKa: ['პროდუქტი', 'ბრიფი'],
    addons: [
      { title: 'Instagram Reel repost', titleKa: 'Instagram Reel რეპოსტი', price: 400 },
      { title: 'Pinned for 7 days', titleKa: '7 დღით მიმაგრება', price: 250 },
    ],
    thumbnail:
      'https://images.unsplash.com/photo-1611605698335-8b1569810432?w=800&q=80&auto=format&fit=crop',
  },
];

export function getServicesByCreator(creatorId: string) {
  return services.filter((s) => s.creatorId === creatorId);
}

export function getService(id: string) {
  return services.find((s) => s.id === id);
}
