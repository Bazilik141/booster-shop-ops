import { createClient } from "npm:@supabase/supabase-js@2";

type OpenCartItem = {
  name?: unknown;
  sku?: unknown;
  model?: unknown;
  quantity?: unknown;
  unit_price?: unknown;
  price?: unknown;
  total?: unknown;
};

type OpenCartTotal = {
  code?: unknown;
  value?: unknown;
};

type OpenCartPayload = {
  event?: unknown;
  order_id?: unknown;
  order_key?: unknown;
  date_added?: unknown;
  firstname?: unknown;
  lastname?: unknown;
  customer_name?: unknown;
  telephone?: unknown;
  comment?: unknown;
  payment_method_name?: unknown;
  payment_method_code?: unknown;
  shipping_method_name?: unknown;
  shipping_method_code?: unknown;
  products?: unknown;
  totals?: unknown;
};

const jsonHeaders = {
  "content-type": "application/json; charset=utf-8",
  "cache-control": "no-store",
};

function json(body: Record<string, unknown>, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: jsonHeaders });
}

function text(value: unknown): string {
  return typeof value === "string" ? value.trim() : "";
}

function numberValue(value: unknown): number | null {
  if (typeof value === "number" && Number.isFinite(value)) return value;
  if (typeof value === "string" && value.trim() !== "") {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }
  return null;
}

function orderIdText(value: unknown): string {
  if (typeof value === "number" && Number.isSafeInteger(value)) {
    return String(value);
  }

  return text(value);
}

function itemUnitPrice(item: OpenCartItem): number | null {
  return numberValue(item.unit_price) ?? numberValue(item.price);
}

function normalize(value: unknown): string {
  return text(value).replace(/\s+/g, " ").toLocaleLowerCase("uk-UA");
}

function normalizePhone(value: unknown): string {
  return text(value).replace(/\D/g, "");
}

function sameSecret(expected: string, actual: string): boolean {
  if (!expected || expected.length !== actual.length) return false;

  let mismatch = 0;

  for (let index = 0; index < expected.length; index += 1) {
    mismatch |= expected.charCodeAt(index) ^ actual.charCodeAt(index);
  }

  return mismatch === 0;
}

function isTestOrder(payload: OpenCartPayload, items: OpenCartItem[]): boolean {
  const lastname = normalize(payload.lastname);
  const phone = normalizePhone(payload.telephone);

  if (lastname.includes("леусенко") || phone.endsWith("0991119279")) {
    return true;
  }

  const texts = [
    payload.comment,
    ...items.flatMap((item) => [item.name, item.sku, item.model]),
  ];

  return texts.some((value) => {
    const normalized = normalize(value);
    return normalized.includes("тест") || normalized.includes("test");
  });
}

function canonicalSku(item: OpenCartItem): string {
  const raw = text(item.sku) || text(item.model);

  if (raw === "PKM-KR-HWA-BST") return "PKM-KR-HWAK-BST";
  if (raw === "PKM-KR-HWA-BBX") return "PKM-KR-HWAK-BBX";

  return raw;
}

function paymentTypeCode(payload: OpenCartPayload): string {
  const value = normalize(
    text(payload.payment_method_name) + " " + text(payload.payment_method_code),
  );

  const monoParts = value.match(/mono_chast[._-]mono_chast_([345])/);
  if (monoParts) {
    return `credit_mono_${monoParts[1]}`;
  }

  if (
    value.includes("рекв") ||
    value.includes("bank") ||
    value.includes("iban")
  ) {
    return "bank_details";
  }

  if (
    value.includes("після") ||
    value.includes("cod") ||
    value.includes("налож") ||
    value.includes("nova pay") ||
    value.includes("novapay")
  ) {
    return "fop_control";
  }

  return "acquiring";
}

function postMethodCode(payload: OpenCartPayload): string {
  const value = normalize(
    text(payload.shipping_method_name) + " " + text(payload.shipping_method_code),
  );

  if (value.includes("нова") || value.includes("novaposhta") || value.includes("novapost")) {
    return "nova_poshta";
  }
  if (value.includes("укр") || value.includes("ukrposhta")) return "ukrposhta";
  if (value.includes("meest")) return "meest";
  if (value.includes("самов") || value.includes("pickup")) return "pickup";

  return "other";
}

function discountTotal(totals: OpenCartTotal[]): number {
  return totals.reduce((sum, item) => {
    const code = normalize(item.code);
    const value = numberValue(item.value);

    return value !== null && value < 0 && code !== "total"
      ? sum + Math.abs(value)
      : sum;
  }, 0);
}

function toSoldAt(value: unknown): string | null {
  const match = text(value).match(/^(\d{4}-\d{2}-\d{2})/);
  return match ? match[1] : null;
}

function validatePayload(payload: OpenCartPayload): {
  orderId: string;
  orderNo: string;
  soldAt: string;
  items: OpenCartItem[];
  totals: OpenCartTotal[];
} | { error: string } {
  const orderId = orderIdText(payload.order_id);
  const orderNo = text(payload.order_key) || "OC-FOP-" + orderId.padStart(4, "0");
  const soldAt = toSoldAt(payload.date_added);
  const items = Array.isArray(payload.products) ? payload.products as OpenCartItem[] : [];
  const totals = Array.isArray(payload.totals) ? payload.totals as OpenCartTotal[] : [];

  if (!/^\d+$/.test(orderId) || !/^\d+$/.test(orderNo.replace(/^OC-FOP-/, ""))) {
    return { error: "invalid OpenCart order identifier" };
  }
  if (!/^OC-FOP-\d{4,}$/.test(orderNo)) return { error: "invalid order number" };
  if (!soldAt) return { error: "date_added is required" };
  if (items.length === 0) return { error: "products are required" };

  for (const item of items) {
    const sku = canonicalSku(item);
    const quantity = numberValue(item.quantity);
    const price = itemUnitPrice(item);

    if (!sku || quantity === null || !Number.isInteger(quantity) || quantity <= 0 || price === null || price < 0) {
      return { error: "each product needs SKU, positive integer quantity, and non-negative price" };
    }
  }

  return { orderId, orderNo, soldAt, items, totals };
}

Deno.serve(async (request) => {
  if (request.method !== "POST") return json({ error: "method not allowed" }, 405);

  const expectedSecret = Deno.env.get("ORDER_SYNC_SHARED_SECRET") ?? "";
  const providedSecret = request.headers.get("x-order-sync-secret") ?? "";

  if (!expectedSecret) return json({ error: "server misconfigured" }, 500);
  if (!sameSecret(expectedSecret, providedSecret)) return json({ error: "unauthorized" }, 401);

  let payload: OpenCartPayload;

  try {
    payload = await request.json();
  } catch {
    return json({ error: "invalid JSON" }, 400);
  }

  if (text(payload.event) !== "order_add") {
    return json({ skipped: true, reason: "event-not-in-scope" });
  }

  const validated = validatePayload(payload);
  if ("error" in validated) return json({ error: validated.error }, 400);
  if (isTestOrder(payload, validated.items)) {
    return json({ skipped: true, reason: "test-filter" });
  }

  const supabaseUrl = Deno.env.get("SUPABASE_URL") ?? "";
  const serviceRoleKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? "";
  if (!supabaseUrl || !serviceRoleKey) return json({ error: "server misconfigured" }, 500);

  const customerName = text(payload.customer_name) ||
    [text(payload.firstname), text(payload.lastname)].filter(Boolean).join(" ");
  const note = [
    "OpenCart #" + validated.orderId,
    text(payload.comment) ? "Коментар: " + text(payload.comment) : "",
  ].filter(Boolean).join("; ");
  const rpcPayload = {
    opencart_order_id: validated.orderId,
    order_no: validated.orderNo,
    sold_at: validated.soldAt,
    customer_phone: text(payload.telephone),
    customer_name: customerName,
    payment_type_code: paymentTypeCode(payload),
    payment_status_code: "unpaid",
    order_status_code: "new",
    post_method_code: postMethodCode(payload),
    discount_total: discountTotal(validated.totals),
    packaging_cost: 0,
    shop_delivery: 0,
    note,
    items: validated.items.map((item) => ({
      sku: canonicalSku(item),
      qty: numberValue(item.quantity),
      unit_price: itemUnitPrice(item),
      note: text(item.name),
    })),
  };
  const supabase = createClient(supabaseUrl, serviceRoleKey, {
    auth: { autoRefreshToken: false, persistSession: false },
  });
  const { data, error } = await supabase.rpc("fn_ingest_opencart_order", {
    p_payload: rpcPayload,
  });

  if (error) {
    console.error("order-sync RPC failed", {
      orderId: validated.orderId,
      code: error.code,
      message: error.message,
    });
    return json({ error: "order ingestion failed" }, 422);
  }

  return json(data as Record<string, unknown>);
});
