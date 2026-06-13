import type { Locale } from './types';

export const DEFAULT_LOCALE: Locale = 'ka';

export const dict = {
  brand: {
    ka: 'კრეატორები',
    en: 'Kreatorebi',
  },
  brandTagline: {
    ka: 'ქართული კონტენტ კრეატორების მარკეტფლეისი',
    en: 'Georgian creator marketplace',
  },
  nav: {
    marketplace: { ka: 'კრეატორები', en: 'Marketplace' },
    categories: { ka: 'კატეგორიები', en: 'Categories' },
    forBusiness: { ka: 'ბიზნესისთვის', en: 'For business' },
    forCreators: { ka: 'კრეატორებისთვის', en: 'For creators' },
    about: { ka: 'ჩვენ შესახებ', en: 'About' },
    contact: { ka: 'კონტაქტი', en: 'Contact' },
    faq: { ka: 'FAQ', en: 'FAQ' },
    login: { ka: 'შესვლა', en: 'Log in' },
    signup: { ka: 'რეგისტრაცია', en: 'Sign up' },
    dashboard: { ka: 'დაშბორდი', en: 'Dashboard' },
    messages: { ka: 'შეტყობინებები', en: 'Messages' },
  },
  cta: {
    findCreator: { ka: 'იპოვე კრეატორი', en: 'Find a creator' },
    becomeCreator: { ka: 'გახდი კრეატორი', en: 'Become a creator' },
    orderService: { ka: 'შეუკვეთე სერვისი', en: 'Order this service' },
    viewProfile: { ka: 'ნახე პროფილი', en: 'View profile' },
    startCollab: { ka: 'დაიწყე თანამშრომლობა', en: 'Start collaboration' },
    sendMessage: { ka: 'შეტყობინების გაგზავნა', en: 'Send message' },
    contactCreator: { ka: 'დაუკავშირდი კრეატორს', en: 'Contact creator' },
    requestQuote: { ka: 'მოითხოვე შეთავაზება', en: 'Request quote' },
    payNow: { ka: 'გადახდა', en: 'Pay now' },
    seeAll: { ka: 'ყველას ნახვა', en: 'See all' },
  },
  search: {
    placeholder: {
      ka: 'მოძებნე კრეატორი, კატეგორია, ქალაქი, ნიშა...',
      en: 'Search creators, category, city, niche...',
    },
    button: { ka: 'ძებნა', en: 'Search' },
  },
  homepage: {
    heroTitle: {
      ka: 'იპოვე საუკეთესო კონტენტ კრეატორები ქართული ბაზრისთვის',
      en: 'Find the best content creators for the Georgian market',
    },
    heroSubtitle: {
      ka: 'ბიზნესს, ბრენდებსა და სტარტაპებს შეუძლიათ შეუკვეთონ ვიდეოები, TikTok-ები, Reels-ები, UGC კონტენტი, პროდუქტის მიმოხილვები, ფოტოსესიები და ინფლუენსერ კოლაბორაციები — ერთ ადგილზე.',
      en: 'Brands, businesses and startups can order videos, TikToks, Reels, UGC content, product reviews, photoshoots and influencer collaborations — all in one place.',
    },
    featuredCreators: { ka: 'რჩეული კრეატორები', en: 'Featured creators' },
    popularCategories: { ka: 'პოპულარული კატეგორიები', en: 'Popular categories' },
    howItWorks: { ka: 'როგორ მუშაობს', en: 'How it works' },
    forBusiness: { ka: 'ბიზნესისთვის', en: 'For business' },
    forCreators: { ka: 'კრეატორებისთვის', en: 'For creators' },
    testimonials: { ka: 'რას ამბობენ ჩვენი მომხმარებლები', en: 'What our users say' },
    steps: [
      { ka: 'აირჩიე კრეატორი', en: 'Choose a creator' },
      { ka: 'შეარჩიე სერვისი', en: 'Pick a service' },
      { ka: 'შეუკვეთე კონტენტი', en: 'Order content' },
      { ka: 'მიიღე შედეგი', en: 'Get the result' },
    ],
  },
  filters: {
    title: { ka: 'ფილტრები', en: 'Filters' },
    category: { ka: 'კატეგორია', en: 'Category' },
    platform: { ka: 'პლატფორმა', en: 'Platform' },
    city: { ka: 'ქალაქი', en: 'City' },
    priceRange: { ka: 'ფასი (₾)', en: 'Price range (₾)' },
    rating: { ka: 'შეფასება', en: 'Rating' },
    audienceSize: { ka: 'აუდიტორიის ზომა', en: 'Audience size' },
    niche: { ka: 'ნიშა', en: 'Niche' },
    deliveryTime: { ka: 'მიწოდების ვადა', en: 'Delivery time' },
    verifiedOnly: { ka: 'მხოლოდ ვერიფიცირებული', en: 'Verified only' },
    clear: { ka: 'გასუფთავება', en: 'Clear' },
    apply: { ka: 'გამოყენება', en: 'Apply' },
  },
  creator: {
    verified: { ka: 'დადასტურებული კრეატორი', en: 'Verified creator' },
    followers: { ka: 'მიმდევრები', en: 'Followers' },
    startingFrom: { ka: 'იწყება', en: 'Starting from' },
    deliveryIn: { ka: 'მიწოდება', en: 'Delivery in' },
    days: { ka: 'დღე', en: 'days' },
    responseTime: { ka: 'პასუხის დრო', en: 'Response time' },
    hours: { ka: 'სთ', en: 'h' },
    portfolio: { ka: 'პორტფოლიო', en: 'Portfolio' },
    services: { ka: 'სერვისები', en: 'Services' },
    reviews: { ka: 'შეფასებები', en: 'Reviews' },
    aboutCreator: { ka: 'კრეატორის შესახებ', en: 'About creator' },
    platforms: { ka: 'პლატფორმები', en: 'Platforms' },
    languages: { ka: 'ენები', en: 'Languages' },
    audienceDemographics: { ka: 'აუდიტორიის დემოგრაფია', en: 'Audience demographics' },
    revisions: { ka: 'შესწორება', en: 'revisions' },
    whatsIncluded: { ka: 'რას მოიცავს', en: "What's included" },
    requirements: { ka: 'მოთხოვნები კლიენტისგან', en: 'Requirements from client' },
    addOns: { ka: 'დამატებები', en: 'Add-ons' },
  },
  order: {
    title: { ka: 'შეკვეთის დადება', en: 'Place an order' },
    selectPackage: { ka: 'აირჩიე პაკეტი', en: 'Select package' },
    brief: { ka: 'კამპანიის ბრიფი', en: 'Campaign brief' },
    briefPlaceholder: {
      ka: 'აღწერე პროდუქტი, სამიზნე აუდიტორია, ტონი, ძირითადი მესიჯი...',
      en: 'Describe the product, target audience, tone, key message...',
    },
    files: { ka: 'პროდუქტის ფაილები / ბმულები', en: 'Product files / links' },
    deadline: { ka: 'ვადა', en: 'Deadline' },
    summary: { ka: 'შეჯამება', en: 'Summary' },
    basePrice: { ka: 'საბაზისო ფასი', en: 'Base price' },
    addons: { ka: 'დამატებები', en: 'Add-ons' },
    commission: { ka: 'პლატფორმის საკომისიო', en: 'Platform fee' },
    total: { ka: 'ჯამი', en: 'Total' },
    paymentMethod: { ka: 'გადახდის მეთოდი', en: 'Payment method' },
    escrowNote: {
      ka: 'თანხა ინახება ეskროუ-ში — კრეატორი იღებს ანაზღაურებას მხოლოდ ნამუშევრის დადასტურების შემდეგ.',
      en: 'Funds are held in escrow — the creator is paid only after you approve the deliverables.',
    },
    statuses: {
      new: { ka: 'ახალი შეკვეთა', en: 'New order' },
      awaiting_creator: { ka: 'ელოდება კრეატორის დადასტურებას', en: 'Awaiting creator approval' },
      in_progress: { ka: 'მუშავდება', en: 'In progress' },
      submitted: { ka: 'კონტენტი ჩაბარდა', en: 'Content submitted' },
      revision_requested: { ka: 'მოთხოვნილია შესწორება', en: 'Revision requested' },
      completed: { ka: 'დასრულებული', en: 'Completed' },
      cancelled: { ka: 'გაუქმებული', en: 'Cancelled' },
    },
  },
  dashboard: {
    creator: {
      title: { ka: 'კრეატორის დაშბორდი', en: 'Creator dashboard' },
      myProfile: { ka: 'ჩემი პროფილი', en: 'My profile' },
      myServices: { ka: 'ჩემი სერვისები', en: 'My services' },
      portfolio: { ka: 'პორტფოლიო', en: 'Portfolio' },
      orders: { ka: 'შეკვეთები', en: 'Orders' },
      earnings: { ka: 'შემოსავალი', en: 'Earnings' },
      reviews: { ka: 'შეფასებები', en: 'Reviews' },
      analytics: { ka: 'ანალიტიკა', en: 'Analytics' },
      messages: { ka: 'შეტყობინებები', en: 'Messages' },
      acceptOrder: { ka: 'დადასტურება', en: 'Accept' },
      rejectOrder: { ka: 'უარყოფა', en: 'Reject' },
      submitContent: { ka: 'კონტენტის ჩაბარება', en: 'Submit content' },
    },
    client: {
      title: { ka: 'კლიენტის დაშბორდი', en: 'Client dashboard' },
      myOrders: { ka: 'ჩემი შეკვეთები', en: 'My orders' },
      activeOrders: { ka: 'მიმდინარე შეკვეთები', en: 'Active orders' },
      completedOrders: { ka: 'დასრულებული შეკვეთები', en: 'Completed orders' },
      savedCreators: { ka: 'შენახული კრეატორები', en: 'Saved creators' },
      messages: { ka: 'შეტყობინებები', en: 'Messages' },
      briefs: { ka: 'ბრიფები', en: 'Briefs' },
      leaveReview: { ka: 'შეფასების დატოვება', en: 'Leave review' },
      downloadContent: { ka: 'კონტენტის ჩამოტვირთვა', en: 'Download content' },
      reorder: { ka: 'თავიდან შეკვეთა', en: 'Reorder' },
    },
    admin: {
      title: { ka: 'ადმინ პანელი', en: 'Admin panel' },
      users: { ka: 'მომხმარებლები', en: 'Users' },
      pendingCreators: { ka: 'დასადასტურებელი კრეატორები', en: 'Pending creators' },
      orders: { ka: 'შეკვეთები', en: 'Orders' },
      categories: { ka: 'კატეგორიები', en: 'Categories' },
      disputes: { ka: 'დავები', en: 'Disputes' },
      commission: { ka: 'პლატფორმის შემოსავალი', en: 'Platform revenue' },
      featured: { ka: 'რჩეული კრეატორები', en: 'Featured creators' },
      moderation: { ka: 'მოდერაცია', en: 'Moderation' },
      approve: { ka: 'დადასტურება', en: 'Approve' },
      reject: { ka: 'უარყოფა', en: 'Reject' },
      verify: { ka: 'ვერიფიკაცია', en: 'Verify' },
    },
  },
  auth: {
    loginTitle: { ka: 'შესვლა ანგარიშში', en: 'Log in to your account' },
    signupTitle: { ka: 'შექმენი ანგარიში', en: 'Create your account' },
    chooseRole: { ka: 'ვინ ხარ?', en: 'Who are you?' },
    iAmCreator: { ka: 'მე ვარ კრეატორი', en: 'I am a Creator' },
    iAmClient: { ka: 'მე ვარ კლიენტი / ბიზნესი', en: 'I am a Client / Business' },
    email: { ka: 'ელ-ფოსტა', en: 'Email' },
    password: { ka: 'პაროლი', en: 'Password' },
    fullName: { ka: 'სრული სახელი', en: 'Full name' },
    phone: { ka: 'ტელეფონის ნომერი', en: 'Phone number' },
    city: { ka: 'ქალაქი', en: 'City' },
    companyName: { ka: 'კომპანიის სახელი (არჩევითი)', en: 'Company name (optional)' },
    industry: { ka: 'ინდუსტრია', en: 'Industry' },
    bio: { ka: 'მოკლე ბიო', en: 'Short bio' },
    portfolio: { ka: 'პორტფოლიოს ბმული', en: 'Portfolio link' },
    startingPrice: { ka: 'საწყისი ფასი (₾)', en: 'Starting price (₾)' },
    mainPlatforms: { ka: 'ძირითადი პლატფორმები', en: 'Main platforms' },
    category: { ka: 'კატეგორია / ნიშა', en: 'Category / niche' },
    socialLinks: { ka: 'სოციალური ბმულები', en: 'Social media links' },
    submit: { ka: 'რეგისტრაცია', en: 'Sign up' },
    submitLogin: { ka: 'შესვლა', en: 'Log in' },
    alreadyHaveAccount: { ka: 'უკვე გაქვს ანგარიში?', en: 'Already have an account?' },
    noAccount: { ka: 'არ გაქვს ანგარიში?', en: "Don't have an account?" },
  },
  messaging: {
    title: { ka: 'შეტყობინებები', en: 'Messages' },
    placeholder: { ka: 'დაწერე შეტყობინება...', en: 'Write a message...' },
    attach: { ka: 'ფაილის მიმაგრება', en: 'Attach file' },
    send: { ka: 'გაგზავნა', en: 'Send' },
    noConversations: { ka: 'ჯერ შეტყობინებები არ გაქვს.', en: 'No conversations yet.' },
    conversationsTitle: { ka: 'საუბრები', en: 'Conversations' },
  },
  faq: {
    title: { ka: 'ხშირად დასმული კითხვები', en: 'Frequently asked questions' },
  },
  about: {
    title: { ka: 'ჩვენ შესახებ', en: 'About us' },
  },
  contact: {
    title: { ka: 'დაგვიკავშირდი', en: 'Contact us' },
    name: { ka: 'სახელი', en: 'Name' },
    message: { ka: 'შეტყობინება', en: 'Message' },
    send: { ka: 'გაგზავნა', en: 'Send' },
  },
  footer: {
    company: { ka: 'კომპანია', en: 'Company' },
    forBusiness: { ka: 'ბიზნესისთვის', en: 'For business' },
    forCreators: { ka: 'კრეატორებისთვის', en: 'For creators' },
    support: { ka: 'მხარდაჭერა', en: 'Support' },
    rights: {
      ka: 'ყველა უფლება დაცულია.',
      en: 'All rights reserved.',
    },
  },
} as const;

export type DictKey = keyof typeof dict;

export function t(key: string, locale: Locale = DEFAULT_LOCALE): string {
  const parts = key.split('.');
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  let cur: any = dict;
  for (const p of parts) {
    if (cur && typeof cur === 'object' && p in cur) cur = cur[p];
    else return key;
  }
  if (cur && typeof cur === 'object' && (locale in cur)) {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    return (cur as any)[locale];
  }
  return key;
}

export function formatGEL(amount: number, locale: Locale = 'ka'): string {
  const formatted = new Intl.NumberFormat(locale === 'ka' ? 'ka-GE' : 'en-US', {
    maximumFractionDigits: 0,
  }).format(amount);
  return `${formatted} ₾`;
}

export function formatFollowers(n: number): string {
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1).replace(/\.0$/, '')}M`;
  if (n >= 1_000) return `${(n / 1_000).toFixed(1).replace(/\.0$/, '')}K`;
  return String(n);
}
