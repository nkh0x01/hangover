// Platform Agreement / Contract content (Georgian + English).
// Both creators and clients must accept this before they can use the platform.
// Key purpose: prevent "off-platform" deals (Anti-Circumvention) so the
// marketplace's commission and protections aren't bypassed.

export const AGREEMENT_VERSION = 'v1.0-2026-05';

export type AgreementType = 'creator' | 'client';

export interface AgreementClause {
  id: string;
  titleKa: string;
  titleEn: string;
  bodyKa: string;
  bodyEn: string;
}

const COMMON: AgreementClause[] = [
  {
    id: 'platform-only',
    titleKa: '1. პლატფორმის ექსკლუზიური გამოყენება',
    titleEn: '1. Platform-only transactions',
    bodyKa:
      'მომხმარებელი თანხმდება, რომ ნებისმიერი ფინანსური ტრანზაქცია (გადახდა, საკომისიო, ბონუსი, საჩუქარი) კრეატორებსა და კლიენტებს შორის, რომლებიც ერთმანეთს გაიცნეს კრეატორები.ge-ის გავლით, ჩატარდება მხოლოდ ჩვენი პლატფორმის გავლით 24 თვის განმავლობაში პირველი კონტაქტიდან.',
    bodyEn:
      'The user agrees that any financial transaction (payment, fee, bonus, gift) between creators and clients introduced through Kreatorebi.ge will be conducted only through our platform for 24 months from first contact.',
  },
  {
    id: 'no-circumvention',
    titleKa: '2. პლატფორმის გვერდის ავლის აკრძალვა',
    titleEn: '2. No circumvention',
    bodyKa:
      'მომხმარებელი არ მიიღებს და არ შესთავაზებს მეორე მხარეს გადახდას ან კონტენტის გადაცემას პლატფორმის გარეთ (პირადი გადარიცხვა, ნაღდი ანგარიშსწორება, საბანკო გადარიცხვა, კრიპტო, სხვა მარკეტფლეისი, პირადი მესენჯერი). მსგავსი ქმედება გამოიწვევს ანგარიშის გაუქმებას და კონტრაქტული პირგასამტეხლოს გადახდას ბოლო 12 თვის ბრუნვის 30%-ის ოდენობით.',
    bodyEn:
      'The user will not accept or offer payment or content delivery outside the platform (private transfer, cash, bank wire, crypto, other marketplaces, private DMs). Doing so will result in account termination and a contractual penalty equal to 30% of the last 12 months of revenue.',
  },
  {
    id: 'contact-info',
    titleKa: '3. პირადი საკონტაქტო ინფორმაცია',
    titleEn: '3. Personal contact info',
    bodyKa:
      'მესენჯერში პირადი ნომრის, ელ-ფოსტის, სხვა მესენჯერის ბმულის ან გადახდის რეკვიზიტების გაცვლა აკრძალულია მანამ, სანამ შეკვეთა არ არის გადახდილი პლატფორმაზე. ჩვენი სისტემა ავტომატურად აფიქსირებს ასეთ მცდელობებს.',
    bodyEn:
      'Sharing personal phone numbers, emails, other messenger handles, or payment details in chat is prohibited until an order has been paid on the platform. Our system automatically flags such attempts.',
  },
  {
    id: 'commission',
    titleKa: '4. პლატფორმის საკომისიო',
    titleEn: '4. Platform commission',
    bodyKa:
      'პლატფორმის საკომისიო შეადგენს კრეატორის თითოეული დასრულებული შეკვეთის 12%-ს. საკომისიო ავტომატურად ჩამოიჭრება შემოსავლიდან. კლიენტი იხდის სერვისის სრულ ფასს — დამატებითი საფასური მისთვის არ არსებობს.',
    bodyEn:
      'The platform commission is 12% of each completed order, deducted automatically from the creator\'s payout. The client pays the full listed price — there are no extra fees for clients.',
  },
  {
    id: 'escrow',
    titleKa: '5. Escrow და გადახდა',
    titleEn: '5. Escrow & payment',
    bodyKa:
      'კლიენტის გადახდილი თანხა ინახება პლატფორმაზე (escrow) კონტენტის მიწოდებამდე და კლიენტის მიერ დადასტურებამდე. დადასტურების შემდეგ თანხა ჩაირიცხება კრეატორთან 3 სამუშაო დღის ვადაში.',
    bodyEn:
      'Funds paid by the client are held in escrow until the content is delivered and approved by the client. Once approved, the payout is transferred to the creator within 3 business days.',
  },
  {
    id: 'disputes',
    titleKa: '6. დავების მოგვარება',
    titleEn: '6. Dispute resolution',
    bodyKa:
      'უთანხმოების შემთხვევაში, მხარეები იყენებენ პლატფორმის შიდა დავების სისტემას. ჩვენი გუნდი მიიღებს გადაწყვეტილებას 48 საათში, ჩატის ისტორიის, ბრიფისა და მიწოდებული კონტენტის საფუძველზე.',
    bodyEn:
      'In case of disagreement, both parties use the platform\'s internal dispute system. Our team will rule within 48 hours, based on the chat history, brief, and delivered content.',
  },
  {
    id: 'ip',
    titleKa: '7. ინტელექტუალური საკუთრება',
    titleEn: '7. Intellectual property',
    bodyKa:
      'შეკვეთის სრულად გადახდის შემდეგ, კონტენტის გამოყენების უფლება გადადის კლიენტზე სერვისის აღწერაში მითითებული პირობებით. ბრენდინგი და უფასო გადანაწილება შესაძლებელია მხოლოდ ცალკე შეთანხმებით.',
    bodyEn:
      'After full payment, content usage rights transfer to the client per the terms in the service listing. White-labeling or free redistribution requires a separate written agreement.',
  },
  {
    id: 'reporting',
    titleKa: '8. დარღვევის შესახებ შეტყობინება',
    titleEn: '8. Reporting violations',
    bodyKa:
      'თუ მეორე მხარე გთავაზობს პლატფორმის გვერდის ავლას, შეგიძლია გვაცნობო პლატფორმაზე ღილაკით „დარღვევის შეტყობინება". თუ შენი ანონიმური შეტყობინება დასტურდება, მიიღებ კრედიტს მომდევნო შეკვეთაზე.',
    bodyEn:
      'If the other party attempts to bypass the platform, report it via the "Report violation" button. If your anonymous report is confirmed, you receive credit on your next order.',
  },
  {
    id: 'liability',
    titleKa: '9. ხელშეკრულების ვადა და მოშლა',
    titleEn: '9. Term & termination',
    bodyKa:
      'ხელშეკრულება ძალაშია ანგარიშის გახსნიდან მის წაშლამდე. პლატფორმის გვერდის ავლის შემთხვევაში, ხელშეკრულება შეიძლება მოიშალოს ცალმხრივად, ანგარიშის გაუქმებითა და გადასახდელი თანხის შენარჩუნებით.',
    bodyEn:
      'This agreement is effective from account creation until deletion. In case of platform circumvention, the agreement may be terminated unilaterally, with account suspension and forfeiture of pending payouts.',
  },
  {
    id: 'jurisdiction',
    titleKa: '10. გამოყენებითი სამართალი',
    titleEn: '10. Governing law',
    bodyKa:
      'ხელშეკრულება რეგულირდება საქართველოს კანონმდებლობით. დავა, რომელიც ვერ მოგვარდა მოლაპარაკებით, განიხილება თბილისის საქალაქო სასამართლოში.',
    bodyEn:
      'This agreement is governed by the laws of Georgia. Any unresolved dispute will be heard in the Tbilisi City Court.',
  },
];

const CREATOR_SPECIFIC: AgreementClause[] = [
  {
    id: 'creator-quality',
    titleKa: 'C1. ხარისხი და მიწოდება',
    titleEn: 'C1. Quality & delivery',
    bodyKa:
      'კრეატორი თანხმდება მიაწოდოს კონტენტი თავის სერვისში მითითებული ვადისა და მოცულობის შესაბამისად. დაგვიანების ან არასრული მიწოდების შემთხვევაში, კლიენტს უფლება აქვს მოითხოვოს თანხის სრული დაბრუნება.',
    bodyEn:
      'The creator agrees to deliver content per the deadline and scope stated in their service. If delayed or incomplete, the client may request a full refund.',
  },
  {
    id: 'creator-original',
    titleKa: 'C2. ორიგინალური კონტენტი',
    titleEn: 'C2. Original content',
    bodyKa:
      'კრეატორი იძლევა გარანტიას, რომ მის მიერ შექმნილი კონტენტი ორიგინალურია, არ არღვევს მესამე პირის უფლებებს და არ შეიცავს არალიცენზირებულ მუსიკას/მასალას.',
    bodyEn:
      'The creator warrants that the content is original, does not infringe third-party rights, and does not include unlicensed music or material.',
  },
  {
    id: 'creator-taxes',
    titleKa: 'C3. გადასახადები',
    titleEn: 'C3. Taxes',
    bodyKa:
      'კრეატორი ვალდებულია თავად მართოს თავისი საგადასახადო ვალდებულებები (მცირე ბიზნესი / ინდ. მეწარმე). პლატფორმა გვერდი არ უდგას საგადასახადო კონსულტაციას, თუმცა გასცემს ანგარიშფაქტურას ნებისმიერ თვეზე.',
    bodyEn:
      'The creator is responsible for their own tax obligations (small-business / sole proprietor). The platform does not provide tax advice but will issue an invoice for any month on request.',
  },
];

const CLIENT_SPECIFIC: AgreementClause[] = [
  {
    id: 'client-brief',
    titleKa: 'B1. ნათელი ბრიფი',
    titleEn: 'B1. Clear brief',
    bodyKa:
      'კლიენტი თანხმდება მიაწოდოს კრეატორს ნათელი და სრული ბრიფი შეკვეთის დროს. შემდგომი ცვლილებები, რომლებიც სცილდება საწყის ბრიფს, ჩაითვლება დამატებით სამუშაოდ.',
    bodyEn:
      'The client agrees to provide a clear and complete brief when ordering. Changes that go beyond the original brief will be treated as additional work.',
  },
  {
    id: 'client-payment',
    titleKa: 'B2. დროული გადახდა',
    titleEn: 'B2. Timely payment',
    bodyKa:
      'კლიენტი იხდის თანხას წინასწარ, შეკვეთის დადასტურებამდე. გადახდა ხდება მხოლოდ პლატფორმაზე — საბანკო ბარათით, საბანკო გადარიცხვით ან ფაქტურით.',
    bodyEn:
      'The client pays upfront before the order is confirmed. Payment is made only on the platform — by bank card, wire, or invoice.',
  },
  {
    id: 'client-usage',
    titleKa: 'B3. გამოყენების უფლება',
    titleEn: 'B3. Usage rights',
    bodyKa:
      'კლიენტი იღებს კონტენტს იმ უფლებებით, რომელიც მითითებულია სერვისის აღწერაში. რეკლამისთვის (paid ads), თეთრი ნიშნით (white-label) ან განუსაზღვრელი ვადით გამოყენებას სჭირდება დამატებითი add-on.',
    bodyEn:
      'The client receives the content with the rights stated in the service description. Paid ads, white-labeling, or perpetual usage require an additional add-on.',
  },
];

export function getAgreementClauses(type: AgreementType): AgreementClause[] {
  const specific = type === 'creator' ? CREATOR_SPECIFIC : CLIENT_SPECIFIC;
  return [...COMMON, ...specific];
}

export const AGREEMENT_SUMMARY = {
  ka: [
    'ყველა გადახდა მხოლოდ პლატფორმაზე — გვერდის ავლა აკრძალულია',
    'პლატფორმის საკომისიო კრეატორზე: 12%',
    'თანხა ინახება Escrow-ში მიწოდებამდე',
    'დავის გადაწყვეტა — 48 საათში',
    'დარღვევისთვის: ანგარიშის გაუქმება + პირგასამტეხლო',
  ],
  en: [
    'All payments only on-platform — circumvention is prohibited',
    'Platform commission on creator: 12%',
    'Funds held in escrow until delivery',
    'Dispute resolution within 48 hours',
    'Violations: account termination + penalty',
  ],
};
