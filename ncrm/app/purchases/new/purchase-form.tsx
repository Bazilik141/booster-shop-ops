"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { PurchaseFormReferences, SkuOption } from "@/lib/domain";
import { createPurchaseAction } from "./actions";

type PurchaseLotForm = { lotCode: string; productId: string; qty: number; goodsCostUah: number; status: string; deliveryDate: string; trackNumber: string; note: string; manualForwarding: number; manualIntl: number; manualLocal: number };
const emptyLot = (index: number): PurchaseLotForm => ({ lotCode: `LOT-${Date.now()}-${index + 1}`, productId: "", qty: 1, goodsCostUah: 0, status: "ordered", deliveryDate: "", trackNumber: "", note: "", manualForwarding: 0, manualIntl: 0, manualLocal: 0 });
const stockStatuses = new Set(["in_stock", "selling", "sold"]);

export function PurchaseForm({ products, references }: { products: SkuOption[]; references: PurchaseFormReferences }) {
  const router = useRouter();
  const [lots, setLots] = useState<PurchaseLotForm[]>([emptyLot(0)]);
  const [method, setMethod] = useState("value");
  const [message, setMessage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  function updateLot(index: number, key: keyof PurchaseLotForm, value: string | number) { setLots((current) => current.map((lot, lotIndex) => lotIndex === index ? { ...lot, [key]: value } : lot)); }
  async function submit(formData: FormData) {
    setIsSubmitting(true); setMessage(null); formData.set("lotsJson", JSON.stringify(lots));
    const result = await createPurchaseAction(formData); setIsSubmitting(false);
    if (result.ok) { router.push("/stock"); router.refresh(); return; }
    setMessage(result.message);
  }
  const showManual = lots.length > 1 && method === "manual";

  return <form className="stack form-stack" action={submit}>
    <section className="form-grid">
      <label>Регіон<select name="regionId" required>{references.regions.map((region) => <option key={region.id} value={region.id}>{region.name}</option>)}</select></label>
      <label>Постачальник<input name="supplierName" /></label>
      <label>Референс замовлення<input name="orderRef" /></label>
      <label>Посилання на замовлення<input name="orderUrl" type="url" required /></label>
      <label>Замовлено<input name="orderedAt" type="datetime-local" required /></label>
      <label>Метод shared fee<select name="allocationMethod" value={method} onChange={(event) => setMethod(event.target.value)}><option value="value">За вартістю товару</option><option value="weight">За вагою SKU</option><option value="manual">Ручний розподіл</option></select></label>
    </section>
    <section className="form-grid" aria-label="Суми та курси">
      <label>Товар, сума<input name="goodsTotalAmount" type="number" min="0" step="0.01" defaultValue="0" required /></label><label>Валюта товару<input name="goodsTotalCurrency" defaultValue="UAH" required /></label><label>Курс товару<input name="goodsTotalRate" type="number" min="0.000001" step="0.000001" defaultValue="1" required /></label><label>Товар, грн<input name="goodsTotalUah" type="number" min="0" step="0.01" defaultValue="0" required /></label>
      <label>Forwarding, сума<input name="forwardingFeeAmount" type="number" min="0" step="0.01" defaultValue="0" required /></label><label>Валюта forwarding<input name="forwardingFeeCurrency" defaultValue="UAH" required /></label><label>Курс forwarding<input name="forwardingFeeRate" type="number" min="0.000001" step="0.000001" defaultValue="1" required /></label><label>Forwarding, грн<input name="forwardingFeeUah" type="number" min="0" step="0.01" defaultValue="0" required /></label>
      <label>Міжнар. доставка, сума<input name="intlShippingAmount" type="number" min="0" step="0.01" defaultValue="0" required /></label><label>Валюта міжнар.<input name="intlShippingCurrency" defaultValue="UAH" required /></label><label>Курс міжнар.<input name="intlShippingRate" type="number" min="0.000001" step="0.000001" defaultValue="1" required /></label><label>Міжнар. доставка, грн<input name="intlShippingUah" type="number" min="0" step="0.01" defaultValue="0" required /></label>
      <label>Локальна доставка, сума<input name="localDeliveryAmount" type="number" min="0" step="0.01" defaultValue="0" required /></label><label>Валюта локальної<input name="localDeliveryCurrency" defaultValue="UAH" required /></label><label>Курс локальної<input name="localDeliveryRate" type="number" min="0.000001" step="0.000001" defaultValue="1" required /></label><label>Локальна доставка, грн<input name="localDeliveryUah" type="number" min="0" step="0.01" defaultValue="0" required /></label>
    </section>
    <label>Нотатка<input name="note" /></label>
    <section className="stack"><div className="form-section-heading"><h2>Лоти</h2><button type="button" onClick={() => setLots((current) => [...current, emptyLot(current.length)])}>Додати лот</button></div>
      {lots.map((lot, index) => <article className="card compact-card stack" key={`${lot.lotCode}-${index}`}><div className="form-grid">
        <label>Код лоту<input value={lot.lotCode} onChange={(event) => updateLot(index, "lotCode", event.target.value)} required /></label>
        <label>SKU<select value={lot.productId} onChange={(event) => updateLot(index, "productId", event.target.value)} required><option value="">Оберіть SKU</option>{products.map((product) => <option key={product.productId} value={product.productId}>{product.sku} — {product.name ?? "Без назви"}</option>)}</select></label>
        <label>Кількість<input type="number" min="0.001" step="0.001" value={lot.qty} onChange={(event) => updateLot(index, "qty", Number(event.target.value))} required /></label>
        <label>Вартість товару, грн<input type="number" min="0" step="0.01" value={lot.goodsCostUah} onChange={(event) => updateLot(index, "goodsCostUah", Number(event.target.value))} required /></label>
        <label>Статус<select value={lot.status} onChange={(event) => updateLot(index, "status", event.target.value)}>{references.lotStatuses.map((status) => <option key={status.code} value={status.code}>{status.name}</option>)}</select></label>
        <label>Дата отримання<input type="date" value={lot.deliveryDate} onChange={(event) => updateLot(index, "deliveryDate", event.target.value)} /></label>
        <label>Трек-номер<input value={lot.trackNumber} onChange={(event) => updateLot(index, "trackNumber", event.target.value)} /></label>
      </div>
      {stockStatuses.has(lot.status) && !lot.deliveryDate ? <p className="warning">Для складського статусу бажано вказати дату отримання.</p> : null}
      {showManual ? <div className="form-grid"><label>Ручний forwarding, грн<input type="number" min="0" step="0.01" value={lot.manualForwarding} onChange={(event) => updateLot(index, "manualForwarding", Number(event.target.value))} /></label><label>Ручна міжнар. доставка, грн<input type="number" min="0" step="0.01" value={lot.manualIntl} onChange={(event) => updateLot(index, "manualIntl", Number(event.target.value))} /></label><label>Ручна локальна доставка, грн<input type="number" min="0" step="0.01" value={lot.manualLocal} onChange={(event) => updateLot(index, "manualLocal", Number(event.target.value))} /></label></div> : null}
      <label>Нотатка лоту<input value={lot.note} onChange={(event) => updateLot(index, "note", event.target.value)} /></label>
      {lots.length > 1 ? <button type="button" className="secondary" onClick={() => setLots((current) => current.filter((_, lotIndex) => lotIndex !== index))}>Прибрати лот</button> : null}
      </article>)}
    </section>
    <p className="muted">Для кількох лотів база даних виконує вибраний shared-fee розподіл; TypeScript не рахує частки.</p>
    <button type="submit" disabled={isSubmitting}>{isSubmitting ? "Зберігаю…" : "Створити закупку"}</button>
    {message ? <p className="warning">{message}</p> : null}
  </form>;
}
