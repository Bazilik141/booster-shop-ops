// Mobile search redesign — two interactive concepts for fixing the
// hard-to-tap live-search field. Both keep live results IN PLACE and never
// redirect to /search. Self-contained: own icons, own mini product set.

const { useState: uS, useRef: uR, useEffect: uE } = React;

/* ---------- icons (currentColor) ---------------------------------------- */
const Ic = {
  Search: (p) => (<svg {...p} viewBox="0 0 20 20" fill="none"><circle cx="9" cy="9" r="6" stroke="currentColor" strokeWidth="1.7"/><path d="M14 14l4 4" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></svg>),
  Cart:   (p) => (<svg {...p} viewBox="0 0 20 20" fill="none"><path d="M3 4h2l2 10h9l2-7H6" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" strokeLinecap="round"/><circle cx="8" cy="17" r="1.2" fill="currentColor"/><circle cx="15" cy="17" r="1.2" fill="currentColor"/></svg>),
  Menu:   (p) => (<svg {...p} viewBox="0 0 18 18" fill="none"><path d="M2 4.5h14M2 9h14M2 13.5h14" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></svg>),
  Close:  (p) => (<svg {...p} viewBox="0 0 16 16" fill="none"><path d="M3.5 3.5l9 9M12.5 3.5l-9 9" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"/></svg>),
  Back:   (p) => (<svg {...p} viewBox="0 0 18 18" fill="none"><path d="M11 3.5L5.5 9 11 14.5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  Clock:  (p) => (<svg {...p} viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="5.6" stroke="currentColor" strokeWidth="1.4"/><path d="M8 5v3.2l2 1.3" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  Trend:  (p) => (<svg {...p} viewBox="0 0 16 16" fill="none"><path d="M2 11l3.5-3.5 2.5 2.5L14 4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/><path d="M10.5 4H14v3.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  Arrow:  (p) => (<svg {...p} viewBox="0 0 16 16" fill="none"><path d="M3 8h9M8.5 4l4 4-4 4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  Chevron:(p) => (<svg {...p} viewBox="0 0 14 14" fill="none"><path d="M4 2.5L9 7l-5 4.5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  User:   (p) => (<svg {...p} viewBox="0 0 20 20" fill="none"><circle cx="10" cy="7" r="3.2" stroke="currentColor" strokeWidth="1.6"/><path d="M3.5 17c.7-3.3 3.4-5 6.5-5s5.8 1.7 6.5 5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"/></svg>),
  Tg:     (p) => (<svg {...p} viewBox="0 0 20 20" fill="none"><path d="M3 9.5L17 4l-2 13-4-2-2 3-1-4 8-7-9 5-4-1.5z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/></svg>),
  Truck:  (p) => (<svg {...p} viewBox="0 0 18 16" fill="none"><path d="M1 3h10v8H1zM11 6h4l2 3v2h-6z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/><circle cx="5" cy="13" r="1.5" stroke="currentColor" strokeWidth="1.4"/><circle cx="13" cy="13" r="1.5" stroke="currentColor" strokeWidth="1.4"/></svg>),
  Shield: (p) => (<svg {...p} viewBox="0 0 16 16" fill="none"><path d="M8 1.5l5.5 2v4c0 4-2.4 6.4-5.5 7-3.1-.6-5.5-3-5.5-7v-4L8 1.5z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/><path d="M5.5 7.7l2 2 3-3.4" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  Bag:    (p) => (<svg {...p} viewBox="0 0 16 16" fill="none"><path d="M3 5h10l-.8 8.5H3.8L3 5z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/><path d="M5.5 5V4a2.5 2.5 0 015 0v1" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round"/></svg>),
  Help:   (p) => (<svg {...p} viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" strokeWidth="1.4"/><path d="M6.4 6.2c.2-1 1-1.6 1.9-1.5.9 0 1.6.7 1.6 1.5 0 1.1-1.3 1.2-1.5 2.2" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round"/><circle cx="8.2" cy="11" r=".8" fill="currentColor"/></svg>),
  Orders: (p) => (<svg {...p} viewBox="0 0 16 16" fill="none"><path d="M2 5l6-2.6L14 5v6l-6 2.6L2 11V5z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/><path d="M2 5l6 2.6L14 5M8 7.6V13.6" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/></svg>),
};

/* ---------- mock catalogue for live results ----------------------------- */
const CATALOG = [
  { id: 1, title: 'Бустер Pokémon TCG: Mega Symphonia', cat: 'Pokémon TCG', price: 150 },
  { id: 2, title: 'Бустер Pokémon TCG: Mega Brave', cat: 'Pokémon TCG', price: 150 },
  { id: 3, title: 'Бустер Pokémon TCG: Ninja Spinner', cat: 'Pokémon TCG', price: 150 },
  { id: 4, title: 'Бустер Pokémon TCG: Wild Force', cat: 'Pokémon TCG', price: 170 },
  { id: 5, title: 'Бустер One Piece OP-11', cat: 'One Piece', price: 165 },
  { id: 6, title: 'Бустер One Piece EB-03', cat: 'One Piece', price: 180 },
  { id: 7, title: 'One Piece Mystery Box', cat: 'One Piece', price: 450 },
  { id: 8, title: 'Бустер-бокс Pokémon TCG: Mega Evolution', cat: 'Бустер-бокси', price: 4200 },
];
const RECENT = ['mega evolution', 'one piece op-11', 'бустер-бокс'];
const POPULAR = ['Mega Brave', 'Бустер-бокси', 'One Piece', 'Sealed набори'];

function hl(text, q) {
  if (!q) return text;
  const i = text.toLowerCase().indexOf(q.toLowerCase());
  if (i < 0) return text;
  return (<>
    {text.slice(0, i)}
    <mark style={{ background: 'var(--bs-gold-soft)', color: 'inherit', borderRadius: 3, padding: '0 1px' }}>{text.slice(i, i + q.length)}</mark>
    {text.slice(i + q.length)}
  </>);
}

/* ---------- shared bits ------------------------------------------------- */
function Thumb({ small }) {
  const s = small ? 38 : 46;
  return (<div className="bs-img-ph" style={{ width: s, height: s, flex: `0 0 ${s}px`, borderRadius: 'var(--bs-r-sm)', fontSize: 0 }} />);
}

function ResultRow({ p, q, onPick }) {
  return (
    <button onClick={() => onPick(p)} style={{
      display: 'flex', alignItems: 'center', gap: 12, width: '100%',
      padding: '10px 16px', background: 'transparent', border: 0,
      textAlign: 'left', cursor: 'pointer', borderRadius: 0,
    }}
      onMouseDown={(e) => e.preventDefault()}>
      <Thumb />
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 13.5, fontWeight: 600, color: 'var(--bs-ink)', lineHeight: 1.35, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{hl(p.title, q)}</div>
        <div style={{ fontSize: 11.5, color: 'var(--bs-ink-3)', marginTop: 2 }}>{p.cat}</div>
      </div>
      <div style={{ fontSize: 13.5, fontWeight: 800, color: 'var(--bs-ink)', flex: '0 0 auto' }}>₴{p.price}</div>
    </button>
  );
}

/* The live-results body — reused by both variants. Shows recent + popular
   when empty, live matches when typing, empty-state when no match. */
function SearchResults({ q, onPick, onTerm }) {
  const query = q.trim();
  const matches = query
    ? CATALOG.filter(p => p.title.toLowerCase().includes(query.toLowerCase()) || p.cat.toLowerCase().includes(query.toLowerCase()))
    : [];

  if (!query) {
    return (
      <div style={{ padding: '6px 0 10px' }}>
        <SectionLabel>Нещодавні</SectionLabel>
        {RECENT.map(t => (
          <button key={t} onMouseDown={(e) => e.preventDefault()} onClick={() => onTerm(t)} style={termRow}>
            <Ic.Clock width="15" height="15" style={{ color: 'var(--bs-ink-4)', flex: '0 0 auto' }} />
            <span style={{ flex: 1 }}>{t}</span>
            <Ic.Arrow width="15" height="15" style={{ color: 'var(--bs-ink-4)', transform: 'rotate(-45deg)' }} />
          </button>
        ))}
        <SectionLabel style={{ marginTop: 6 }}>Популярне</SectionLabel>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, padding: '4px 16px 4px' }}>
          {POPULAR.map(t => (
            <button key={t} onMouseDown={(e) => e.preventDefault()} onClick={() => onTerm(t)} className="bs-chip" style={{ padding: '7px 12px', fontSize: 12.5, cursor: 'pointer' }}>
              <Ic.Trend width="13" height="13" style={{ color: 'var(--bs-ink-3)' }} />{t}
            </button>
          ))}
        </div>
      </div>
    );
  }

  if (matches.length === 0) {
    return (
      <div style={{ padding: '34px 24px', textAlign: 'center' }}>
        <div style={{ width: 44, height: 44, borderRadius: '50%', background: 'var(--bs-bg)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', color: 'var(--bs-ink-4)', marginBottom: 12 }}>
          <Ic.Search width="20" height="20" />
        </div>
        <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--bs-ink)' }}>Нічого не знайдено</div>
        <div style={{ fontSize: 12.5, color: 'var(--bs-ink-3)', marginTop: 4 }}>за запитом «{query}». Спробуйте іншу назву або виробника.</div>
      </div>
    );
  }

  return (
    <div style={{ paddingBottom: 6 }}>
      <SectionLabel>Товари · {matches.length}</SectionLabel>
      {matches.map(p => <ResultRow key={p.id} p={p} q={query} onPick={onPick} />)}
      <button onMouseDown={(e) => e.preventDefault()} onClick={() => onTerm(query)} style={{
        display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, width: '100%',
        padding: '12px 16px', marginTop: 4, background: 'transparent',
        border: 0, borderTop: '1px solid var(--bs-line-2)', cursor: 'pointer',
        fontSize: 13, fontWeight: 700, color: 'var(--bs-blue)',
      }}>
        Усі результати за «{query}» <Ic.Arrow width="15" height="15" />
      </button>
    </div>
  );
}

function SectionLabel({ children, style }) {
  return (<div style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: '.1em', textTransform: 'uppercase', color: 'var(--bs-ink-4)', padding: '12px 16px 6px', fontFamily: '"JetBrains Mono", ui-monospace, monospace', ...style }}>{children}</div>);
}
const termRow = {
  display: 'flex', alignItems: 'center', gap: 12, width: '100%',
  padding: '9px 16px', background: 'transparent', border: 0,
  textAlign: 'left', cursor: 'pointer', fontSize: 13.5, color: 'var(--bs-ink-2)', fontWeight: 500,
};

Object.assign(window, { Ic, CATALOG, SearchResults, ResultRow, Thumb, SectionLabel });
