export type Locale = 'ka' | 'en';

export type Platform =
  | 'tiktok'
  | 'instagram'
  | 'youtube'
  | 'facebook'
  | 'linkedin';

export type CategoryId =
  | 'ugc'
  | 'tiktok'
  | 'reels'
  | 'youtube'
  | 'photographer'
  | 'videographer'
  | 'influencer'
  | 'product-reviewer'
  | 'food'
  | 'fashion'
  | 'beauty'
  | 'travel'
  | 'fitness'
  | 'tech'
  | 'business'
  | 'lifestyle';

export interface Category {
  id: CategoryId;
  ka: string;
  en: string;
  emoji: string;
  description: { ka: string; en: string };
}

export interface PortfolioItem {
  id: string;
  type: 'image' | 'video';
  thumbnail: string;
  title: string;
  titleKa: string;
}

export interface ServicePackageAddon {
  title: string;
  titleKa: string;
  price: number;
}

export interface Service {
  id: string;
  creatorId: string;
  title: string;
  titleKa: string;
  description: string;
  descriptionKa: string;
  category: CategoryId;
  price: number;
  deliveryDays: number;
  revisions: number;
  includes: string[];
  includesKa: string[];
  requirements: string[];
  requirementsKa: string[];
  addons: ServicePackageAddon[];
  thumbnail: string;
}

export interface Review {
  id: string;
  creatorId: string;
  clientName: string;
  clientAvatar: string;
  rating: number;
  comment: string;
  commentKa: string;
  date: string;
}

export interface CreatorAudienceDemographic {
  ageGroup: string;
  percent: number;
}

export interface Creator {
  id: string;
  slug: string;
  name: string;
  nameKa: string;
  avatar: string;
  cover: string;
  city: string;
  cityKa: string;
  bio: string;
  bioKa: string;
  category: CategoryId;
  niches: string[];
  nichesKa: string[];
  platforms: Platform[];
  socialLinks: Partial<Record<Platform, string>>;
  followers: Partial<Record<Platform, number>>;
  totalFollowers: number;
  verified: boolean;
  featured: boolean;
  rating: number;
  reviewCount: number;
  startingPrice: number;
  responseTimeHours: number;
  avgDeliveryDays: number;
  audienceDemographics: {
    genderFemalePct: number;
    genderMalePct: number;
    topCountries: string[];
    ageGroups: CreatorAudienceDemographic[];
  };
  portfolio: PortfolioItem[];
  joinedAt: string;
  languages: string[];
}

export type OrderStatus =
  | 'new'
  | 'awaiting_creator'
  | 'in_progress'
  | 'submitted'
  | 'revision_requested'
  | 'completed'
  | 'cancelled';

export interface Order {
  id: string;
  serviceId: string;
  creatorId: string;
  clientName: string;
  clientCompany?: string;
  campaignBrief: string;
  deadline: string;
  price: number;
  commission: number;
  payout: number;
  status: OrderStatus;
  createdAt: string;
  addons: string[];
}

export interface Message {
  id: string;
  conversationId: string;
  from: 'client' | 'creator';
  authorName: string;
  authorAvatar: string;
  text: string;
  attachments?: string[];
  createdAt: string;
}

export interface Conversation {
  id: string;
  creatorId: string;
  clientName: string;
  clientAvatar: string;
  lastMessage: string;
  lastMessageAt: string;
  unread: number;
  orderId?: string;
}

export interface AdminUser {
  id: string;
  name: string;
  email: string;
  role: 'creator' | 'client' | 'admin';
  status: 'pending' | 'approved' | 'rejected' | 'active';
  joinedAt: string;
  creatorId?: string;
}
