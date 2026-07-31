"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { ReferenceOption, SaleFormReferences, SkuOption } from "@/lib/domain";
import { createSaleAction } from "./actions";

type SaleLine = { productId: string; qty: number; unitPrice: number; discountAlloc: number; packagingAlloc: number; shopDeliveryAlloc: number; paymentFee: number; feeTouched: boolean; note: string };
type EditableSaleLineKey = Exclude<keyof SaleLine, "feeTouched">;
const emptyLine = (): SaleLine => ({ productId: "", qty: 1, unitPrice: 0, discountAlloc: 0, packagingAlloc: 0, shopDeliveryAlloc: 0, paymentFee: 0, feeTouched: false, note: "" });

function SelectOptions({ options }: { options: ReferenceOption[] }) {
  return <>{options.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}</>;
}

function resolveDefaultPaymentTypeId(channelId: string, channels: ReferenceOption[], paymentTypes: SaleFormReferences["paymentTypes"], fallbackId: string): string {
  const channel = channels.find((option) => option.id === channelId);
  const monobazarPostpay = paymentTypes.find((option) => option.code === "postpay_monobazar");
  return channel?.code === "monobazar" && monobazarPostpay ? monobazarPostpay.id : fallbackId;
}

function roundCurrency(value: number): number {
  return Math.round((value + Number.EPSILON) * 100) / 100;
}

function applyAutomaticPaymentFee(line: SaleLine, paymentTypeId: string, paymentTypes: SaleFormReferences["paymentTypes"]): SaleLine {
  const feePct = paymentTypes.find((option) => option.id === paymentTypeId)?.feePct;
  if (line.feeTouched || feePct === null || feePct === undefined) return line;
  return { ...line, paymentFee: roundCurrency((line.qty * line.unitPrice - line.discountAlloc) * feePct) };
}

export function SaleForm({ products, references }: { products: SkuOption[]; references: SaleFormReferences }) {
  const router = useRouter();
  const initialChannelId = references.channels[0]?.id ?? "";
  const [channelId, setChannelId] = useState(initialChannelId);
  const [paymentTypeId, setPaymentTypeId] = useState(() => resolveDefaultPaymentTypeId(initialChannelId, references.channels, references.paymentTypes, references.paymentTypes[0]?.id ?? ""));
  const [lines, setLines] = useState<SaleLine[]>([emptyLine()]);
  const [message, setMessage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  function updatePaymentType(nextPaymentTypeId: string) {
    setPaymentTypeId(nextPaymentTypeId);
    setLines((current) => current.map((line) => applyAutomaticPaymentFee(line, nextPaymentTypeId, references.paymentTypes)));
  }
  function updateChannel(nextChannelId: string) {
    setChannelId(nextChannelId);
    const nextPaymentTypeId = resolveDefaultPaymentTypeId(nextChannelId, references.channels, references.paymentTypes, paymentTypeId);
    if (nextPaymentTypeId !== paymentTypeId) updatePaymentType(nextPaymentTypeId);
  }
  function updateLine(index: number, key: EditableSaleLineKey, value: string | number) {
    setLines((current) => current.map((line, lineIndex) => {
      if (lineIndex !== index) return line;
      const nextLine = { ...line, [key]: value };
      return key === "paymentFee" ? { ...nextLine, feeTouched: true } : applyAutomaticPaymentFee(nextLine, paymentTypeId, references.paymentTypes);
    }));
  }
  async function submit(formData: FormData) {
    setIsSubmitting(true); setMessage(null); formData.set("itemsJson", JSON.stringify(lines.map(({ feeTouched: _feeTouched, ...line }) => line)));
    const result = await createSaleAction(formData); setIsSubmitting(false);
    if (result.ok) { router.push("/orders"); router.refresh(); return; }
    setMessage(result.message);
  }

  return <form className="stack form-stack" action={submit}>
    <section className="form-grid">
      <label>Номер замовлення<input name="orderNo" required /></label>
      <label>OpenCart ID<input name="openCartOrderId" inputMode="numeric" /></label>
      <label>Продано<input name="soldAt" type="datetime-local" required /></label>
      <label>Канал<select name="channelId" value={channelId} onChange={(event) => updateChannel(event.target.value)} required><SelectOptions options={references.channels} /></select></label>
      <label>Тип оплати<select name="paymentTypeId" value={paymentTypeId} onChange={(event) => updatePaymentType(event.target.value)} required><SelectOptions options={references.paymentTypes} /></select></label>
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
      <div className="form-section-heading"><h2>Позиції</h2><button type="button" onClick={() => setLines((current) => [...current, applyAutomaticPaymentFee(emptyLine(), paymentTypeId, references.paymentTypes)])}>Додати позицію</button></div>
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
