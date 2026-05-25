/* eslint-disable no-console */
// Full seeder — populates the SQLite DB from lib/data/* and creates a set
// of demo users with known passwords so you can log in immediately.

import bcrypt from 'bcryptjs';
import { PrismaClient } from '@prisma/client';
import { categories } from '../lib/data/categories';
import { creators } from '../lib/data/creators';
import { services } from '../lib/data/services';
import { reviews } from '../lib/data/reviews';
import { orders } from '../lib/data/orders';

const prisma = new PrismaClient();

async function main() {
  console.log('🌱 wiping existing data...');
  await prisma.orderEvent.deleteMany();
  await prisma.review.deleteMany();
  await prisma.deliverable.deleteMany();
  await prisma.payment.deleteMany();
  await prisma.message.deleteMany();
  await prisma.conversation.deleteMany();
  await prisma.order.deleteMany();
  await prisma.savedCreator.deleteMany();
  await prisma.service.deleteMany();
  await prisma.portfolioItem.deleteMany();
  await prisma.creator.deleteMany();
  await prisma.client.deleteMany();
  await prisma.agreement.deleteMany();
  await prisma.notification.deleteMany();
  await prisma.session.deleteMany();
  await prisma.account.deleteMany();
  await prisma.user.deleteMany();
  await prisma.category.deleteMany();

  console.log('🏷  categories...');
  for (const c of categories) {
    await prisma.category.create({
      data: {
        id: c.id,
        ka: c.ka,
        en: c.en,
        emoji: c.emoji,
        descriptionKa: c.description.ka,
        descriptionEn: c.description.en,
      },
    });
  }

  console.log('🛡  admin user...');
  await prisma.user.create({
    data: {
      email: 'admin@kreatorebi.ge',
      passwordHash: await bcrypt.hash('admin1234', 10),
      name: 'Platform Admin',
      role: 'ADMIN',
    },
  });

  console.log('👤 creator users + profiles...');
  const slugToCreatorId = new Map<string, string>();
  for (const c of creators) {
    const user = await prisma.user.create({
      data: {
        email: `${c.slug}@kreatorebi.ge`,
        passwordHash: await bcrypt.hash('creator123', 10),
        name: c.name,
        image: c.avatar,
        role: 'CREATOR',
        creatorProfile: {
          create: {
            slug: c.slug,
            name: c.name,
            nameKa: c.nameKa,
            bio: c.bio,
            bioKa: c.bioKa,
            city: c.city,
            cityKa: c.cityKa,
            avatar: c.avatar,
            cover: c.cover,
            category: c.category,
            niches: JSON.stringify(c.niches),
            platforms: JSON.stringify(c.platforms),
            socialLinks: JSON.stringify(c.socialLinks),
            followers: JSON.stringify(c.followers),
            totalFollowers: c.totalFollowers,
            startingPrice: c.startingPrice,
            responseTimeHours: c.responseTimeHours,
            avgDeliveryDays: c.avgDeliveryDays,
            languages: JSON.stringify(c.languages),
            status: 'APPROVED',
            verified: c.verified,
            featured: c.featured,
            rating: c.rating,
            reviewCount: c.reviewCount,
            portfolioItems: {
              create: c.portfolio.map((p) => ({
                type: p.type,
                url: p.thumbnail,
                thumbnail: p.thumbnail,
                title: p.title,
                titleKa: p.titleKa,
              })),
            },
          },
        },
      },
      include: { creatorProfile: true },
    });
    if (user.creatorProfile) slugToCreatorId.set(c.slug, user.creatorProfile.id);
  }

  console.log('🛒 services...');
  // Map old service.creatorId (c-001) → new creator.id (cuid)
  const oldToNewCreator = new Map<string, string>();
  for (const c of creators) {
    const newId = slugToCreatorId.get(c.slug);
    if (newId) oldToNewCreator.set(c.id, newId);
  }
  const oldToNewService = new Map<string, string>();
  for (const s of services) {
    const newCreatorId = oldToNewCreator.get(s.creatorId);
    if (!newCreatorId) continue;
    const created = await prisma.service.create({
      data: {
        creatorId: newCreatorId,
        title: s.title,
        titleKa: s.titleKa,
        description: s.description,
        descriptionKa: s.descriptionKa,
        category: s.category,
        price: s.price,
        deliveryDays: s.deliveryDays,
        revisions: s.revisions,
        includes: JSON.stringify(s.includes),
        includesKa: JSON.stringify(s.includesKa),
        requirements: JSON.stringify(s.requirements),
        requirementsKa: JSON.stringify(s.requirementsKa),
        addons: JSON.stringify(s.addons),
        thumbnail: s.thumbnail,
      },
    });
    oldToNewService.set(s.id, created.id);
  }

  console.log('🏢 client users...');
  const demoClients = [
    { name: 'Tata Khurtsidze', email: 'tata@mera.ge', company: 'Mera Cosmetics', industry: 'სილამაზე' },
    { name: 'Nika Lortkipanidze', email: 'nika@skinco.ge', company: 'Skin&Co', industry: 'სილამაზე' },
    { name: 'Wolt Georgia', email: 'partnerships@wolt.ge', company: 'Wolt', industry: 'Food delivery' },
    { name: 'Rooms Hotels', email: 'marketing@roomshotels.com', company: 'Adjara Group', industry: 'სასტუმროები' },
  ];
  const emailToClientId = new Map<string, string>();
  for (const c of demoClients) {
    const u = await prisma.user.create({
      data: {
        email: c.email,
        passwordHash: await bcrypt.hash('client123', 10),
        name: c.name,
        role: 'CLIENT',
        clientProfile: { create: { name: c.name, companyName: c.company, industry: c.industry } },
      },
      include: { clientProfile: true },
    });
    if (u.clientProfile) emailToClientId.set(c.email, u.clientProfile.id);
  }

  console.log('📦 sample orders...');
  // Pair sample orders with our seeded clients
  const clientByName: Record<string, string | undefined> = {
    'Tata Khurtsidze': emailToClientId.get('tata@mera.ge'),
    'Nika Lortkipanidze': emailToClientId.get('nika@skinco.ge'),
    'Wolt Georgia': emailToClientId.get('partnerships@wolt.ge'),
    'Rooms Hotels': emailToClientId.get('marketing@roomshotels.com'),
    'Sandro Tabatadze': emailToClientId.get('partnerships@wolt.ge'),
    'Levan Tsulukidze': emailToClientId.get('nika@skinco.ge'),
    'Lumi Beauty': emailToClientId.get('tata@mera.ge'),
  };
  let orderCount = 0;
  for (const o of orders) {
    const newCreatorId = oldToNewCreator.get(o.creatorId);
    const newServiceId = oldToNewService.get(o.serviceId);
    const newClientId = clientByName[o.clientName] ?? Array.from(emailToClientId.values())[0];
    if (!newCreatorId || !newClientId) continue;
    const map = {
      new: 'NEW',
      awaiting_creator: 'AWAITING_CREATOR',
      in_progress: 'IN_PROGRESS',
      submitted: 'SUBMITTED',
      revision_requested: 'REVISION_REQUESTED',
      completed: 'COMPLETED',
      cancelled: 'CANCELLED',
    } as const;
    const status = map[o.status];
    await prisma.order.create({
      data: {
        serviceId: newServiceId,
        serviceTitleKa: services.find((s) => s.id === o.serviceId)?.titleKa ?? '',
        creatorId: newCreatorId,
        clientId: newClientId,
        clientName: o.clientName,
        clientCompany: o.clientCompany,
        campaignBrief: o.campaignBrief,
        deadline: new Date(o.deadline),
        price: o.price,
        commission: o.commission,
        payout: o.payout,
        addons: JSON.stringify(o.addons),
        status,
        createdAt: new Date(o.createdAt),
        events: {
          create: [
            { actor: 'system', type: 'status_change', toStatus: 'NEW', note: 'შეკვეთა შეიქმნა და გადახდილია' },
            ...(status !== 'NEW'
              ? [{ actor: 'creator', type: 'status_change', fromStatus: 'NEW', toStatus: 'AWAITING_CREATOR', note: 'მიღებულია' }]
              : []),
            ...(['IN_PROGRESS', 'SUBMITTED', 'REVISION_REQUESTED', 'COMPLETED'].includes(status)
              ? [{ actor: 'creator', type: 'status_change', fromStatus: 'AWAITING_CREATOR', toStatus: 'IN_PROGRESS', note: 'მუშაობა დაიწყო' }]
              : []),
            ...(['SUBMITTED', 'REVISION_REQUESTED', 'COMPLETED'].includes(status)
              ? [{ actor: 'creator', type: 'deliverable_submitted', toStatus: 'SUBMITTED', note: 'კონტენტი ჩაბარდა' }]
              : []),
            ...(status === 'COMPLETED'
              ? [{ actor: 'client', type: 'status_change', fromStatus: 'SUBMITTED', toStatus: 'COMPLETED', note: 'კონტენტი დადასტურდა' }]
              : []),
          ],
        },
      },
    });
    orderCount++;
  }
  console.log(`   ${orderCount} orders seeded`);

  console.log('⭐ reviews...');
  for (const r of reviews) {
    const newCreatorId = oldToNewCreator.get(r.creatorId);
    if (!newCreatorId) continue;
    // Pair with the first completed order for that creator if any; skip otherwise.
    const order = await prisma.order.findFirst({
      where: { creatorId: newCreatorId, status: 'COMPLETED' },
    });
    if (!order) continue;
    const existing = await prisma.review.findUnique({ where: { orderId: order.id } });
    if (existing) continue;
    await prisma.review.create({
      data: {
        orderId: order.id,
        creatorId: newCreatorId,
        clientId: order.clientId,
        rating: r.rating,
        comment: r.commentKa,
        createdAt: new Date(r.date),
      },
    });
  }

  const counts = {
    users: await prisma.user.count(),
    creators: await prisma.creator.count(),
    services: await prisma.service.count(),
    orders: await prisma.order.count(),
    reviews: await prisma.review.count(),
  };
  console.log('\n✅ Seed complete:', counts);
  console.log('\n🔐 Demo logins:');
  console.log('   admin@kreatorebi.ge / admin1234       (ADMIN)');
  console.log('   nino-beridze@kreatorebi.ge / creator123  (CREATOR)');
  console.log('   tata@mera.ge / client123              (CLIENT)');
}

main()
  .then(() => prisma.$disconnect())
  .catch(async (e) => {
    console.error(e);
    await prisma.$disconnect();
    process.exit(1);
  });
