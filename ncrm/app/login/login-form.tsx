"use client";

import { type FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { createSupabaseBrowserAppClient } from "@/lib/supabase/client";

export function LoginForm() {
  const router = useRouter();
  const [message, setMessage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setMessage(null);
    setIsSubmitting(true);

    const form = new FormData(event.currentTarget);
    const supabase = createSupabaseBrowserAppClient();
    const { error } = await supabase.auth.signInWithPassword({
      email: String(form.get("email") ?? ""),
      password: String(form.get("password") ?? "")
    });

    setIsSubmitting(false);
    if (error) {
      setMessage("Не вдалося увійти. Перевірте email і пароль.");
      return;
    }

    router.replace("/");
    router.refresh();
  }

  return (
    <form className="stack" onSubmit={handleSubmit}>
      <label>
        Email
        <input name="email" type="email" autoComplete="email" required />
      </label>
      <label>
        Пароль
        <input name="password" type="password" autoComplete="current-password" required />
      </label>
      <button type="submit" disabled={isSubmitting}>
        {isSubmitting ? "Вхід…" : "Увійти"}
      </button>
      {message ? <p className="warning">{message}</p> : null}
    </form>
  );
}
