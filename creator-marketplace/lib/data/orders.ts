import type { Order } from '../types';

const COMMISSION_PERCENT = 12;

function makeOrder(
  id: string,
  serviceId: string,
  creatorId: string,
  clientName: string,
  clientCompany: string | undefined,
  brief: string,
  deadline: string,
  price: number,
  status: Order['status'],
  createdAt: string,
  addons: string[] = [],
): Order {
  const commission = Math.round((price * COMMISSION_PERCENT) / 100);
  return {
    id,
    serviceId,
    creatorId,
    clientName,
    clientCompany,
    campaignBrief: brief,
    deadline,
    price,
    commission,
    payout: price - commission,
    status,
    createdAt,
    addons,
  };
}

export const orders: Order[] = [
  makeOrder(
    'o-1001',
    's-006',
    'c-005',
    'Tata Khurtsidze',
    'Mera Cosmetics',
    'Launch campaign for spring lipstick collection. Tone: bold, feminine, premium. Targeting Tbilisi women 18-34.',
    '2026-06-08',
    2400,
    'in_progress',
    '2026-05-20',
    ['Exclusivity (30 days)'],
  ),
  makeOrder(
    'o-1002',
    's-001',
    'c-001',
    'Nika Lortkipanidze',
    'Skin&Co',
    'Hero UGC video for new vitamin C serum. Focus on glow result + texture.',
    '2026-05-30',
    470,
    'submitted',
    '2026-05-21',
    ['Rush delivery (48h)'],
  ),
  makeOrder(
    'o-1003',
    's-013',
    'c-012',
    'Wolt Georgia',
    'Wolt',
    'Native TikTok about ordering from new restaurants in Tbilisi.',
    '2026-06-02',
    1200,
    'awaiting_creator',
    '2026-05-23',
    [],
  ),
  makeOrder(
    'o-1004',
    's-009',
    'c-007',
    'Sandro Tabatadze',
    'Caucasus Coffee Co.',
    'Refresh product photography for new espresso line — 10 photos.',
    '2026-06-12',
    650,
    'completed',
    '2026-05-04',
    [],
  ),
  makeOrder(
    'o-1005',
    's-004',
    'c-003',
    'Rooms Hotels',
    'Adjara Group',
    '30s cinematic reel for new Kazbegi suite. Sunrise + breakfast scene.',
    '2026-06-15',
    900,
    'in_progress',
    '2026-05-15',
    ['TikTok cross-post'],
  ),
  makeOrder(
    'o-1006',
    's-011',
    'c-009',
    'Lumi Beauty',
    'Lumi',
    'Tutorial reel for new hyaluronic serum.',
    '2026-06-10',
    600,
    'revision_requested',
    '2026-05-18',
    [],
  ),
  makeOrder(
    'o-1007',
    's-003',
    'c-002',
    'Levan Tsulukidze',
    'Folio',
    'Long-form review of our SaaS analytics platform.',
    '2026-06-22',
    1800,
    'new',
    '2026-05-24',
    [],
  ),
];

export function getOrdersByCreator(creatorId: string) {
  return orders.filter((o) => o.creatorId === creatorId);
}

export function getOrdersByClient(clientName: string) {
  return orders.filter((o) => o.clientName === clientName);
}

export function getOrder(id: string) {
  return orders.find((o) => o.id === id);
}

export const PLATFORM_COMMISSION_PERCENT = COMMISSION_PERCENT;
