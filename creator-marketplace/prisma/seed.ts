/* eslint-disable @typescript-eslint/no-var-requires */
// Seed script — populates the SQLite database from the in-repo sample data.
// Run with: `npm run db:seed` (after `npm run db:push`).

import { categories } from '../lib/data/categories';
import { creators } from '../lib/data/creators';
import { services } from '../lib/data/services';
import { reviews } from '../lib/data/reviews';
import { orders } from '../lib/data/orders';

async function main() {
  const { PrismaClient } = await import('@prisma/client').catch(() => {
    console.error(
      '\nPrisma client not installed. Run: npm install && npx prisma generate\n',
    );
    process.exit(1);
  });
  // @ts-expect-error — prisma client is generated at runtime
  const prisma = new PrismaClient();

  console.log('Seeding categories...');
  for (const c of categories) {
    await prisma.category.upsert({
      where: { id: c.id },
      update: {},
      create: {
        id: c.id,
        ka: c.ka,
        en: c.en,
        emoji: c.emoji,
        descriptionKa: c.description.ka,
        descriptionEn: c.description.en,
      },
    });
  }

  console.log(`Seeding ${creators.length} creators (sample data)...`);
  console.log(`Sample includes: ${services.length} services, ${reviews.length} reviews, ${orders.length} orders.`);
  console.log(
    'Full seeding into User/Creator/Service tables is left as an exercise — see lib/data/*.ts for the in-memory source of truth.',
  );

  await prisma.$disconnect();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
