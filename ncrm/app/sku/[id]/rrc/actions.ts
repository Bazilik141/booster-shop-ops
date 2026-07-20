"use server";

import { revalidatePath } from "next/cache";
import { getCurrentStaff } from "@/lib/auth/session";
import { updateRrc } from "@/lib/repositories/products.repo";

type FormResult = { ok: boolean; message: string };

export async function updateRrcAction(formData: FormData): Promise<FormResult> {
  try {
    const staff = await getCurrentStaff();
    if (!staff) throw new Error("Доступ owner/admin не підтверджено.");
    const productId = formData.get("productId");
    const effectiveFrom = formData.get("effectiveFrom");
    const rrc = Number(formData.get("rrc"));
    if (typeof productId !== "string" || !productId) throw new Error("Не знайдено SKU.");
    if (typeof effectiveFrom !== "string" || !/^\d{4}-\d{2}-\d{2}$/.test(effectiveFrom)) throw new Error("Некоректна дата дії РРЦ.");
    if (!Number.isFinite(rrc) || rrc < 0) throw new Error("РРЦ має бути невід’ємним числом.");
    const product = await updateRrc({ productId, rrc, effectiveFrom, note: (formData.get("note") as string)?.trim() || null });
    if (!product) throw new Error("SKU не знайдено після оновлення РРЦ.");
    revalidatePath("/sku");
    return { ok: true, message: `РРЦ для ${product.sku} оновлено.` };
  } catch (error) {
    return { ok: false, message: error instanceof Error ? error.message : "Не вдалося оновити РРЦ." };
  }
}
