import Link from "next/link";
import { notFound } from "next/navigation";
import { listSku } from "@/lib/repositories/products.repo";
import { RrcForm } from "./rrc-form";

export const dynamic = "force-dynamic";

export default async function RrcPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const product = (await listSku({ limit: 1000 })).find((item) => item.productId === id);
  if (!product) notFound();
  return <main className="page"><section className="hero compact"><div className="eyebrow">NCRM-09c · write</div><h1>РРЦ: {product.sku}</h1><p>{product.name ?? "Без назви"}</p><Link href="/sku">← До SKU</Link></section><section className="card"><RrcForm productId={product.productId} sku={product.sku} currentRrc={product.currentRrc} /></section></main>;
}
