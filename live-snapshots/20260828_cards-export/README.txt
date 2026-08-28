bs-cards-export — read-only snapshot of Booster Shop card and category text
Generated: 2026-08-28T08:47:40+00:00
Products: 94 (flagged: 94)
Categories: 15
Language id: 4

products/   one file per card, 3D first, then by flag count
categories/ one file per category
index.tsv   one row per item, opens in Excel
problem-cards.txt  short list of everything that tripped a flag
raw.json    full machine-readable dump

Flags — mechanical pointers, not verdicts. A human decides.
  KEY_NOT_IN_BODY      head phrase of the Meta Title is absent from the visible description
  NAME_VS_TITLE        product name and Meta Title carry different head phrases
  NO_HEADING           description has no h2/h3 of its own
  NO_EMPHASIS          description has no strong/b
  THIN_BODY            visible description under 400 characters
  NO_FAQ               no FAQ accordion in the description
  MD_TOO_LONG          Meta Description over 155 characters
  MD_TOO_SHORT         Meta Description under 80 characters
  MT_TOO_LONG          Meta Title over 60 characters
  NO_META_TITLE        Meta Title empty
  NO_SEO_URL           no seo_url row for this product
  PLACEHOLDER          text still says «уточнюємо» / TBD / {{
  INTERNAL_VOCAB       production words in customer copy (партія, п'ятірка, SKU, артикул)
  SUPERLATIVE          най-/єдиний — check what the claim is scoped to
  BATCH_SCOPED         superlative tied to the current range; false as soon as the range grows
  ADDRESSES_TY         addresses the reader as «ти» while the shop uses «ви» or impersonal
  VOICE_MIXED          «ми друкуємо» and «друкується» in the same card
  REFS_OFFLINE_PRODUCT names a product whose own page is not visible
  EMPTY_BODY           no description at all

Notes are not text problems: NO_IMAGE, HIDDEN.

Nothing on the site was written. Every statement is a SELECT.
