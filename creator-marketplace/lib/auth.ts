import type { NextAuthOptions } from 'next-auth';
import CredentialsProvider from 'next-auth/providers/credentials';
import { PrismaAdapter } from '@auth/prisma-adapter';
import bcrypt from 'bcryptjs';
import { prisma } from './prisma';
import type { Role } from './enums';

// Augment the session to expose `id` + `role`.
declare module 'next-auth' {
  interface Session {
    user: {
      id: string;
      email: string;
      name: string;
      image?: string | null;
      role: Role;
    };
  }
  interface User {
    role: Role;
  }
}

declare module 'next-auth/jwt' {
  interface JWT {
    uid: string;
    role: Role;
  }
}

export const authOptions: NextAuthOptions = {
  // PrismaAdapter is omitted intentionally — we use JWT sessions so the
  // credentials flow works without persisting Session rows on every request.
  // (Adapter is kept around for OAuth providers if added later.)
  session: { strategy: 'jwt', maxAge: 30 * 24 * 60 * 60 },
  pages: {
    signIn: '/auth/login',
  },
  providers: [
    CredentialsProvider({
      name: 'Credentials',
      credentials: {
        email: { label: 'Email', type: 'email' },
        password: { label: 'Password', type: 'password' },
      },
      async authorize(credentials) {
        if (!credentials?.email || !credentials.password) return null;
        const user = await prisma.user.findUnique({
          where: { email: credentials.email.toLowerCase().trim() },
        });
        if (!user || !user.passwordHash) return null;
        const ok = await bcrypt.compare(credentials.password, user.passwordHash);
        if (!ok) return null;
        return {
          id: user.id,
          email: user.email,
          name: user.name,
          image: user.image ?? null,
          role: user.role as Role,
        };
      },
    }),
  ],
  callbacks: {
    jwt: async ({ token, user }) => {
      if (user) {
        token.uid = user.id;
        token.role = (user as { role: Role }).role;
      }
      return token;
    },
    session: async ({ session, token }) => {
      if (session.user) {
        session.user.id = token.uid;
        session.user.role = token.role;
      }
      return session;
    },
  },
  secret: process.env.NEXTAUTH_SECRET ?? 'dev-secret-change-me',
};

export async function hashPassword(plain: string): Promise<string> {
  return bcrypt.hash(plain, 10);
}

// Re-export PrismaAdapter type to keep tree-shaking happy (the adapter
// is unused at runtime but importing it documents the intent).
export { PrismaAdapter };
