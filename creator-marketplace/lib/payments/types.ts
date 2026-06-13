// Payment provider abstraction.
//
// The marketplace supports any provider that implements PaymentProvider:
// - BOG (Bank of Georgia e-commerce) — primary, GEL native
// - TBC (TBC iPay) — alternative GEL processor
// - Stripe — for cross-border clients (forex layer required for GEL)
//
// All flows share the same lifecycle:
//
//   created    →  client clicked "Pay", payment intent persisted, redirect_url issued
//   processing →  user is on the hosted 3DS page
//   held       →  funds captured into platform's escrow sub-account (webhook)
//   released   →  client approved delivery, payout sent to creator minus commission
//   refunded   →  cancelled or lost dispute, money returned to client
//   failed     →  3DS rejected / declined

export type PaymentStatus =
  | 'created'
  | 'processing'
  | 'held'
  | 'released'
  | 'refunded'
  | 'failed';

export interface PaymentIntent {
  id: string;
  orderId: string;
  provider: 'bog' | 'tbc' | 'stripe' | 'mock';
  amount: number;        // in minor units (tetri for GEL = 1/100 of a lari)
  currency: 'GEL';
  status: PaymentStatus;
  providerRef?: string;  // the provider's own ID for the transaction
  redirectUrl?: string;  // hosted 3DS page the user is sent to
  capturedAt?: string;
  releasedAt?: string;
  refundedAt?: string;
  metadata?: Record<string, string>;
  createdAt: string;
  updatedAt: string;
}

export interface CreatePaymentInput {
  orderId: string;
  amountGEL: number;     // amount in lari (we convert to tetri internally)
  clientName: string;
  clientEmail?: string;
  description: string;
  returnUrl: string;     // where to send the user after pay/cancel
  webhookUrl: string;    // where the provider POSTs status updates
}

export interface PaymentProvider {
  readonly name: 'bog' | 'tbc' | 'stripe' | 'mock';
  create(input: CreatePaymentInput): Promise<PaymentIntent>;
  capture(intentId: string): Promise<PaymentIntent>;       // explicit auth + capture flow
  release(intentId: string): Promise<PaymentIntent>;       // escrow → creator payout
  refund(intentId: string, reason?: string): Promise<PaymentIntent>;
  get(intentId: string): Promise<PaymentIntent | null>;
}
