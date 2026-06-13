import type { Category } from '../types';

export const categories: Category[] = [
  {
    id: 'ugc',
    ka: 'UGC კრეატორი',
    en: 'UGC Creator',
    emoji: '🎬',
    description: {
      ka: 'ავთენტური ვიდეო რეკლამები ბრენდისთვის — როგორც ნამდვილი მომხმარებლისგან.',
      en: 'Authentic video ads for brands — as if from a real customer.',
    },
  },
  {
    id: 'tiktok',
    ka: 'TikTok კრეატორი',
    en: 'TikTok Creator',
    emoji: '🎵',
    description: {
      ka: 'ვირუსული TikTok ვიდეოები ქართული ტრენდებით.',
      en: 'Viral TikTok videos with Georgian trends.',
    },
  },
  {
    id: 'reels',
    ka: 'Instagram Reels კრეატორი',
    en: 'Instagram Reels Creator',
    emoji: '📸',
    description: {
      ka: 'მოკლე და დინამიკური Reels კონტენტი Instagram-ისთვის.',
      en: 'Short and dynamic Reels content for Instagram.',
    },
  },
  {
    id: 'youtube',
    ka: 'YouTube კრეატორი',
    en: 'YouTuber',
    emoji: '▶️',
    description: {
      ka: 'YouTube ვიდეო კონტენტი — შოუებიდან მიმოხილვებამდე.',
      en: 'YouTube video content — from shows to reviews.',
    },
  },
  {
    id: 'photographer',
    ka: 'ფოტოგრაფი',
    en: 'Photographer',
    emoji: '📷',
    description: {
      ka: 'პროდუქტის, ბრენდის და ლაიფსტაილის ფოტოგრაფია.',
      en: 'Product, brand and lifestyle photography.',
    },
  },
  {
    id: 'videographer',
    ka: 'ვიდეოგრაფი',
    en: 'Videographer',
    emoji: '🎥',
    description: {
      ka: 'პროფესიონალური ვიდეო წარმოება და მონტაჟი.',
      en: 'Professional video production and editing.',
    },
  },
  {
    id: 'influencer',
    ka: 'ინფლუენსერი',
    en: 'Influencer',
    emoji: '⭐',
    description: {
      ka: 'დიდი აუდიტორიის მქონე გავლენიანი კრეატორები.',
      en: 'Influential creators with large audiences.',
    },
  },
  {
    id: 'product-reviewer',
    ka: 'პროდუქტის მიმომხილველი',
    en: 'Product Reviewer',
    emoji: '🔍',
    description: {
      ka: 'დეტალური და გულახდილი პროდუქტის მიმოხილვები.',
      en: 'Detailed and honest product reviews.',
    },
  },
  {
    id: 'food',
    ka: 'ფუდ კრეატორი',
    en: 'Food Content Creator',
    emoji: '🍽️',
    description: {
      ka: 'რესტორნების, კერძების და კულინარული ბრენდების კონტენტი.',
      en: 'Content for restaurants, dishes and culinary brands.',
    },
  },
  {
    id: 'fashion',
    ka: 'ფეშენ კრეატორი',
    en: 'Fashion Creator',
    emoji: '👗',
    description: {
      ka: 'მოდის, სტილისა და ბრენდინგის კონტენტი.',
      en: 'Fashion, style and branding content.',
    },
  },
  {
    id: 'beauty',
    ka: 'სილამაზის კრეატორი',
    en: 'Beauty Creator',
    emoji: '💄',
    description: {
      ka: 'მაკიაჟი, კანის მოვლა, ჰეარკეარი — ვიდეო და ფოტო.',
      en: 'Makeup, skincare, haircare — video and photo.',
    },
  },
  {
    id: 'travel',
    ka: 'მოგზაურობის კრეატორი',
    en: 'Travel Creator',
    emoji: '✈️',
    description: {
      ka: 'საქართველოს და მსოფლიოს მოგზაურობის კონტენტი.',
      en: 'Travel content from Georgia and around the world.',
    },
  },
  {
    id: 'fitness',
    ka: 'ფიტნეს კრეატორი',
    en: 'Fitness Creator',
    emoji: '💪',
    description: {
      ka: 'ვარჯიში, ჯანმრთელი ცხოვრება, კვება.',
      en: 'Training, healthy lifestyle, nutrition.',
    },
  },
  {
    id: 'tech',
    ka: 'ტექ კრეატორი',
    en: 'Tech Creator',
    emoji: '💻',
    description: {
      ka: 'ტექნოლოგიების მიმოხილვები, gadget-ები, ციფრული პროდუქტები.',
      en: 'Tech reviews, gadgets, digital products.',
    },
  },
  {
    id: 'business',
    ka: 'ბიზნეს / ფინანსების კრეატორი',
    en: 'Business / Finance Creator',
    emoji: '📊',
    description: {
      ka: 'ბიზნესის, ფინანსების და მეწარმეობის შინაარსი.',
      en: 'Business, finance and entrepreneurship content.',
    },
  },
  {
    id: 'lifestyle',
    ka: 'ლაიფსტაილ კრეატორი',
    en: 'Lifestyle Creator',
    emoji: '🌿',
    description: {
      ka: 'ყოველდღიური ცხოვრება, vlog-ები, კულტურა.',
      en: 'Everyday life, vlogs, culture.',
    },
  },
];

export function getCategory(id: string) {
  return categories.find((c) => c.id === id);
}
