"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { ReferenceOption, SaleFormReferences, SkuOption } from "@/lib/domain";
import { createSaleAction } from "./actions";

type SaleLine = { productId: string; qty: number; unitPrice: number; discountAlloc: number; packagingAlloc: number; shopDeliveryAlloc: number; paymentFee: number; note: string };
const emptyLine = (): SaleLine => ({ productId: "", qty: 1, unitPrice: 0, discountAlloc: 0, packagingAlloc: 0, shopDeliveryAlloc: 0, paymentFee: 0, note: "" });

function SelectOptions({ options }: { options: ReferenceOption[] }) {
  return <>{options.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}</>;
}

export function SaleForm({ products, references }: { products: SkuOption[]; references: SaleFormReferences }) {
  const router = useRouter();
  const [lines, setLines] = useState<SaleLine[]>([emptyLine()]);
  const [message, setMessage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  function updateLine(index: number, key: keyof SaleLine, value: string | number) {
    setLines((current) => current.map((line, lineIndex) => lineIndex === index ? { ...line, [key]: value } : line));
  }
  async function submit(formData: FormData) {
    setIsSubmitting(true); setMessage(null); formData.set("itemsJson", JSON.stringify(lines));
    const result = await createSaleAction(formData); setIsSubmitting(false);
    if (result.ok) { router.push("/orders"); router.refresh(); return; }
    setMessage(result.message);
  }

  return <form className="stack form-stack" action={submit}>
    <section className="form-grid">
      <label>Номер замовлення<input name="orderNo" required /></label>
      <label>OpenCart ID<input name="openCartOrderId" inputMode="numeric" /></label>
      <label>Продано<input name="soldAt" type="datetime-local" required /></label>
      <label>Канал<select name="channelId" required><SelectOptions options={references.channels} /></select></label>
      <label>Тип оплати<select name="paymentTypeId" required><SelectOptions options={references.paymentTypes} /></select></label>
      <label>Статус оплати<select name="paymentStatusId" required><SelectOptions options={references.paymentStatuses} /></select></label>
      <label>Статус замовлення<select name="orderStatusId" required><SelectOptions options={references.orderStatuses} /></select></label>
      <label>Доставка<select name="postMethodId"><option value="">Не вказано</option><SelectOptions options={references.postMethods} /></select></label>
      <label>ТТН<input name="ttn" /></label>
      <label>Клієнт<input name="customerName" /></label>
      <label>Телефон<input name="customerPhone" type="tel" /></label>
      <label>Знижка, грн<input name="discountTotal" type="number" min="0" step="0.01" defaultValue="0" required /></label>
      <label>Пакування, грн<input name="packagingCost" type="number" min="0" step="0.01" defaultValue="0" required /></label>
      <label>Доставка магазину, грн<input name="shopDelivery" type="number" min="0" step="0.01" defaultValue="0" required /></label>
    </section>
    <label>Нотатка<textarea name="note" rows={3} /></label>
    <section className="stack" aria-label="Позиції продажу">
      <div className="form-section-heading"><h2>Позиції</h2><button type="button" onClick={() => setLines((current) => [...current, emptyLine()])}>Додати позицію</button></div>
      {lines.map((line, index) => <article className="card compact-card stack" key={index}>
        <div className="form-grid">
          <label>SKU<select value={line.productId} onChange={(event) => updateLine(index, "productId", event.target.value)} required><option value="">Оберіть SKU</option>{products.map((product) => <option key={product.productId} value={product.productId}>{product.sku} — {product.name ?? "Без назви"}</option>)}</select></label>
          <label>Кількість<input type="number" min="0.001" step="0.001" value={line.qty} onChange={(event) => updateLine(index, "qty", Number(event.target.value))} required /></label>
          <label>Ціна, грн<input type="number" min="0" step="0.01" value={line.unitPrice} onChange={(event) => updateLine(index, "unitPrice", Number(event.target.value))} required /></label>
          <label>Знижка, грн<input type="number" min="0" step="0.01" value={line.discountAlloc} onChange={(event) => updateLine(index, "discountAlloc", Number(event.target.value))} /></label>
          <label>Пакування, грн<input type="number" min="0" step="0.01" value={line.packagingAlloc} onChange={(event) => updateLine(index, "packagingAlloc", Number(event.target.value))} /></label>
          <label>Доставка, грн<input type="number" min="0" step="0.01" value={line.shopDeliveryAlloc} onChange={(event) => updateLine(index, "shopDeliveryAlloc", Number(event.target.value))} /></label>
          <label>Комісія, грн<input type="number" min="0" step="0.01" value={line.paymentFee} onChange={(event) => updateLine(index, "paymentFee", Number(event.target.value))} /></label>
        </div>
        <label>Нотатка позиції<input value={line.note} onChange={(event) => updateLine(index, "note", event.target.value)} /></label>
        {lines.length > 1 ? <button type="button" className="secondary" onClick={() => setLines((current) => current.filter((_, lineIndex) => lineIndex !== index))}>Прибрати позицію</button> : null}
      </article>)}
    </section>
    <button type="submit" disabled={isSubmitting}>{isSubmitting ? "Зберігаю…" : "Створити продаж"}</button>
    {message ? <p className="warning">{message}</p> : null}
  </form>;
}
