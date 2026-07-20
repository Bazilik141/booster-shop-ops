import Link from "next/link";
import { notFound } from "next/navigation";
import { getEligibleComponents, getMysteryBoxType, getMysteryFulfillment } from "@/lib/repositories/mystery.repo";
import { MysteryForm } from "./mystery-form";

export const dynamic = "force-dynamic";

export default async function MysteryFulfillmentPage({ params }: { params: Promise<{ saleItemId: string }> }) {
  const { saleItemId } = await params;
  const fulfillment = await getMysteryFulfillment(saleItemId);
  if (!fulfillment) notFound();
  const boxType = await getMysteryBoxType(fulfillment.mysteryProductId);
  if (!boxType) notFound();
  const components = fulfillment.state === "needs_assembly" ? await getEligibleComponents(fulfillment.mysteryProductId) : [];

  return <main className="page"><section className="hero compact"><div className="eyebrow">NCRM-09e · fulfillment</div><h1>Збірка Mystery Box</h1><p><Link href="/mystery">← До черги Mystery Box</Link></p></section><section className="card"><MysteryForm fulfillment={fulfillment} boxType={boxType} components={components} /></section></main>;
}
