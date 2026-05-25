import { NextResponse } from 'next/server';
import { getProvider } from '@/lib/payments';
import { prisma } from '@/lib/prisma';
import { getCurrentUser } from '@/lib/session';
import { createOrder } from '@/lib/orders';
import { PLATFORM_COMMISSION_PERCENT } from '@/lib/data/orders';

// POST /api/payments/create
//   body: { serviceId, addons?: string[], clientName, clientEmail?, brief?, deadline? }
//
// Authz: requires a CLIENT session. Looks up the service + creator in the DB,
// creates an Order row, then opens a payment intent with the configured
// provider and returns the redirect URL.
export async function POST(req: Request) {
  const user = await getCurrentUser();
  if (!user || (user.role !== 'CLIENT' && user.role !== 'ADMIN')) {
    return NextResponse.json(
      { error: 'შესვლა საჭიროა (კლიენტი)' },
      { status: 401 },
    );
  }
  const client = await prisma.client.findUnique({ where: { userId: user.id } });
  if (!client) {
    return NextResponse.json({ error: 'client profile not found' }, { status: 404 });
  }

  const body = await req.json().catch(() => ({}));
  const { serviceId, addons = [], brief, deadline, priority } = body ?? {};
  const clientName: string = body?.clientName ?? user.name;
  const clientEmail: string | undefined = body?.clientEmail ?? user.email;

  if (!serviceId) {
    return NextResponse.json({ error: 'serviceId required' }, { status: 400 });
  }

  const service = await prisma.service.findUnique({
    where: { id: serviceId },
    include: { creator: true },
  });
  if (!service) {
    return NextResponse.json({ error: 'service not found' }, { status: 404 });
  }

  const parsedAddons = JSON.parse(service.addons || '[]') as Array<{ titleKa: string; title: string; price: number }>;
  const addonTotal = parsedAddons
    .filter((a) => addons.includes(a.titleKa) || addons.includes(a.title))
    .reduce((s, a) => s + a.price, 0);
  const rushFee = priority === 'rush' ? Math.round(service.price * 0.25) : 0;
  const amountGEL = service.price + addonTotal + rushFee;
  const commission = Math.round((amountGEL * PLATFORM_COMMISSION_PERCENT) / 100);
  const payout = amountGEL - commission;

  const order = await createOrder({
    serviceId: service.id,
    serviceTitleKa: service.titleKa,
    creatorId: service.creatorId,
    clientId: client.id,
    clientName,
    clientCompany: client.companyName ?? undefined,
    campaignBrief: brief ?? '',
    deadline: deadline ? new Date(deadline) : undefined,
    priority: priority ?? 'std',
    price: amountGEL,
    commission,
    payout,
    addons,
  });

  const provider = getProvider();
  const origin =
    process.env.NEXT_PUBLIC_APP_URL ??
    `${req.headers.get('x-forwarded-proto') ?? 'http'}://${req.headers.get('host')}`;

  const intent = await provider.create({
    orderId: order.id,
    amountGEL,
    clientName,
    clientEmail,
    description: `${service.titleKa} — ${service.creator.nameKa}`,
    returnUrl: `${origin}/payments/return/PLACEHOLDER`,
    webhookUrl: `${origin}/api/payments/webhook/bog`,
  });

  // Persist the link between order and payment intent.
  await prisma.payment.create({
    data: {
      orderId: order.id,
      provider: intent.provider,
      status: 'pending',
      amount: amountGEL,
      currency: 'GEL',
      providerRef: intent.providerRef,
    },
  });

  intent.metadata = {
    ...(intent.metadata ?? {}),
    returnUrl: intent.metadata?.returnUrl?.replace('PLACEHOLDER', intent.id) ?? '',
    orderId: order.id,
    brief: brief ?? '',
    commission: String(commission),
    payout: String(payout),
    creatorId: service.creatorId,
    serviceTitleKa: service.titleKa,
  };

  return NextResponse.json({
    ok: true,
    paymentId: intent.id,
    orderId: order.id,
    amountGEL,
    commission,
    payout,
    redirectUrl:
      intent.provider === 'mock'
        ? `/payments/mock-bog/${intent.id}`
        : intent.redirectUrl,
  });
}
