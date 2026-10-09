// Desktop-only toast variants (mobile keeps the full-width strip).
// A — картка в правому верхньому куті · B — смужка по ширині контенту · C — прив'язана до кнопки кошика
function ToastCard({ ok, title, text, children, style }) {
  return (
    <div role={ok ? 'status' : 'alert'} aria-live="polite" style={{ background: 'var(--bs-paper)', border: '1px solid ' + (ok ? '#BBE7CC' : '#F3C0C0'), borderRadius: 'var(--bs-r)', boxShadow: 'var(--bs-sh-pop)', padding: 14, display: 'grid', gap: 10, pointerEvents: 'auto', ...style }}>
      <div style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
        <span style={{ flex: '0 0 auto', width: 28, height: 28, borderRadius: 999, display: 'grid', placeItems: 'center', background: ok ? 'var(--bs-green-soft)' : '#FDECEC', color: ok ? 'var(--bs-green-hover)' : 'var(--bs-danger)' }}>{ok ? <Ic.check s={17}/> : <Ic.warn s={17}/>}</span>
        <div style={{ flex: '1 1 auto', minWidth: 0 }}>
          <div style={{ fontSize: 13.5, fontWeight: 700, color: 'var(--bs-ink)' }}>{title}</div>
          <div style={{ fontSize: 12.5, color: 'var(--bs-ink-3)', lineHeight: 1.45, marginTop: 2, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>{text}</div>
        </div>
      </div>
      {children}
    </div>
  );
}

function DesktopToast({ toast, variant, onClose, onOpenCart }) {
  if (!toast) return null;
  const ok = toast.type === 'success';
  const close = <button onClick={onClose} aria-label="Закрити" style={{ width: 32, height: 32, display: 'grid', placeItems: 'center', background: 'transparent', border: 0, color: ok ? 'var(--bs-ink-4)' : 'var(--bs-ink-3)' }}><Ic.x s={15}/></button>;

  if (variant === 'corner') {
    return (
      <div style={{ position: 'absolute', top: 74, right: 24, width: 300, animation: 'rdFadeIn .2s ease-out' }}>
        <ToastCard ok={ok} title={ok ? 'Товар у кошику' : 'Не вдалося додати'} text={toast.message}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <button className="bs-btn bs-btn-primary" onClick={onOpenCart} style={{ flex: '1 1 auto', height: 38, padding: '0 10px', fontSize: 13 }}>{ok ? 'Переглянути кошик' : 'Відкрити кошик'}</button>
            <button className="bs-btn" onClick={onClose} style={{ flex: '1 1 auto', height: 38, padding: '0 10px', fontSize: 13, background: '#fff', color: 'var(--bs-ink-2)', border: '1px solid var(--bs-line)' }}>Продовжити</button>
          </div>
        </ToastCard>
      </div>
    );
  }

  if (variant === 'inline') {
    return (
      <div style={{ position: 'absolute', top: 81, left: 0, right: 0, display: 'flex', justifyContent: 'center', animation: 'rdSlideDown .22s ease-out' }}>
        <div role={ok ? 'status' : 'alert'} style={{ pointerEvents: 'auto', width: 'min(1132px, calc(100% - 48px))', background: ok ? 'var(--bs-green-soft)' : '#FDECEC', border: '1px solid ' + (ok ? '#BBE7CC' : '#F3C0C0'), borderRadius: 'var(--bs-r)', boxShadow: 'var(--bs-sh)', padding: '12px 14px', display: 'flex', alignItems: 'center', gap: 12 }}>
          <span style={{ color: ok ? 'var(--bs-green-hover)' : 'var(--bs-danger)', display: 'inline-flex' }}>{ok ? <Ic.check s={20}/> : <Ic.warn s={20}/>}</span>
          <div style={{ flex: '1 1 auto', fontSize: 13.5, fontWeight: 600, color: ok ? '#14532D' : '#7F1D1D', lineHeight: 1.4 }}>{toast.message}</div>
          <button onClick={onOpenCart} style={{ background: 'transparent', border: 0, padding: 0, fontSize: 13, fontWeight: 700, color: ok ? 'var(--bs-green-hover)' : 'var(--bs-danger)', textDecoration: 'underline', textUnderlineOffset: 3 }}>Переглянути кошик</button>
          {close}
        </div>
      </div>
    );
  }

  // anchored — під кнопкою кошика в шапці, з хвостиком
  return (
    <div style={{ position: 'absolute', top: 74, right: 24, width: 340, animation: 'rdFadeIn .18s ease-out' }}>
      <div style={{ position: 'absolute', top: -6, right: 46, width: 12, height: 12, background: 'var(--bs-paper)', borderLeft: '1px solid ' + (ok ? '#BBE7CC' : '#F3C0C0'), borderTop: '1px solid ' + (ok ? '#BBE7CC' : '#F3C0C0'), transform: 'rotate(45deg)' }}></div>
      <ToastCard ok={ok} title={ok ? 'Додано в кошик' : 'Не вдалося додати'} text={toast.message}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <button className="bs-btn bs-btn-primary" onClick={onOpenCart} style={{ flex: '1 1 auto', height: 40, fontSize: 13 }}>Оформити замовлення</button>
          {close}
        </div>
      </ToastCard>
    </div>
  );
}

Object.assign(window, { DesktopToast, ToastCard });
