// Shared content for the FAQ v2 explorations. The four design variants all
// render the same questions so the visual difference is the only variable.
// The text is realistic for a Booster Shop product description: short, neutral,
// in line with the rest of the site copy.

const FAQ_V2_ITEMS = [
  {
    q: 'Що таке Black & White Rare (BWR)?',
    a: 'BWR — нова рідкість сетів Black Bolt і White Flare: монохромні фойл-карти, парні між двома сетами. Reshiram BWR у White Flare має пряму пару Zekrom BWR у Black Bolt.',
  },
  {
    q: 'Чим White Flare відрізняється від Black Bolt?',
    a: 'Це парні японські сети червня 2025 року. White Flare побудований навколо Reshiram і світлої палітри регіону Уново; Black Bolt — навколо Zekrom і темної. Карти BWR одного сету мають дзеркальні пари в іншому.',
  },
  {
    q: 'Hilda — це персонаж з якої гри?',
    a: 'Hilda — головна героїня Pokémon Black/White (Gen V). У White Flare вона з’являється як Special Illustration Rare — перша поява жіночого ігрового персонажа Gen V на карті TCG.',
  },
  {
    q: 'Бустери зважувались перед продажем?',
    a: 'Ні. Усі бустери продаються без зважування — у тому стані, у якому ми отримали їх з оригінального боксу від постачальника.',
  },
];

// Three realistic "wild" HTML snippets that an LLM might write into a
// product description. The normalizer must handle all three and produce the
// same accordion output. Kept short on purpose — long enough to show that the
// parser handles the structural variation, not the prose.
const WILD_FAQ_SAMPLES = {
  // Format A — clean: H4 question + paragraph answer. Common output of GPT/Codex.
  h4: `
<h3>FAQ</h3>
<h4>Що таке Black &amp; White Rare (BWR)?</h4>
<p>BWR — нова рідкість сетів Black Bolt і White Flare: монохромні фойл-карти, парні між двома сетами.</p>
<h4>Чим White Flare відрізняється від Black Bolt?</h4>
<p>Парні японські сети червня 2025 року. White Flare — Reshiram і світла палітра, Black Bolt — Zekrom і темна.</p>
<h4>Бустери зважувались?</h4>
<p>Ні. Усі бустери продаються без зважування.</p>
`.trim(),

  // Format B — wrong but realistic: bold + paragraph pairs, no headings.
  // This is exactly what broke on the OnePiece OP-15 product page.
  strong: `
<h3>FAQ — про цей бокс</h3>
<p><strong>Чи є заводська стрічка на боксі?</strong></p>
<p>Так. Бокс продається sealed із заводською стрічкою — у тому стані, в якому прийшов від постачальника.</p>
<p><strong>Скільки карт у боксі загалом?</strong></p>
<p>У боксі 24 бустери. Орієнтовно 144 карти на бокс.</p>
<p><strong>Що таке Manga Rare у One Piece TCG?</strong></p>
<p>Manga Rare (SEC-SP) — найрідкісніший рівень карт сету, у стилі оригінальних манга-панелей.</p>
`.trim(),

  // Format C — headings without answers (broke on Mega Dream EX). The parser
  // should still render them as a clean list and mark them as "answer pending"
  // rather than rendering a half-broken table-with-question-marks.
  headingsOnly: `
<h3>FAQ</h3>
<h4>Що таке High Class Pack?</h4>
<h4>Які шанси витягнути SAR або MUR?</h4>
<h4>Бустери з box чи з розсипу?</h4>
<h4>Бустери зважувались перед продажем?</h4>
`.trim(),
};

Object.assign(window, { FAQ_V2_ITEMS, WILD_FAQ_SAMPLES });
