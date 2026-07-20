import { readFile } from "node:fs/promises";
import { createClient } from "@supabase/supabase-js";

async function loadEnvLocal() {
  try {
    const content = await readFile(new URL("../.env.local", import.meta.url), "utf8");
    for (const line of content.split(/\r?\n/)) {
      const match = line.match(/^\s*([A-Z_][A-Z0-9_]*)\s*=\s*(.*?)\s*$/);
      if (!match || process.env[match[1]]) continue;
      const [, key, rawValue] = match;
      const value = rawValue.replace(/^("|')|\1$/g, "");
      process.env[key] = value;
    }
  } catch (error) {
    if (error?.code !== "ENOENT") throw error;
  }
}

function requiredEnv(name) {
  const value = process.env[name];
  if (!value) throw new Error(`Missing required ${name} in .env.local.`);
  return value;
}

await loadEnvLocal();

try {
  const url = requiredEnv("NEXT_PUBLIC_SUPABASE_URL");
  const serviceRoleKey = requiredEnv("SUPABASE_SERVICE_ROLE_KEY");
  const email = requiredEnv("OWNER_EMAIL").trim().toLowerCase();
  const password = requiredEnv("OWNER_PASSWORD");
  const supabase = createClient(url, serviceRoleKey, {
    auth: { autoRefreshToken: false, persistSession: false }
  });

  const { data: users, error: usersError } = await supabase.auth.admin.listUsers({
    page: 1,
    perPage: 1000
  });
  if (usersError) throw usersError;

  let user = users.users.find((candidate) => candidate.email?.toLowerCase() === email);
  let userAction = "existing";
  if (!user) {
    const { data, error } = await supabase.auth.admin.createUser({
      email,
      password,
      email_confirm: true
    });
    if (error || !data.user) throw error ?? new Error("Auth user was not returned after creation.");
    user = data.user;
    userAction = "created";
  }

  const { data: staff, error: staffReadError } = await supabase
    .from("staff")
    .select("id, role")
    .eq("id", user.id)
    .maybeSingle();
  if (staffReadError) throw staffReadError;

  let staffAction = "existing";
  if (!staff) {
    const { error } = await supabase.from("staff").insert({
      id: user.id,
      display_name: email,
      role: "owner"
    });
    if (error) throw error;
    staffAction = "created";
  } else if (staff.role !== "owner") {
    throw new Error(`Staff row for ${email} already has role ${staff.role}; owner role was not changed.`);
  }

  console.log(`done=ok auth_user=${userAction} staff=${staffAction} role=owner`);
} catch (error) {
  console.error(`done=error message=${error instanceof Error ? error.message : String(error)}`);
  process.exitCode = 1;
}
