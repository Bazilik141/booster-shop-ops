import type { Metadata } from "next";
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
      <body>{children}</body>
    </html>
  );
}
