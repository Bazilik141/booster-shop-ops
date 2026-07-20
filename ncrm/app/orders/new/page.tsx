import Link from "next/link";
import { listSku } from "@/lib/repositories/products.repo";
import { getSaleFormReferences } from "@/lib/repositories/reference.repo";
import { SaleForm } from "./sale-form";

export const dynamic = "force-dynamic";

export default async function NewSalePage() {
  const [products, references] = await Promise.all([listSku({ limit: 500 }), getSaleFormReferences()]);
  return <main className="page">
    <section className="hero compact"><div className="eyebrow">NCRM-09b · write</div><h1>Новий продаж</h1><p>COGS/FIFO рахує база даних після вставки позицій для actual-продажу.</p><Link href="/orders">← До замовлень</Link></section>
    <section className="card"><SaleForm products={products} references={references} /></section>
  </main>;
}
