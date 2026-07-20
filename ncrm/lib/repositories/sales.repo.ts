import type { AddSalePayload, Sale, SaleItem, UpdateSaleStatusPayload } from "@/lib/domain";
import { createRepositoryClient } from "@/lib/supabase/client";
import type { Tables, TablesInsert, TablesUpdate } from "@/lib/types/database";
import { mapLookup, repositoryError, toNumber, toNullableNumber } from "./_utils";

type SaleItemRecord = Tables<"sale_items">;
type SaleRecord = Tables<"sales"> & {
  sale_channels?: unknown;
  payment_types?: unknown;
  payment_statuses?: unknown;
  order_statuses?: unknown;
  post_methods?: unknown;
  sale_items?: SaleItemRecord[] | null;
};

const SALE_SELECT = `
  *,
  sale_channels(id, code, name_uk),
  payment_types(id, code, name_uk),
  payment_statuses(id, code, name_uk),
  order_statuses(id, code, name_uk),
  post_methods(id, code, name_uk),
  sale_items(*)
`;

function mapSaleItem(row: SaleItemRecord): SaleItem {
  return {
    id: row.id,
    saleId: row.sale_id,
    productId: row.product_id,
    qty: toNumber(row.qty),
    unitPrice: toNumber(row.unit_price),
    discountAlloc: toNumber(row.discount_alloc),
    packagingAlloc: toNumber(row.packaging_alloc),
    shopDeliveryAlloc: toNumber(row.shop_delivery_alloc),
    paymentFee: toNumber(row.payment_fee),
    prroUnit: toNullableNumber(row.prro_unit),
    mgmtUnit: toNullableNumber(row.mgmt_unit),
    costMethod: row.cost_method,
    costState: row.cost_state,
    note: row.note
  };
}

function mapSale(row: SaleRecord): Sale {
  return {
    id: row.id,
    orderNo: row.order_no,
    openCartOrderId: row.opencart_order_id,
    soldAt: row.sold_at,
    customerName: row.customer_name,
    customerPhone: row.customer_phone,
    channelId: row.channel_id,
    channel: mapLookup(row.sale_channels),
    paymentTypeId: row.payment_type_id,
    paymentType: mapLookup(row.payment_types),
    paymentStatusId: row.payment_status_id,
    paymentStatus: mapLookup(row.payment_statuses),
    orderStatusId: row.order_status_id,
    orderStatus: mapLookup(row.order_statuses),
    postMethodId: row.post_method_id,
    postMethod: mapLookup(row.post_methods),
    ttn: row.ttn,
    discountTotal: toNumber(row.discount_total),
    packagingCost: toNumber(row.packaging_cost),
    shopDelivery: toNumber(row.shop_delivery),
    note: row.note,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
    items: row.sale_items?.map(mapSaleItem) ?? []
  };
}

export async function listSales(options: { limit?: number } = {}): Promise<Sale[]> {
  const supabase = createRepositoryClient();
  const limit = options.limit ?? 50;
  const { data, error } = await supabase
    .from("sales")
    .select(SALE_SELECT)
    .order("sold_at", { ascending: false })
    .limit(limit);

  if (error) {
    throw repositoryError("listSales", error.message);
  }

  return ((data ?? []) as SaleRecord[]).map(mapSale);
}

export async function getSale(id: string): Promise<Sale | null> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("sales")
    .select(SALE_SELECT)
    .eq("id", id)
    .maybeSingle();

  if (error) {
    throw repositoryError("getSale", error.message);
  }

  return data ? mapSale(data as SaleRecord) : null;
}

export async function addSale(payload: AddSalePayload): Promise<Sale> {
  const supabase = createRepositoryClient();
  const saleInsert: TablesInsert<"sales"> = {
    created_by: payload.createdBy,
    order_no: payload.orderNo,
    opencart_order_id: payload.openCartOrderId ?? null,
    channel_id: payload.channelId,
    sold_at: payload.soldAt,
    customer_name: payload.customerName ?? null,
    customer_phone: payload.customerPhone ?? null,
    payment_type_id: payload.paymentTypeId,
    payment_status_id: payload.paymentStatusId,
    order_status_id: payload.orderStatusId,
    post_method_id: payload.postMethodId ?? null,
    ttn: payload.ttn ?? null,
    discount_total: payload.discountTotal ?? 0,
    packaging_type_id: payload.packagingTypeId ?? null,
    packaging_cost: payload.packagingCost ?? 0,
    shop_delivery: payload.shopDelivery ?? 0,
    note: payload.note ?? null
  };

  const { data: sale, error: saleError } = await supabase
    .from("sales")
    .insert(saleInsert)
    .select("*")
    .single();

  if (saleError) {
    throw repositoryError("addSale", saleError.message);
  }

  if (payload.items?.length) {
    const itemRows: TablesInsert<"sale_items">[] = payload.items.map((item) => ({
      sale_id: sale.id,
      product_id: item.productId,
      qty: item.qty,
      unit_price: item.unitPrice,
      discount_alloc: item.discountAlloc ?? 0,
      packaging_alloc: item.packagingAlloc ?? 0,
      shop_delivery_alloc: item.shopDeliveryAlloc ?? 0,
      payment_fee: item.paymentFee ?? 0,
      prro_unit: item.prroUnit ?? null,
      mgmt_unit: item.mgmtUnit ?? null,
      note: item.note ?? null
    }));

    const { error: itemsError } = await supabase.from("sale_items").insert(itemRows);

    if (itemsError) {
      throw repositoryError("addSaleItems", itemsError.message);
    }
  }

  const inserted = await getSale(sale.id);
  if (!inserted) {
    throw repositoryError("addSale", "inserted sale was not found after insert");
  }

  return inserted;
}

export async function updateSaleStatus(
  id: string,
  payload: UpdateSaleStatusPayload
): Promise<Sale | null> {
  const supabase = createRepositoryClient();
  const update: TablesUpdate<"sales"> = {};

  if (payload.orderStatusId) {
    update.order_status_id = payload.orderStatusId;
  }

  if (payload.paymentStatusId) {
    update.payment_status_id = payload.paymentStatusId;
  }

  if ("ttn" in payload) {
    update.ttn = payload.ttn ?? null;
  }

  if ("note" in payload) {
    update.note = payload.note ?? null;
  }

  if (Object.keys(update).length === 0) {
    return getSale(id);
  }

  const { data, error } = await supabase
    .from("sales")
    .update(update)
    .eq("id", id)
    .select(SALE_SELECT)
    .maybeSingle();

  if (error) {
    throw repositoryError("updateSaleStatus", error.message);
  }

  return data ? mapSale(data as SaleRecord) : null;
}
