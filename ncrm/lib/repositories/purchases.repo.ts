import type {
  AddPurchasePayload,
  BatchUpdateLotStatusPayload,
  Purchase,
  PurchaseSharedFeeType,
  PurchaseLot
} from "@/lib/domain";
import { createRepositoryClient } from "@/lib/supabase/client";
import type { Tables, TablesInsert, TablesUpdate } from "@/lib/types/database";
import { repositoryError, toNumber, toNullableNumber } from "./_utils";

type ProductMini = {
  sku?: string | null;
  name?: string | null;
  full_name?: string | null;
};

type PurchaseLotRecord = Tables<"purchase_lots"> & {
  products?: ProductMini | ProductMini[] | null;
};

type PurchaseRecord = Tables<"purchases"> & {
  purchase_lots?: PurchaseLotRecord[] | null;
};

const LOT_SELECT = "*, products(sku, name, full_name)";
const PURCHASE_SELECT = "*, purchase_lots(*, products(sku, name, full_name))";
const SHARED_FEES: { type: PurchaseSharedFeeType; amountKey: "forwardingFeeUah" | "intlShippingUah" | "localDeliveryUah" }[] = [
  { type: "forwarding_fee", amountKey: "forwardingFeeUah" },
  { type: "intl_shipping", amountKey: "intlShippingUah" },
  { type: "local_delivery", amountKey: "localDeliveryUah" }
];

function firstProduct(raw: PurchaseLotRecord["products"]): ProductMini | null {
  if (!raw) {
    return null;
  }

  return Array.isArray(raw) ? (raw[0] ?? null) : raw;
}

function mapLot(row: PurchaseLotRecord): PurchaseLot {
  const product = firstProduct(row.products);

  return {
    id: row.id,
    lotCode: row.lot_code,
    purchaseId: row.purchase_id,
    productId: row.product_id,
    productSku: product?.sku ?? null,
    productName: product?.full_name ?? product?.name ?? null,
    qty: toNumber(row.qty),
    goodsCostUah: toNumber(row.goods_cost_uah),
    forwardingFeeUah: toNumber(row.forwarding_fee_uah),
    intlShippingUah: toNumber(row.intl_shipping_uah),
    localDeliveryUah: toNumber(row.local_delivery_uah),
    manualUnitCost: toNullableNumber(row.manual_unit_cost),
    deliveryDate: row.delivery_date,
    trackNumber: row.track_number,
    status: row.status,
    legacyStatus: row.legacy_status,
    note: row.note,
    createdAt: row.created_at,
    updatedAt: row.updated_at
  };
}

function mapPurchase(row: PurchaseRecord): Purchase {
  return {
    id: row.id,
    regionId: row.region_id,
    supplierName: row.supplier_name,
    orderRef: row.order_ref,
    orderUrl: row.order_url,
    orderedAt: row.ordered_at,
    goodsTotalAmount: toNumber(row.goods_total_amount),
    goodsTotalCurrency: row.goods_total_currency,
    goodsTotalRate: toNumber(row.goods_total_rate),
    goodsTotalUah: toNumber(row.goods_total_uah),
    forwardingFeeAmount: toNumber(row.forwarding_fee_amount),
    forwardingFeeCurrency: row.forwarding_fee_currency,
    forwardingFeeRate: toNumber(row.forwarding_fee_rate),
    forwardingFeeUah: toNumber(row.forwarding_fee_uah),
    intlShippingAmount: toNumber(row.intl_shipping_amount),
    intlShippingCurrency: row.intl_shipping_currency,
    intlShippingRate: toNumber(row.intl_shipping_rate),
    intlShippingUah: toNumber(row.intl_shipping_uah),
    localDeliveryAmount: toNumber(row.local_delivery_amount),
    localDeliveryCurrency: row.local_delivery_currency,
    localDeliveryRate: toNumber(row.local_delivery_rate),
    localDeliveryUah: toNumber(row.local_delivery_uah),
    note: row.note,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
    lots: row.purchase_lots?.map(mapLot) ?? []
  };
}

export async function listPurchaseLots(
  options: { limit?: number; status?: string } = {}
): Promise<PurchaseLot[]> {
  const supabase = createRepositoryClient();
  let query = supabase
    .from("purchase_lots")
    .select(LOT_SELECT)
    .order("created_at", { ascending: false })
    .limit(options.limit ?? 100);

  if (options.status) {
    query = query.eq("status", options.status);
  }

  const { data, error } = await query;

  if (error) {
    throw repositoryError("listPurchaseLots", error.message);
  }

  return ((data ?? []) as PurchaseLotRecord[]).map(mapLot);
}

export async function addPurchase(payload: AddPurchasePayload): Promise<Purchase> {
  const supabase = createRepositoryClient();
  const purchaseInsert: TablesInsert<"purchases"> = {
    created_by: payload.createdBy,
    region_id: payload.regionId,
    supplier_name: payload.supplierName ?? null,
    order_ref: payload.orderRef ?? null,
    order_url: payload.orderUrl,
    ordered_at: payload.orderedAt,
    goods_total_amount: payload.goodsTotalAmount,
    goods_total_currency: payload.goodsTotalCurrency,
    goods_total_rate: payload.goodsTotalRate,
    goods_total_uah: payload.goodsTotalUah,
    forwarding_fee_amount: payload.forwardingFeeAmount,
    forwarding_fee_currency: payload.forwardingFeeCurrency,
    forwarding_fee_rate: payload.forwardingFeeRate,
    forwarding_fee_uah: payload.forwardingFeeUah,
    intl_shipping_amount: payload.intlShippingAmount,
    intl_shipping_currency: payload.intlShippingCurrency,
    intl_shipping_rate: payload.intlShippingRate,
    intl_shipping_uah: payload.intlShippingUah,
    local_delivery_amount: payload.localDeliveryAmount,
    local_delivery_currency: payload.localDeliveryCurrency,
    local_delivery_rate: payload.localDeliveryRate,
    local_delivery_uah: payload.localDeliveryUah,
    note: payload.note ?? null
  };

  const { data: purchase, error: purchaseError } = await supabase
    .from("purchases")
    .insert(purchaseInsert)
    .select("*")
    .single();

  if (purchaseError) {
    throw repositoryError("addPurchase", purchaseError.message);
  }

  try {
    if (!payload.lots?.length) {
      throw new Error("At least one purchase lot is required.");
    }
    if (payload.lots.length > 1 && !payload.sharedFeeAllocation) {
      throw new Error("Multi-lot purchases require a shared-fee allocation method.");
    }

    const lotRows: TablesInsert<"purchase_lots">[] = payload.lots.map((lot) => ({
      lot_code: lot.lotCode,
      purchase_id: purchase.id,
      product_id: lot.productId,
      qty: lot.qty,
      goods_cost_uah: lot.goodsCostUah,
      forwarding_fee_uah: lot.forwardingFeeUah,
      intl_shipping_uah: lot.intlShippingUah,
      local_delivery_uah: lot.localDeliveryUah,
      manual_unit_cost: lot.manualUnitCost ?? null,
      delivery_date: lot.deliveryDate ?? null,
      track_number: lot.trackNumber ?? null,
      status: lot.status,
      legacy_status: lot.legacyStatus ?? null,
      note: lot.note ?? null
    }));

    const { data: insertedLots, error: lotError } = await supabase
      .from("purchase_lots")
      .insert(lotRows)
      .select("id, lot_code");

    if (lotError) {
      throw new Error(`addPurchaseLots: ${lotError.message}`);
    }

    if (payload.lots.length > 1 && payload.sharedFeeAllocation) {
      const lotsByCode = new Map((insertedLots ?? []).map((lot) => [lot.lot_code, lot.id]));
      for (const fee of SHARED_FEES) {
        if (payload[fee.amountKey] === 0) continue;

        const manualByLotCode = payload.sharedFeeAllocation.manualAllocations?.[fee.type];
        const manualAllocations = payload.sharedFeeAllocation.method === "manual"
          ? Object.fromEntries(payload.lots.map((lot) => {
              const lotId = lotsByCode.get(lot.lotCode);
              const allocated = manualByLotCode?.[lot.lotCode];
              if (!lotId || typeof allocated !== "number") {
                throw new Error(`Manual ${fee.type} allocation must be entered for every lot.`);
              }
              return [lotId, allocated];
            }))
          : null;

        const { error: allocationError } = await supabase.rpc("fn_allocate_purchase_shared_fee", {
          p_purchase_id: purchase.id,
          p_fee_type: fee.type,
          p_allocation_method: payload.sharedFeeAllocation.method,
          p_manual_allocations: manualAllocations
        });
        if (allocationError) {
          throw new Error(`allocate ${fee.type}: ${allocationError.message}`);
        }
      }
    }

    const { data, error } = await supabase
      .from("purchases")
      .select(PURCHASE_SELECT)
      .eq("id", purchase.id)
      .single();

    if (error) {
      throw new Error(`addPurchaseReadback: ${error.message}`);
    }

    return mapPurchase(data as PurchaseRecord);
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    const { data: lots } = await supabase
      .from("purchase_lots")
      .select("id")
      .eq("purchase_id", purchase.id);
    const lotIds = (lots ?? []).map((lot) => lot.id);
    const rollbackErrors: string[] = [];

    if (lotIds.length > 0) {
      const { error: allocationsError } = await supabase
        .from("purchase_lot_fee_allocations")
        .delete()
        .in("purchase_lot_id", lotIds);
      if (allocationsError) rollbackErrors.push(allocationsError.message);
    }
    const { error: lotsError } = await supabase.from("purchase_lots").delete().eq("purchase_id", purchase.id);
    if (lotsError) rollbackErrors.push(lotsError.message);
    const { error: purchaseDeleteError } = await supabase.from("purchases").delete().eq("id", purchase.id);
    if (purchaseDeleteError) rollbackErrors.push(purchaseDeleteError.message);

    const rollbackSuffix = rollbackErrors.length === 0
      ? "incomplete purchase rolled back"
      : `rollback failed: ${rollbackErrors.join(" | ")}`;
    throw repositoryError("addPurchase", `${message}; ${rollbackSuffix}`);
  }
}

export async function batchUpdateStatus(
  payload: BatchUpdateLotStatusPayload
): Promise<PurchaseLot[]> {
  if (payload.lotIds.length === 0) {
    return [];
  }

  const supabase = createRepositoryClient();
  const update: TablesUpdate<"purchase_lots"> = {
    status: payload.status
  };

  if ("legacyStatus" in payload) {
    update.legacy_status = payload.legacyStatus ?? null;
  }

  if ("deliveryDate" in payload) {
    update.delivery_date = payload.deliveryDate ?? null;
  }

  if ("note" in payload) {
    update.note = payload.note ?? null;
  }

  const { data, error } = await supabase
    .from("purchase_lots")
    .update(update)
    .in("id", payload.lotIds)
    .select(LOT_SELECT);

  if (error) {
    throw repositoryError("batchUpdateStatus", error.message);
  }

  return ((data ?? []) as PurchaseLotRecord[]).map(mapLot);
}
