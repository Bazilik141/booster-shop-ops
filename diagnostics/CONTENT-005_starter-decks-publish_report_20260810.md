# CONTENT-005 — starter-decks publication patch report

Date: 2026-08-10
Task: CONTENT-005
Executor: Codex
Status: patch prepared locally; production execution was not authorized or performed.

The owner reassigned this task from Claude Code to Codex on 2026-08-10.

## Scope

The runner creates five preorder products: OP-JP-ST32-STD through OP-JP-ST36-STD.
It creates exactly one new attribute, Кількість карток у колоді. It creates no other
attribute and does not modify an existing product, category, layout, manufacturer, or attribute definition.

## Discovery evidence

- Latest cPanel source backup: backup-8.5.2026_10-49-27_boosters.tar.gz.
- The deployed bs-faq.js byte-matched that backup:
  SHA-256 467ef0a45507373733217bc92c84b9c49afde0124f72db622aaf3c8edc8fc44e.
- Fresh live DB snapshot: CONTENT-005_op16_live_20260810_155527.tar.gz,
  generated 2026-08-10T12:55:28Z.
- DB prefix is ocp5_. The OP-16 template is product 107, language 4, store 0,
  category ids 60 and 68, no layout rows, manufacturer Bandai id 12,
  and preorder stock status id 8.
- Fresh live checks found no SKU, SEO keyword, or prod-st32 through prod-st36 FAQ-id collisions.
- Attribute display uses OpenCart sort order, accepted by the owner:
  Назва сету, Мова, Тип пакування, Додатковий вміст,
  Кількість карток у колоді, Стан, Виробник, Рік випуску.

## Patch safeguards

- Explicit --dry-run or --apply mode; no default write.
- Live OP-16, manufacturer, preorder, category, store, layout, SEO, attribute,
  SKU, keyword and FAQ-id anchors are rechecked before a write.
- The content payload was extracted from the final plan and is SHA-256 checked:
  ccebeb5deed546b59a653a38eb2ac5a4c7c7a22d489042cd5b0f2271648e8a89.
  The descriptions, FAQ markup, ARIA attributes, and existing button/panel IDs
  are stored without editing.
- A prestate JSON file is written under _patch_backups before the transaction.
- One transaction covers all five products. A failure rolls back every new row.
- The product insert now uses an explicit safe field set rather than copying every
  OP-16 column. Identifiers and location are blank; points, viewed and rating are
  zero; dates use the patch-run time; and, by owner decision, weight and all
  dimensions are zero. Unknown required future columns abort the patch.
- Before commit it verifies 5 products, 5 descriptions, 40 attributes,
  10 related links and 5 SEO rows.
- A complete and internally verified existing SKU set returns already_applied=yes
  and writes nothing; a partial or incomplete set aborts.
- Successful apply prints five product IDs, five URLs, rollback IDs and the new
  attribute ID, then self-deletes.

## Local verification

Passed before this review round: PHP lint and direct byte-for-byte payload comparison against the final plan.
Payload SHA-256: ccebeb5deed546b59a653a38eb2ac5a4c7c7a22d489042cd5b0f2271648e8a89.
The payload contains five descriptions, eight attribute rows per product, and the
unchanged FAQ ids prod-st32, prod-st33, prod-st34, prod-st35 and prod-st36.
No production database action, cache clear, sitemap regeneration, image upload,
Notion update, dashboard update, commit or push was performed.

## Rollback

Complete ordered SQL is in the patch header. It uses the product IDs and new
attribute ID printed by a successful apply. The new attribute is removed only
when new_attribute_created=yes.

## Production gate

Before --apply the owner must take a fresh cPanel MySQL database backup. The
owner must separately authorize the production run. Run the cache clear in the
same terminal after a successful apply. After it succeeds, add the emitted
product IDs to this report before declaring deployment or QA complete.

~~~bash
cd ~/public_html || exit
php CONTENT-005_starter-decks-publish_20260810.php --apply && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
~~~

## Claude-review follow-up

- D1 addressed by owner decision: product fields are now explicitly controlled.
  EAN, JAN, ISBN, UPC, MPN and location are blank; viewed and rating are zero;
  dates are current at patch execution; weight, length, width and height are zero.
  Any unhandled required future product column aborts before writing.
- F1 addressed in the production command above. Cache clear remains outside the patch.
- F2 addressed: self-lint runs when exec is available; otherwise the already parsed
  PHP file continues and reports the skipped self-lint.
- F3 addressed: an already-applied --apply run self-deletes; dry-run deliberately does not.
- F4 is not a defect: the owner reassigned the executor to Codex.
- F5 remains a production QA constraint: upload product photos before running
  Merchant/schema validation or relying on the Merchant feed.
- F6 is accepted: actual OpenCart sort order governs the attribute display.

Local post-review verification passed: PHP syntax, plan payload SHA-256,
FAQ ids, absence of the unbounded clone loop, zero physical-field values and
the exec-unavailable fallback.

## Host-compatibility follow-up

The first production dry-run did not write to the database. It stopped at
mysqli_stmt::get_result(), because this host does not include mysqlnd. The
runner now uses result_metadata plus bind_result, which works with the host
mysqli driver without mysqlnd.
