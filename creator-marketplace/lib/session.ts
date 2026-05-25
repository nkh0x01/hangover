// Helpers to fetch / require the current authenticated user in server
// components and route handlers.

import { getServerSession } from 'next-auth/next';
import { redirect } from 'next/navigation';
import { authOptions } from './auth';
import type { Role } from './enums';

export async function getCurrentUser() {
  const session = await getServerSession(authOptions);
  return session?.user ?? null;
}

export async function requireUser() {
  const user = await getCurrentUser();
  if (!user) redirect('/auth/login');
  return user;
}

export async function requireRole(role: Role | Role[]) {
  const user = await requireUser();
  const allowed = Array.isArray(role) ? role : [role];
  if (!allowed.includes(user.role)) {
    redirect('/');
  }
  return user;
}
