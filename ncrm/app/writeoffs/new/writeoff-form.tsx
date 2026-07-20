"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { SkuOption } from "@/lib/domain";
import { createWriteoffAction } from "./actions";

type WriteoffLine = { productId: string; qty: number; note: string };
const emptyLine = (): WriteoffLine => ({ productId: "", qty: 1, note: "" });
const writeoffTypes = ["Власне відкриття", "Маркетинг", "Інше"];

export function WriteoffForm({ products }: { products: SkuOption[] }) {
  const router = useRouter();
  const [lines, setLines] = useState<WriteoffLine[]>([emptyLine()]);
  const [message, setMessage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  function updateLine(index: number, key: keyof WriteoffLine, value: string | number) { setLines((current) => current.map((line, lineIndex) => lineIndex === index ? { ...line, [key]: value } : line)); }
  async function submit(formData: FormData) {
    setIsSubmitting(true); setMessage(null); formData.set("itemsJson", JSON.stringify(lines));
    const result = await createWriteoffAction(formData); setIsSubmitting(false);
    if (result.ok) { router.push("/writeoffs"); router.refresh(); return; }
    setMessage(result.message);
  }

  return <form className="stack form-stack" action={submit}>
    <section className="form-grid">
      <label>Номер списання<input name="writeoffNo" required /></label>
      <label>Тип<select name="type" required>{writeoffTypes.map((type) => <option key={type} value={type}>{type}</option>)}</select></label>
      <label>Дата списання<input name="writtenOffAt" type="datetime-local" required /></label>
      <label>Очікувана кількість<input name="expectedQty" type="number" min="0" step="0.001" /></label>
      <label>Причина<input name="reason" /></label>
    </section>
    <label>Загальна нотатка<textarea name="note" rows={3} /></label>
    <section className="stack"><div className="form-section-heading"><h2>Позиції</h2><button type="button" onClick={() => setLines((current) => [...current, emptyLine()])}>Додати позицію</button></div>
      {lines.map((line, index) => <article className="card compact-card stack" key={index}><div className="form-grid">
        <label>SKU<select value={line.productId} onChange={(event) => updateLine(index, "productId", event.target.value)} required><option value="">Оберіть SKU</option>{products.map((product) => <option key={product.productId} value={product.productId}>{product.sku} — {product.name ?? "Без назви"}</option>)}</select></label>
        <label>Кількість<input type="number" min="0.001" step="0.001" value={line.qty} onChange={(event) => updateLine(index, "qty", Number(event.target.value))} required /></label>
        <label>Нотатка позиції<input value={line.note} onChange={(event) => updateLine(index, "note", event.target.value)} /></label>
      </div>{lines.length > 1 ? <button type="button" className="secondary" onClick={() => setLines((current) => current.filter((_, lineIndex) => lineIndex !== index))}>Прибрати позицію</button> : null}</article>)}
    </section>
    <button type="submit" disabled={isSubmitting}>{isSubmitting ? "Зберігаю…" : "Створити списання"}</button>
    {message ? <p className="warning">{message}</p> : null}
  </form>;
}
