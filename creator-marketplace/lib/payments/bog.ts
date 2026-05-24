// Bank of Georgia e-commerce provider.
//
// Real API reference: https://api.bog.ge/docs/payments/ecommerce
//   - OAuth2 token:        POST https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token
//   - Create payment:      POST https://api.bog.ge/payments/v1/ecommerce/orders
//   - Get payment:         GET  https://api.bog.ge/payments/v1/receipt/{orderId}
//   - Refund:              POST https://api.bog.ge/payments/v1/payment/refund/{orderId}
//
// We implement two providers behind one interface:
//   - bogProvider  : the real one (requires BOG_CLIENT_ID + BOG_CLIENT_SECRET)
//   - mockProvider : returns a local /payments/mock-bog/[id] redirect URL so
//                    you can run the entire flow end-to-end without creds
//
// The selector lives in lib/payments/index.ts and picks based on
// PAYMENTS_PROVIDER env var.

import { saveIntent, getIntent, updateIntent } from './store';
import type {
  CreatePaymentInput,
  PaymentIntent,
  PaymentProvider,
} from './types';

const BOG_BASE = process.env.BOG_API_BASE ?? 'https://api.bog.ge/payments/v1';
const BOG_OAUTH =
  process.env.BOG_OAUTH_URL ??
  'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token';

function genId() {
  return `pi_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`;
}

async function getAccessToken(): Promise<string> {
  const clientId = process.env.BOG_CLIENT_ID;
  const clientSecret = process.env.BOG_CLIENT_SECRET;
  if (!clientId || !clientSecret) {
    throw new Error('BOG_CLIENT_ID / BOG_CLIENT_SECRET are not configured');
  }
  const basic = Buffer.from(`${clientId}:${clientSecret}`).toString('base64');
  const res = await fetch(BOG_OAUTH, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      Authorization: `Basic ${basic}`,
    },
    body: 'grant_type=client_credentials',
  });
  if (!res.ok) throw new Error(`BOG oauth ${res.status}`);
  const data = (await res.json()) as { access_token: string };
  return data.access_token;
}

export const bogProvider: PaymentProvider = {
  name: 'bog',

  async create(input: CreatePaymentInput): Promise<PaymentIntent> {
    const token = await getAccessToken();
    const tetri = Math.round(input.amountGEL * 100);
    const body = {
      callback_url: input.webhookUrl,
      external_order_id: input.orderId,
      purchase_units: {
        currency: 'GEL',
        total_amount: input.amountGEL,
        basket: [
          {
            quantity: 1,
            unit_price: input.amountGEL,
            product_id: input.orderId,
            description: input.description,
          },
        ],
      },
      redirect_urls: {
        success: `${input.returnUrl}?status=success`,
        fail: `${input.returnUrl}?status=fail`,
      },
      buyer: input.clientEmail ? { email: input.clientEmail } : undefined,
    };
    const res = await fetch(`${BOG_BASE}/ecommerce/orders`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify(body),
    });
    if (!res.ok) {
      const err = await res.text();
      throw new Error(`BOG create failed ${res.status}: ${err}`);
    }
    const data = (await res.json()) as {
      id: string;
      _links: { redirect: { href: string } };
    };
    const now = new Date().toISOString();
    return saveIntent({
      id: genId(),
      orderId: input.orderId,
      provider: 'bog',
      amount: tetri,
      currency: 'GEL',
      status: 'created',
      providerRef: data.id,
      redirectUrl: data._links.redirect.href,
      createdAt: now,
      updatedAt: now,
    });
  },

  async capture(id: string) {
    // BOG e-commerce flow captures automatically on success; this becomes
    // a no-op that simply re-reads provider state.
    const cur = getIntent(id);
    if (!cur) throw new Error('intent not found');
    return cur;
  },

  async release(id: string) {
    // Release = trigger a payout from our escrow sub-account to the
    // creator's IBAN. In production this is a separate BOG Business
    // payout API call. For now we just mark the intent as released.
    const next = updateIntent(id, {
      status: 'released',
      releasedAt: new Date().toISOString(),
    });
    if (!next) throw new Error('intent not found');
    return next;
  },

  async refund(id: string, reason?: string) {
    const cur = getIntent(id);
    if (!cur) throw new Error('intent not found');
    const token = await getAccessToken();
    await fetch(`${BOG_BASE}/payment/refund/${cur.providerRef}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ amount: cur.amount / 100, reason }),
    });
    const next = updateIntent(id, {
      status: 'refunded',
      refundedAt: new Date().toISOString(),
    });
    return next!;
  },

  async get(id: string) {
    return getIntent(id);
  },
};

// Mock provider — used in dev when no BOG creds are configured.
// Surfaces a local /payments/mock-bog/[id] page that looks like the real
// BOG hosted form so the flow is testable end-to-end.
export const mockProvider: PaymentProvider = {
  name: 'mock',

  async create(input: CreatePaymentInput): Promise<PaymentIntent> {
    const id = genId();
    const tetri = Math.round(input.amountGEL * 100);
    const now = new Date().toISOString();
    const base = process.env.NEXT_PUBLIC_APP_URL ?? 'http://localhost:3000';
    return saveIntent({
      id,
      orderId: input.orderId,
      provider: 'mock',
      amount: tetri,
      currency: 'GEL',
      status: 'created',
      providerRef: `mock_${id}`,
      redirectUrl: `${base}/payments/mock-bog/${id}`,
      metadata: {
        description: input.description,
        clientName: input.clientName,
        returnUrl: input.returnUrl,
        webhookUrl: input.webhookUrl,
      },
      createdAt: now,
      updatedAt: now,
    });
  },

  async capture(id: string) {
    const next = updateIntent(id, {
      status: 'held',
      capturedAt: new Date().toISOString(),
    });
    if (!next) throw new Error('intent not found');
    return next;
  },

  async release(id: string) {
    const next = updateIntent(id, {
      status: 'released',
      releasedAt: new Date().toISOString(),
    });
    if (!next) throw new Error('intent not found');
    return next;
  },

  async refund(id: string) {
    const next = updateIntent(id, {
      status: 'refunded',
      refundedAt: new Date().toISOString(),
    });
    if (!next) throw new Error('intent not found');
    return next;
  },

  async get(id: string) {
    return getIntent(id);
  },
};
