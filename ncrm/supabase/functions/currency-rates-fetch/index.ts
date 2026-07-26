import { createClient } from "npm:@supabase/supabase-js@2";

type Currency = "USD" | "EUR" | "JPY";

type RateSource = "ПриватБанк" | "Monobank" | "НБУ";

type RateResult = {
  currency: Currency;
  rate: number;
  source: RateSource;
};

type PrivatRate = {
  ccy?: unknown;
  base_ccy?: unknown;
  sale?: unknown;
};

type MonobankRate = {
  currencyCodeA?: unknown;
  currencyCodeB?: unknown;
  rateSell?: unknown;
};

type NbuRate = {
  cc?: unknown;
  rate?: unknown;
};

const PRIVAT_URL = "https://api.privatbank.ua/p24api/pubinfo?json&exchange&coursid=5";
const MONOBANK_URL = "https://api.monobank.ua/bank/currency";
const NBU_URL = "https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?json&valcode=";
const CURRENCIES: Currency[] = ["USD", "EUR", "JPY"];
const MONOBANK_CODES: Record<Currency, number> = {
  USD: 840,
  EUR: 978,
  JPY: 392,
};
const jsonHeaders = {
  "content-type": "application/json; charset=utf-8",
  "cache-control": "no-store",
};

let privatPayload: Promise<unknown> | undefined;
let monobankPayload: Promise<unknown> | undefined;

function json(body: Record<string, unknown>, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: jsonHeaders });
}

function errorMessage(error: unknown): string {
  return error instanceof Error ? error.message : String(error);
}

function positiveNumber(value: unknown): number | null {
  const numberValue = typeof value === "number" ? value : Number(value);
  return Number.isFinite(numberValue) && numberValue > 0 ? numberValue : null;
}

function requirePositive(value: unknown, context: string): number {
  const rate = positiveNumber(value);
  if (rate === null) throw new Error(`${context}: invalid or non-positive rate`);
  return rate;
}

async function fetchJson(url: string, source: string): Promise<unknown> {
  const response = await fetch(url, {
    headers: { accept: "application/json" },
    signal: AbortSignal.timeout(10_000),
  });

  if (!response.ok) throw new Error(`${source}: HTTP ${response.status}`);
  return await response.json();
}

function loadPrivat(): Promise<unknown> {
  privatPayload ??= fetchJson(PRIVAT_URL, "ПриватБанк");
  return privatPayload;
}

function loadMonobank(): Promise<unknown> {
  monobankPayload ??= fetchJson(MONOBANK_URL, "Monobank");
  return monobankPayload;
}

function kyivDate(): string {
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Europe/Kyiv",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).formatToParts(new Date());
  const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));

  return `${values.year}-${values.month}-${values.day}`;
}

async function privatRate(currency: Exclude<Currency, "JPY">): Promise<RateResult> {
  const payload = await loadPrivat();
  if (!Array.isArray(payload)) throw new Error("ПриватБанк: response is not an array");

  const item = (payload as PrivatRate[]).find((candidate) =>
    candidate.ccy === currency && candidate.base_ccy === "UAH"
  );
  if (!item) throw new Error(`ПриватБанк: ${currency}/UAH not found`);

  // sale = UAH paid by the store to buy one unit of the foreign currency.
  return {
    currency,
    rate: requirePositive(item.sale, `ПриватБанк ${currency}`),
    source: "ПриватБанк",
  };
}

async function monobankRate(currency: Exclude<Currency, "JPY">): Promise<RateResult> {
  const payload = await loadMonobank();
  if (!Array.isArray(payload)) throw new Error("Monobank: response is not an array");

  const item = (payload as MonobankRate[]).find((candidate) =>
    candidate.currencyCodeA === MONOBANK_CODES[currency] && candidate.currencyCodeB === 980
  );
  if (!item) throw new Error(`Monobank: ${currency}/UAH not found`);

  // rateSell = UAH paid by the store to buy one unit of the foreign currency.
  return {
    currency,
    rate: requirePositive(item.rateSell, `Monobank ${currency}`),
    source: "Monobank",
  };
}

async function nbuRate(currency: Currency, buffer: number): Promise<RateResult> {
  const payload = await fetchJson(`${NBU_URL}${currency}`, "НБУ");
  if (!Array.isArray(payload)) throw new Error("НБУ: response is not an array");

  const item = (payload as NbuRate[]).find((candidate) => candidate.cc === currency);
  if (!item) throw new Error(`НБУ: ${currency} not found`);

  return {
    currency,
    rate: requirePositive(item.rate, `НБУ ${currency}`) * (1 + buffer),
    source: "НБУ",
  };
}

async function resolveRate(currency: Currency, buffer: number): Promise<RateResult> {
  if (currency === "JPY") return await nbuRate(currency, buffer);

  try {
    return await privatRate(currency);
  } catch (error) {
    console.warn("currency-rates-fetch fallback", {
      currency,
      failedSource: "ПриватБанк",
      error: errorMessage(error),
    });
  }

  try {
    return await monobankRate(currency);
  } catch (error) {
    console.warn("currency-rates-fetch fallback", {
      currency,
      failedSource: "Monobank",
      error: errorMessage(error),
    });
  }

  return await nbuRate(currency, buffer);
}

Deno.serve(async (request) => {
  if (request.method !== "POST") return json({ error: "method not allowed" }, 405);

  const supabaseUrl = Deno.env.get("SUPABASE_URL") ?? "";
  const serviceRoleKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? "";
  if (!supabaseUrl || !serviceRoleKey) return json({ error: "server misconfigured" }, 500);

  const supabase = createClient(supabaseUrl, serviceRoleKey, {
    auth: { autoRefreshToken: false, persistSession: false },
  });
  const { data: config, error: configError } = await supabase
    .from("v_current_app_config")
    .select("value_num")
    .eq("key", "nbu_rate_buffer_pct")
    .maybeSingle();
  const buffer = positiveNumber(config?.value_num);

  if (configError || buffer === null || buffer >= 1) {
    console.error("currency-rates-fetch config failed", {
      code: configError?.code,
      message: configError?.message,
    });
    return json({ error: "nbu_rate_buffer_pct is unavailable or invalid" }, 500);
  }

  const asOf = kyivDate();
  const results = await Promise.all(CURRENCIES.map(async (currency) => {
    try {
      const resolved = await resolveRate(currency, buffer);
      const { error } = await supabase.from("currency_rates").upsert({
        currency: resolved.currency,
        rate_to_uah: resolved.rate,
        as_of: asOf,
        source: resolved.source,
        note: null,
      }, { onConflict: "currency,as_of" });

      if (error) throw new Error(`currency_rates upsert: ${error.code ?? "unknown"} ${error.message}`);
      return { currency, source: resolved.source, rate_to_uah: resolved.rate };
    } catch (error) {
      console.error("currency-rates-fetch currency failed", {
        currency,
        error: errorMessage(error),
      });
      return { currency, error: "fetch or write failed" };
    }
  }));
  const failed = results.filter((result) => "error" in result);

  return json({
    as_of: asOf,
    written: results.filter((result) => !("error" in result)),
    failed,
  }, failed.length === CURRENCIES.length ? 502 : 200);
});
