// Demo: feeds three different "wild" HTML inputs into the normalizer and
// renders the same accordion (Variant A by default) on the output side.
// This is what proves the unification claim to the user — same component,
// three messy inputs, one clean result.

const { useMemo: useMemoDemo, useState: useStateDemo, useRef: useRefDemo, useEffect: useEffectDemo } = React;

function useParsedFaq(html) {
  return useMemoDemo(() => {
    const root = document.createElement('div');
    root.innerHTML = html;
    return window.parseFaq(root);
  }, [html]);
}

// A small accordion used only for the demo column (compact spacing so 3 of
// them fit side-by-side on the artboard). Mirrors Variant A's structure.
function DemoAccordion({ items }) {
  const [open, toggle] = window.useOpenSetV2([0]);
  return (
    <div style={{ borderTop: '1px solid var(--bs-line)' }}>
      {items.map((it, i) => {
        const isOpen = open.has(i);
        const hasAnswer = it.a && it.a.replace(/<[^>]+>/g, '').trim();
        return (
          <div key={i} style={{ borderBottom: '1px solid var(--bs-line)' }}>
            <button
              onClick={() => toggle(i)}
              style={{
                width: '100%', background: 'transparent', border: 0,
                padding: '12px 2px', cursor: 'pointer',
                display: 'flex', alignItems: 'center', gap: 10,
                textAlign: 'left', font: 'inherit', color: 'var(--bs-ink)',
              }}
            >
              <span style={{
                flex: 1, fontSize: 13, fontWeight: 600, lineHeight: 1.35,
              }}>{it.q}</span>
              <span style={{
                width: 18, height: 18,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                color: isOpen ? 'var(--bs-gold)' : 'var(--bs-ink-3)',
                transform: `rotate(${isOpen ? 180 : 0}deg)`,
                transition: 'transform .28s cubic-bezier(.2,.7,.2,1), color .2s',
              }}>
                <svg width="11" height="11" viewBox="0 0 14 14" fill="none">
                  <path d="M3 5l4 4 4-4" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"/>
                </svg>
              </span>
            </button>
            <window.CollapseV2 open={isOpen}>
              <div style={{
                padding: '0 28px 14px 2px',
                fontSize: 12.5, lineHeight: 1.6, color: 'var(--bs-ink-2)',
              }}>
                {hasAnswer
                  ? <span dangerouslySetInnerHTML={{ __html: it.a }} />
                  : <span style={{ color: 'var(--bs-ink-3)', fontStyle: 'italic' }}>
                      Відповідь у підготовці.
                    </span>}
              </div>
            </window.CollapseV2>
          </div>
        );
      })}
    </div>
  );
}

function CodeBlock({ html }) {
  return (
    <pre style={{
      margin: 0, padding: 14,
      background: '#0f172a', color: '#cbd5e1',
      fontSize: 11, lineHeight: 1.55,
      fontFamily: "'JetBrains Mono', ui-monospace, monospace",
      borderRadius: 8,
      maxHeight: 280, overflow: 'auto',
      whiteSpace: 'pre-wrap', wordBreak: 'break-word',
    }}>
      {html}
    </pre>
  );
}

function NormalizerDemoColumn({ label, sublabel, sourceHtml, variant }) {
  const items = useParsedFaq(sourceHtml);
  return (
    <div style={{
      display: 'flex', flexDirection: 'column', gap: 10,
      minWidth: 0,
    }}>
      <div style={{
        fontFamily: "'JetBrains Mono', ui-monospace, monospace",
        fontSize: 10.5, fontWeight: 600, letterSpacing: '.12em',
        color: 'var(--bs-ink-3)', textTransform: 'uppercase',
      }}>
        {label}
        {sublabel && (
          <span style={{ color: 'var(--bs-ink-4)', marginLeft: 8, fontWeight: 400 }}>
            · {sublabel}
          </span>
        )}
      </div>
      <CodeBlock html={sourceHtml} />
      <div style={{
        display: 'flex', alignItems: 'center', gap: 8,
        fontFamily: "'JetBrains Mono', ui-monospace, monospace",
        fontSize: 10, color: 'var(--bs-ink-4)',
        letterSpacing: '.1em', textTransform: 'uppercase',
      }}>
        <svg width="12" height="12" viewBox="0 0 14 14" fill="none">
          <path d="M3 7h8M7 3l4 4-4 4" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
        parseFaq() → {items.length} item{items.length === 1 ? '' : 's'}
      </div>
      <div style={{
        background: '#fff',
        border: '1px solid var(--bs-line)',
        borderRadius: 8,
        padding: '12px 14px',
      }}>
        {items.length
          ? <DemoAccordion items={items} />
          : <div style={{ color: 'var(--bs-ink-3)', fontSize: 12, fontStyle: 'italic' }}>
              No FAQ heading detected.
            </div>}
      </div>
    </div>
  );
}

function NormalizerDemo() {
  return (
    <div style={{
      display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 24,
      padding: '4px 0',
    }}>
      <NormalizerDemoColumn
        label="Вхід 1"
        sublabel="h4 + p — як хоче GPT"
        sourceHtml={window.WILD_FAQ_SAMPLES.h4}
      />
      <NormalizerDemoColumn
        label="Вхід 2"
        sublabel="strong + p — OnePiece OP-15"
        sourceHtml={window.WILD_FAQ_SAMPLES.strong}
      />
      <NormalizerDemoColumn
        label="Вхід 3"
        sublabel="h4 без відповідей — Mega Dream EX"
        sourceHtml={window.WILD_FAQ_SAMPLES.headingsOnly}
      />
    </div>
  );
}

Object.assign(window, { NormalizerDemo });
