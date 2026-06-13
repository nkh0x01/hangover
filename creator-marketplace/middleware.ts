// Route protection middleware.
// /dashboard/*   → must be logged in
// /admin/*       → must be logged in AND role === ADMIN
// /messages      → must be logged in
// /checkout/*    → must be logged in
//
// Uses NextAuth's JWT cookie directly (no DB read needed).

import { withAuth } from 'next-auth/middleware';
import { NextResponse } from 'next/server';

export default withAuth(
  function middleware(req) {
    const token = req.nextauth.token;
    const path = req.nextUrl.pathname;

    if (path.startsWith('/admin') && token?.role !== 'ADMIN') {
      return NextResponse.redirect(new URL('/', req.url));
    }
    if (path.startsWith('/dashboard/creator') && token?.role !== 'CREATOR' && token?.role !== 'ADMIN') {
      return NextResponse.redirect(new URL('/dashboard/client', req.url));
    }
    if (path.startsWith('/dashboard/client') && token?.role === 'CREATOR') {
      return NextResponse.redirect(new URL('/dashboard/creator', req.url));
    }
    return NextResponse.next();
  },
  {
    callbacks: {
      authorized: ({ token }) => !!token,
    },
  },
);

export const config = {
  matcher: [
    '/dashboard/:path*',
    '/admin/:path*',
    '/messages/:path*',
    '/checkout/:path*',
    '/orders/:path*',
  ],
};
