import type {
  MysteryBoxType,
  MysteryComponentSelection,
  MysteryEligibleComponent,
  MysteryFulfillmentState,
  MysteryQueueItem
} from "@/lib/domain";
import { createRepositoryClient } from "@/lib/supabase/client";
import type { Json, Tables } from "@/lib/types/database";
import { repositoryError, toNumber } from "./_utils";

type ProductMini = { id?: string | null; sku?: string | null; name?: string | null; full_name?: string | null };
type OrderStatusMini = { code?: string | null; name_uk?: string | null };
type SaleMini = { order_no?: string | null; customer_name?: string | null; customer_phone?: string | null; order_statuses?: OrderStatusMini | OrderStatusMini[] | null };
type SaleItemJoin = Tables<"sale_items"> & { products?: ProductMini | ProductMini[] | null; sales?: SaleMini | SaleMini[] | null };
type FulfillmentJoin = Tables<"mystery_fulfillments"> & { sale_items?: SaleItemJoin | SaleItemJoin[] | null };
type EligibleRow = Tables<"v_mystery_eligible_components">;
type BoxTypeRow = Tables<"mystery_box_types">;

const FULFILLMENT_SELECT = `
  id, state, reserved_at, committed_at, released_at, sale_item_id,
  sale_items(
    id, qty,
    products(id, sku, name, full_name),
    sales(order_no, customer_name, customer_phone, order_statuses(code, name_uk))
  )
`;

function first<T>(value: T | T[] | null | undefined): T | null {
  return Array.isArray(value) ? value[0] ?? null : value ?? null;
}

function mapFulfillment(row: FulfillmentJoin): MysteryQueueItem {
  const saleItem = first(row.sale_items);
  const product = first(saleItem?.products);
  const sale = first(saleItem?.sales);
  const status = first(sale?.order_statuses);
  if (!saleItem?.id || !product?.id || !product.sku || !sale?.order_no) {
    throw repositoryError("mapMysteryFulfillment", "missing sale or Mystery product context");
  }

  return {
    fulfillmentId: row.id,
    saleItemId: saleItem.id,
    state: row.state as MysteryFulfillmentState,
    reservedAt: row.reserved_at,
    committedAt: row.committed_at,
    releasedAt: row.released_at,
    orderNo: sale.order_no,
    customerName: sale.customer_name ?? null,
    customerPhone: sale.customer_phone ?? null,
    orderStatusCode: status?.code ?? null,
    orderStatusName: status?.name_uk ?? null,
    mysteryProductId: product.id,
    mysterySku: product.sku,
    mysteryName: product.full_name ?? product.name ?? null,
    saleQty: toNumber(saleItem.qty)
  };
}

export async function listMysteryQueue(
  options: { states?: MysteryFulfillmentState[] } = {}
): Promise<MysteryQueueItem[]> {
  const supabase = createRepositoryClient();
  const states = options.states ?? ["needs_assembly", "reserved"];
  const { data, error } = await supabase
    .from("mystery_fulfillments")
    .select(FULFILLMENT_SELECT)
    .in("state", states)
    .order("created_at", { ascending: true });

  if (error) throw repositoryError("listMysteryQueue", error.message);
  return ((data ?? []) as unknown as FulfillmentJoin[]).map(mapFulfillment);
}

export async function getMysteryFulfillment(saleItemId: string): Promise<MysteryQueueItem | null> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("mystery_fulfillments")
    .select(FULFILLMENT_SELECT)
    .eq("sale_item_id", saleItemId)
    .maybeSingle();

  if (error) throw repositoryError("getMysteryFulfillment", error.message);
  return data ? mapFulfillment(data as unknown as FulfillmentJoin) : null;
}

export async function getEligibleComponents(mysteryProductId: string): Promise<MysteryEligibleComponent[]> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("v_mystery_eligible_components")
    .select("component_product_id, component_sku, component_name, physical_qty, reserved_qty, available_qty")
    .eq("mystery_product_id", mysteryProductId)
    .order("component_sku", { ascending: true });

  if (error) throw repositoryError("getEligibleComponents", error.message);
  return ((data ?? []) as EligibleRow[])
    .filter((row) => row.component_product_id && row.component_sku)
    .map((row) => ({
      productId: row.component_product_id as string,
      sku: row.component_sku as string,
      name: row.component_name,
      physicalQty: toNumber(row.physical_qty),
      reservedQty: toNumber(row.reserved_qty),
      availableQty: toNumber(row.available_qty)
    }));
}

export async function getMysteryBoxType(productId: string): Promise<MysteryBoxType | null> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("mystery_box_types")
    .select("product_id, expected_pack_count, has_holo, holo_cost, provisional_unit_cost")
    .eq("product_id", productId)
    .maybeSingle();

  if (error) throw repositoryError("getMysteryBoxType", error.message);
  if (!data) return null;
  const row = data as BoxTypeRow;
  return {
    productId: row.product_id,
    expectedPackCount: toNumber(row.expected_pack_count),
    hasHolo: row.has_holo,
    holoCost: toNumber(row.holo_cost),
    provisionalUnitCost: toNumber(row.provisional_unit_cost)
  };
}

export async function reserveMysteryFulfillment(
  saleItemId: string,
  components: MysteryComponentSelection[],
  staffId: string
) {
  const supabase = createRepositoryClient();
  const payload: Json = components.map((component) => ({ product_id: component.productId, qty: component.qty }));
  const { data, error } = await supabase.rpc("fn_reserve_mystery_fulfillment", {
    p_sale_item_id: saleItemId,
    p_components: payload
  });
  if (error) throw repositoryError("reserveMysteryFulfillment", error.message);

  const fulfillment = data as Tables<"mystery_fulfillments">;
  const { error: attributionError } = await supabase
    .from("mystery_fulfillments")
    .update({ created_by: staffId })
    .eq("id", fulfillment.id)
    .is("created_by", null);
  if (attributionError) {
    throw repositoryError("reserveMysteryFulfillment.createdBy", attributionError.message);
  }

  return fulfillment;
}

export async function commitMysteryFulfillment(saleItemId: string) {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase.rpc("fn_commit_mystery_fulfillment", { p_sale_item_id: saleItemId });
  if (error) throw repositoryError("commitMysteryFulfillment", error.message);
  return data as Tables<"mystery_fulfillments">;
}

export async function releaseMysteryFulfillment(saleItemId: string) {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase.rpc("fn_release_mystery_fulfillment", { p_sale_item_id: saleItemId });
  if (error) throw repositoryError("releaseMysteryFulfillment", error.message);
  return data as Tables<"mystery_fulfillments">;
}
