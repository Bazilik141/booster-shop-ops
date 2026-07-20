import { LoginForm } from "./login-form";

export default function LoginPage() {
  return (
    <main className="page">
      <section className="hero">
        <div className="eyebrow">NCRM · локальний доступ</div>
        <h1>Вхід до NCRM</h1>
        <p>Доступ мають лише співробітники з роллю owner або admin.</p>
      </section>
      <section className="card stack" aria-label="Форма входу">
        <LoginForm />
      </section>
    </main>
  );
}
