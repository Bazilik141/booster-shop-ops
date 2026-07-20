import type { Metadata } from "next";
import Link from "next/link";
import { SignOutButton } from "@/app/components/sign-out-button";
import { getCurrentStaff } from "@/lib/auth/session";
import "./globals.css";

export const metadata: Metadata = {
  title: "Booster Shop NCRM",
  description: "Next.js skeleton for the Booster Shop Supabase CRM"
};

export default async function RootLayout({
  children
}: Readonly<{
  children: React.ReactNode;
}>) {
  const staff = await getCurrentStaff();

  return (
    <html lang="uk">
      <body>
        <header className="site-header">
          {staff ? (
            <nav className="nav" aria-label="NCRM навігація">
              <Link href="/">Огляд</Link>
              <Link href="/orders">Замовлення</Link>
              <Link href="/purchases/new">Закупка</Link>
              <Link href="/writeoffs">Списання</Link>
              <Link href="/mystery">Mystery Box</Link>
              <Link href="/stock">Склад</Link>
              <Link href="/sku">SKU</Link>
              <Link href="/customers">Клієнти</Link>
              <span className="muted">
                {staff.displayName ?? "Співробітник"} · {staff.role}
              </span>
              <SignOutButton />
            </nav>
          ) : null}
        </header>
        {children}
      </body>
    </html>
  );
}
