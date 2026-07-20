"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { updateRrcAction } from "./actions";

export function RrcForm({ productId, sku, currentRrc }: { productId: string; sku: string; currentRrc: number | null | undefined }) {
  const router = useRouter();
  const [message, setMessage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  async function submit(formData: FormData) {
    setIsSubmitting(true); setMessage(null);
    const result = await updateRrcAction(formData); setIsSubmitting(false);
    if (result.ok) { router.push("/sku"); router.refresh(); return; }
    setMessage(result.message);
  }
  const today = new Date().toISOString().slice(0, 10);
  return <form className="stack form-stack" action={submit}><input name="productId" type="hidden" value={productId} /><p className="muted">Поточна РРЦ: {currentRrc ?? "—"} грн</p><label>Нова РРЦ, грн<input name="rrc" type="number" min="0" step="0.01" required /></label><label>Діє з<input name="effectiveFrom" type="date" defaultValue={today} required /></label><label>Нотатка<input name="note" /></label><button type="submit" disabled={isSubmitting}>{isSubmitting ? "Зберігаю…" : "Оновити РРЦ"}</button>{message ? <p className="warning">{message}</p> : null}</form>;
}
