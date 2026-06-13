// DB-backed order operations + status-transition guard.

import { prisma } from './prisma';
import { canTransition, type OrderStatus } from './enums';

export interface CreateOrderInput {
  serviceId: string;
  serviceTitleKa: string;
  creatorId: string;
  clientId: string;
  clientName: string;
  clientCompany?: string;
  campaignBrief: string;
  deadline?: Date;
  priority?: 'std' | 'rush';
  price: number;
  commission: number;
  payout: number;
  addons: string[];
}

export async function createOrder(input: CreateOrderInput) {
  return prisma.order.create({
    data: {
      serviceId: input.serviceId,
      serviceTitleKa: input.serviceTitleKa,
      creatorId: input.creatorId,
      clientId: input.clientId,
      clientName: input.clientName,
      clientCompany: input.clientCompany,
      campaignBrief: input.campaignBrief,
      deadline: input.deadline,
      priority: input.priority ?? 'std',
      price: input.price,
      commission: input.commission,
      payout: input.payout,
      addons: JSON.stringify(input.addons),
      status: 'NEW',
      events: {
        create: {
          actor: 'system',
          type: 'status_change',
          toStatus: 'NEW',
          note: 'შეკვეთა შეიქმნა',
        },
      },
    },
  });
}

export async function getOrderWithDetails(id: string) {
  return prisma.order.findUnique({
    where: { id },
    include: {
      creator: { include: { user: true } },
      client: { include: { user: true } },
      service: true,
      events: { orderBy: { createdAt: 'asc' } },
      payment: true,
      review: true,
      deliverables: { orderBy: { createdAt: 'desc' } },
    },
  });
}

export async function transitionStatus(opts: {
  orderId: string;
  to: OrderStatus;
  actor: 'creator' | 'client' | 'admin' | 'system';
  actorUserId?: string;
  note?: string;
}) {
  const order = await prisma.order.findUnique({ where: { id: opts.orderId } });
  if (!order) throw new Error('order not found');
  const from = order.status as OrderStatus;
  if (!canTransition(from, opts.to)) {
    throw new Error(`სტატუსი ${from} → ${opts.to} დაუშვებელია`);
  }
  return prisma.order.update({
    where: { id: opts.orderId },
    data: {
      status: opts.to,
      events: {
        create: {
          actor: opts.actor,
          type: 'status_change',
          fromStatus: from,
          toStatus: opts.to,
          note: opts.note,
        },
      },
    },
  });
}

export async function listOrdersForCreator(creatorId: string) {
  return prisma.order.findMany({
    where: { creatorId },
    orderBy: { createdAt: 'desc' },
    include: { service: true, client: { include: { user: true } } },
  });
}

export async function listOrdersForClient(clientId: string) {
  return prisma.order.findMany({
    where: { clientId },
    orderBy: { createdAt: 'desc' },
    include: { service: true, creator: true },
  });
}

export async function submitDeliverable(orderId: string, url: string, type: string) {
  return prisma.deliverable.create({ data: { orderId, url, type } });
}
