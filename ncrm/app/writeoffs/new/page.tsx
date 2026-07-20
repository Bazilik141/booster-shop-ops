import Link from "next/link";
import { listSku } from "@/lib/repositories/products.repo";
import { WriteoffForm } from "./writeoff-form";

export const dynamic = "force-dynamic";

export default async function NewWriteoffPage() {
  const products = await listSku({ limit: 500 });
  return <main className="page"><section className="hero compact"><div className="eyebrow">NCRM-09c · write</div><h1>Нове списання</h1><p>Для Mystery Box використовується окремий майбутній процес, не ця форма.</p><Link href="/writeoffs">← До списань</Link></section><section className="card"><WriteoffForm products={products} /></section></main>;
}
