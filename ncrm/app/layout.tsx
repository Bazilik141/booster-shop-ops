import type { Metadata } from "next";
import Link from "next/link";
import "./globals.css";

export const metadata: Metadata = {
  title: "Booster Shop NCRM",
  description: "Next.js skeleton for the Booster Shop Supabase CRM"
};

export default function RootLayout({
  children
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="uk">
      <body>
        <header className="site-header">
          <nav className="nav" aria-label="NCRM навігація">
            <Link href="/">Огляд</Link>
            <Link href="/orders">Замовлення</Link>
            <Link href="/stock">Склад</Link>
            <Link href="/sku">SKU</Link>
            <Link href="/customers">Клієнти</Link>
          </nav>
        </header>
        {children}
      </body>
    </html>
  );
}
