import './globals.css';
import type { Metadata, Viewport } from 'next';
import { Header } from '@/components/Header';
import { Footer } from '@/components/Footer';

export const metadata: Metadata = {
  title: 'კრეატორები.ge — ქართული კონტენტ კრეატორების მარკეტფლეისი',
  description:
    'იპოვე საუკეთესო ქართველი კონტენტ კრეატორები ბრენდისთვის — TikTok, Instagram, YouTube, UGC, ფოტოგრაფია და ინფლუენსერ კოლაბორაცია.',
  applicationName: 'კრეატორები',
};

export const viewport: Viewport = {
  themeColor: '#7c3aed',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ka">
      <body className="min-h-screen flex flex-col bg-white text-ink-900">
        <Header />
        <main className="flex-1">{children}</main>
        <Footer />
      </body>
    </html>
  );
}
