import { NextResponse } from 'next/server';
import { getProvider } from '@/lib/payments';
import { getService } from '@/lib/data/services';
import { getCreatorById } from '@/lib/data/creators';
import { PLATFORM_COMMISSION_PERCENT } from '@/lib/data/orders';

// POST /api/payments/create
//   body: { serviceId, addons?: string[], clientName, clientEmail?, brief? }
// Persists an Order (in-memory for now) and a Payment intent against the
// configured provider, returns the redirect_url that the client should
// navigate to for 3DS.
export async function POST(req: Request) {
  const body = await req.json().catch(() => ({}));
  const { serviceId, addons = [], clientName, clientEmail, brief } = body ?? {};

  if (!serviceId || !clientName) {
    return NextResponse.json(
      { error: 'serviceId and clientName are required' },
      { status: 400 },
    );
  }
  const service = getService(serviceId);
  if (!service) {
    return NextResponse.json({ error: 'service not found' }, { status: 404 });
  }
  const creator = getCreatorById(service.creatorId);
  if (!creator) {
    return NextResponse.json({ error: 'creator not found' }, { status: 404 });
  }

  // Sum base price + selected addons (matched by title)
  const addonTotal = (service.addons ?? [])
    .filter((a) => addons.includes(a.title) || addons.includes(a.titleKa))
    .reduce((s, a) => s + a.price, 0);
  const amountGEL = service.price + addonTotal;
  const commission = Math.round((amountGEL * PLATFORM_COMMISSION_PERCENT) / 100);
  const payout = amountGEL - commission;

  // Create order shell (real implementation would persist via Prisma).
  const orderId = `o-${Date.now()}`;

  const provider = getProvider();
  const origin =
    process.env.NEXT_PUBLIC_APP_URL ??
    `${req.headers.get('x-forwarded-proto') ?? 'http'}://${req.headers.get('host')}`;

  const intent = await provider.create({
    orderId,
    amountGEL,
    clientName,
    clientEmail,
    description: `${service.titleKa} — ${creator.nameKa}`,
    returnUrl: `${origin}/payments/return/PLACEHOLDER`,
    webhookUrl: `${origin}/api/payments/webhook/bog`,
  });

  // Patch returnUrl to include the real intent id
  intent.metadata = {
    ...(intent.metadata ?? {}),
    returnUrl: intent.metadata?.returnUrl?.replace('PLACEHOLDER', intent.id) ?? '',
    orderId,
    brief: brief ?? '',
    commission: String(commission),
    payout: String(payout),
    creatorId: creator.id,
    serviceTitleKa: service.titleKa,
  };

  return NextResponse.json({
    ok: true,
    paymentId: intent.id,
    orderId,
    amountGEL,
    commission,
    payout,
    redirectUrl:
      intent.provider === 'mock'
        ? `/payments/mock-bog/${intent.id}`
        : intent.redirectUrl,
  });
}
