"use server";

import { revalidatePath } from "next/cache";
import { getCurrentStaff } from "@/lib/auth/session";
import { addPurchase } from "@/lib/repositories/purchases.repo";

type FormResult = { ok: boolean; message: string };
type RawLot = {
  lotCode: string;
  productId: string;
  qty: number;
  goodsCostUah: number;
  status: string;
  deliveryDate: string;
  trackNumber: string;
  note: string;
  manualForwarding: number;
  manualIntl: number;
  manualLocal: number;
};

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

function parseLots(formData: FormData): RawLot[] {
  const raw = JSON.parse(requiredText(formData, "lotsJson")) as unknown;
  if (!Array.isArray(raw) || raw.length === 0) throw new Error("Додайте принаймні один лот.");
  const lotCodes = new Set<string>();
  return raw.map((value, index) => {
    if (!value || typeof value !== "object") throw new Error(`Некоректний лот ${index + 1}.`);
    const lot = value as Record<string, unknown>;
    const lotCode = typeof lot.lotCode === "string" ? lot.lotCode.trim() : "";
    if (!lotCode || lotCodes.has(lotCode)) throw new Error(`Код лоту ${index + 1} має бути унікальним.`);
    lotCodes.add(lotCode);
    if (typeof lot.productId !== "string" || !lot.productId) throw new Error(`Оберіть SKU у лоті ${index + 1}.`);
    return {
      lotCode,
      productId: lot.productId,
      qty: numberValue(lot.qty, `Кількість у лоті ${index + 1}`, Number.EPSILON),
      goodsCostUah: numberValue(lot.goodsCostUah, `Вартість товару у лоті ${index + 1}`),
      status: typeof lot.status === "string" && lot.status ? lot.status : "ordered",
      deliveryDate: typeof lot.deliveryDate === "string" ? lot.deliveryDate : "",
      trackNumber: typeof lot.trackNumber === "string" ? lot.trackNumber.trim() : "",
      note: typeof lot.note === "string" ? lot.note.trim() : "",
      manualForwarding: numberValue(lot.manualForwarding, `Ручний forwarding у лоті ${index + 1}`),
      manualIntl: numberValue(lot.manualIntl, `Ручна міжнародна доставка у лоті ${index + 1}`),
      manualLocal: numberValue(lot.manualLocal, `Ручна локальна доставка у лоті ${index + 1}`)
    };
  });
}

export async function createPurchaseAction(formData: FormData): Promise<FormResult> {
  try {
    const staff = await getCurrentStaff();
    if (!staff) throw new Error("Доступ owner/admin не підтверджено.");
    const lots = parseLots(formData);
    const orderedAt = new Date(requiredText(formData, "orderedAt"));
    if (Number.isNaN(orderedAt.valueOf())) throw new Error("Некоректна дата закупки.");
    const allocationMethod = requiredText(formData, "allocationMethod");
    if (allocationMethod !== "weight" && allocationMethod !== "value" && allocationMethod !== "manual") {
      throw new Error("Некоректний метод розподілу shared fees.");
    }
    const forwardingFeeUah = numberValue(formData.get("forwardingFeeUah"), "Forwarding, грн");
    const intlShippingUah = numberValue(formData.get("intlShippingUah"), "Міжнародна доставка, грн");
    const localDeliveryUah = numberValue(formData.get("localDeliveryUah"), "Локальна доставка, грн");
    const isSingleLot = lots.length === 1;
    const purchase = await addPurchase({
      createdBy: staff.id,
      regionId: requiredText(formData, "regionId"),
      supplierName: (formData.get("supplierName") as string)?.trim() || null,
      orderRef: (formData.get("orderRef") as string)?.trim() || null,
      orderUrl: requiredText(formData, "orderUrl"),
      orderedAt: orderedAt.toISOString(),
      goodsTotalAmount: numberValue(formData.get("goodsTotalAmount"), "Товар, сума"),
      goodsTotalCurrency: requiredText(formData, "goodsTotalCurrency"),
      goodsTotalRate: numberValue(formData.get("goodsTotalRate"), "Товар, курс", Number.EPSILON),
      goodsTotalUah: numberValue(formData.get("goodsTotalUah"), "Товар, грн"),
      forwardingFeeAmount: numberValue(formData.get("forwardingFeeAmount"), "Forwarding, сума"),
      forwardingFeeCurrency: requiredText(formData, "forwardingFeeCurrency"),
      forwardingFeeRate: numberValue(formData.get("forwardingFeeRate"), "Forwarding, курс", Number.EPSILON),
      forwardingFeeUah,
      intlShippingAmount: numberValue(formData.get("intlShippingAmount"), "Міжнародна доставка, сума"),
      intlShippingCurrency: requiredText(formData, "intlShippingCurrency"),
      intlShippingRate: numberValue(formData.get("intlShippingRate"), "Міжнародна доставка, курс", Number.EPSILON),
      intlShippingUah,
      localDeliveryAmount: numberValue(formData.get("localDeliveryAmount"), "Локальна доставка, сума"),
      localDeliveryCurrency: requiredText(formData, "localDeliveryCurrency"),
      localDeliveryRate: numberValue(formData.get("localDeliveryRate"), "Локальна доставка, курс", Number.EPSILON),
      localDeliveryUah,
      note: (formData.get("note") as string)?.trim() || null,
      lots: lots.map((lot) => ({
        lotCode: lot.lotCode,
        productId: lot.productId,
        qty: lot.qty,
        goodsCostUah: lot.goodsCostUah,
        forwardingFeeUah: isSingleLot ? forwardingFeeUah : 0,
        intlShippingUah: isSingleLot ? intlShippingUah : 0,
        localDeliveryUah: isSingleLot ? localDeliveryUah : 0,
        deliveryDate: lot.deliveryDate || null,
        trackNumber: lot.trackNumber || null,
        status: lot.status,
        note: lot.note || null
      })),
      sharedFeeAllocation: isSingleLot ? undefined : {
        method: allocationMethod,
        manualAllocations: allocationMethod === "manual" ? {
          forwarding_fee: Object.fromEntries(lots.map((lot) => [lot.lotCode, lot.manualForwarding])),
          intl_shipping: Object.fromEntries(lots.map((lot) => [lot.lotCode, lot.manualIntl])),
          local_delivery: Object.fromEntries(lots.map((lot) => [lot.lotCode, lot.manualLocal]))
        } : undefined
      }
    });

    revalidatePath("/stock");
    return { ok: true, message: `Закупку ${purchase.orderRef ?? purchase.id} створено.` };
  } catch (error) {
    return { ok: false, message: error instanceof Error ? error.message : "Не вдалося створити закупку." };
  }
}
