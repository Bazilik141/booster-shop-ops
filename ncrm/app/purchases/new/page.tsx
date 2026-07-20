import Link from "next/link";
import { listSku } from "@/lib/repositories/products.repo";
import { getPurchaseFormReferences } from "@/lib/repositories/reference.repo";
import { PurchaseForm } from "./purchase-form";

export const dynamic = "force-dynamic";

export default async function NewPurchasePage() {
  const [products, references] = await Promise.all([listSku({ limit: 500 }), getPurchaseFormReferences()]);
  return <main className="page"><section className="hero compact"><div className="eyebrow">NCRM-09b · write</div><h1>Нова закупка</h1><p>Shared fees для кількох лотів розподіляє наявна DB-функція з аудитом.</p><Link href="/stock">← До складу</Link></section><section className="card"><PurchaseForm products={products} references={references} /></section></main>;
}
