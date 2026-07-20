"use server";

import { revalidatePath } from "next/cache";
import { getCurrentStaff } from "@/lib/auth/session";
import { addSale } from "@/lib/repositories/sales.repo";

type FormResult = { ok: boolean; message: string };

function requiredText(formData: FormData, name: string): string {
  const value = formData.get(name);
  if (typeof value !== "string" || !value.trim()) throw new Error(`Заповніть поле «${name}».`);
  return value.trim();
}

function numberValue(value: unknown, name: string, minimum = 0): number {
  const number = Number(value);
  if (!Number.isFinite(number) || number < minimum) throw new Error(`Поле «${name}» має містити коректне число.`);
  return number;
}

export async function createSaleAction(formData: FormData): Promise<FormResult> {
  try {
    const staff = await getCurrentStaff();
    if (!staff) throw new Error("Доступ owner/admin не підтверджено.");
    const rawItems = JSON.parse(requiredText(formData, "itemsJson")) as unknown;
    if (!Array.isArray(rawItems) || rawItems.length === 0) throw new Error("Додайте принаймні одну позицію продажу.");
    const soldAt = new Date(requiredText(formData, "soldAt"));
    if (Number.isNaN(soldAt.valueOf())) throw new Error("Некоректна дата продажу.");

    const sale = await addSale({
      createdBy: staff.id,
      orderNo: requiredText(formData, "orderNo"),
      openCartOrderId: (formData.get("openCartOrderId") as string)?.trim() || null,
      channelId: requiredText(formData, "channelId"),
      soldAt: soldAt.toISOString(),
      customerName: (formData.get("customerName") as string)?.trim() || null,
      customerPhone: (formData.get("customerPhone") as string)?.trim() || null,
      paymentTypeId: requiredText(formData, "paymentTypeId"),
      paymentStatusId: requiredText(formData, "paymentStatusId"),
      orderStatusId: requiredText(formData, "orderStatusId"),
      postMethodId: (formData.get("postMethodId") as string)?.trim() || null,
      ttn: (formData.get("ttn") as string)?.trim() || null,
      discountTotal: numberValue(formData.get("discountTotal"), "Знижка"),
      packagingCost: numberValue(formData.get("packagingCost"), "Пакування"),
      shopDelivery: numberValue(formData.get("shopDelivery"), "Доставка магазину"),
      note: (formData.get("note") as string)?.trim() || null,
      items: rawItems.map((item, index) => {
        if (!item || typeof item !== "object") throw new Error(`Некоректна позиція ${index + 1}.`);
        const line = item as Record<string, unknown>;
        if (typeof line.productId !== "string" || !line.productId) throw new Error(`Оберіть SKU у позиції ${index + 1}.`);
        return {
          productId: line.productId,
          qty: numberValue(line.qty, `Кількість у позиції ${index + 1}`, Number.EPSILON),
          unitPrice: numberValue(line.unitPrice, `Ціна у позиції ${index + 1}`),
          discountAlloc: numberValue(line.discountAlloc, `Знижка у позиції ${index + 1}`),
          packagingAlloc: numberValue(line.packagingAlloc, `Пакування у позиції ${index + 1}`),
          shopDeliveryAlloc: numberValue(line.shopDeliveryAlloc, `Доставка у позиції ${index + 1}`),
          paymentFee: numberValue(line.paymentFee, `Комісія у позиції ${index + 1}`),
          note: typeof line.note === "string" && line.note.trim() ? line.note.trim() : null
        };
      })
    });

    revalidatePath("/orders");
    return { ok: true, message: `Продаж ${sale.orderNo} створено.` };
  } catch (error) {
    return { ok: false, message: error instanceof Error ? error.message : "Не вдалося створити продаж." };
  }
}
