// App shell — top tab nav switches between the 6 redesigned content pages.
// Persisted in URL hash so the user can deep-link to a specific page review.

const PAGES = [
  { id: 'order',     label: '1 · Замовлення прийнято', Comp: OrderSuccessPage },
  { id: 'guarantee', label: '2 · Гарантія',            Comp: GuaranteePage },
  { id: 'about',     label: '3 · Про нас',             Comp: AboutPage },
  { id: 'delivery',  label: '4 · Оплата і доставка',   Comp: DeliveryPage },
  { id: 'returns',   label: '5 · Обмін і повернення',  Comp: ReturnsPage },
  { id: 'offer',     label: '6 · Публічна оферта',     Comp: OfferPage },
];

function getInitialPage() {
  const h = (location.hash || '').replace('#', '');
  return PAGES.find((p) => p.id === h) ? h : 'order';
}

function ContentApp() {
  const [active, setActive] = React.useState(getInitialPage);

  const onSelect = (id) => {
    setActive(id);
    history.replaceState(null, '', '#' + id);
    window.scrollTo({ top: 0, behavior: 'instant' });
  };

  React.useEffect(() => {
    const onHash = () => {
      const id = (location.hash || '').replace('#', '');
      if (PAGES.find((p) => p.id === id)) setActive(id);
    };
    window.addEventListener('hashchange', onHash);
    return () => window.removeEventListener('hashchange', onHash);
  }, []);

  const Active = (PAGES.find((p) => p.id === active) || PAGES[0]).Comp;

  return (
    <div style={{ minHeight: '100vh', background: 'var(--bs-bg)', fontFamily: 'Manrope, system-ui, sans-serif' }}>
      <PageTabs pages={PAGES} active={active} onSelect={onSelect} />
      <Active />
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<ContentApp />);
