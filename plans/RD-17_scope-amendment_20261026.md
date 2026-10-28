# RD-17 owner scope amendment - default action and profile names

Date: 2026-10-26 (Europe/Kyiv)
Executor: Codex, already assigned. Risky multi-file checkout/customer-data work;
retain high-depth execution and independent subscription Claude review.

## Direct owner decision in this chat

After G0 facts, the owner selected form option 1A (separate route), accepted the
existing NP-dependent order (item 4), requested a button that makes an address
default without opening edit (item 2), and reported that saved profile patronymic
does not prefill checkout (item 3). The owner explicitly requests diagnosis and
necessary implementation across relevant patronymic interactions so current
profile full name is used by checkout. Address-book patronymic display is not
required. This is authorization to prepare scoped functional changes, superseding
the original presentation-only exclusions only where necessary for these two
outcomes. Do not ask the owner to grant the same scope again.

## Approved outcomes and boundaries

1. Separate add/edit form route, existing NP dependency order and hidden stubs.
2. Direct default-address button on the customer's own address card. Build one
   authenticated, owner-scoped POST action using the existing data/transaction
   contracts; do not resubmit a stale complete address form, duplicate NP
   initialization, modify address text/JSON or create another address record.
   Preserve one default and customer/address ownership; idempotent repeat and
   selected-address/session/cache behavior must be tested. This authorizes a
   future customer's explicit UI action, not an agent DB write or data migration.
3. Current profile first name, last name and optional patronymic seed checkout
   coherently. Read the real mapped profile field, never a guessed field ID or
   translated label. Trace session freshness, guest/auth transitions, receiver
   override, explicit empty value, saved-address selection, order/shipping
   snapshots, admin/NP consumers and refresh/revisit behavior. Preserve existing
   explicit alternate-recipient functionality unless current evidence requires
   a clearly reported owner decision. Never attach the account holder's middle
   name to another recipient or silently overwrite an explicit user edit.
4. Profile patronymic and order recipient patronymic remain distinct storage
   concepts; a default value may bridge them without making order edits rewrite
   the profile or address book. Keep existing ACC-009 name rules, optional clear,
   ACC-008 JSON preservation/ownership/transaction safety and C1 readiness/races.
   No historical orders are rewritten. No Telegram/Viber expansion or new contact
   fields in registration/checkout.

## Evidence and execution sequence

The accepted RD-17 WP0 pull proves its 27 named files at
2026-10-26T10:51:48Z to 10:51:49Z. Its G0 findings remain history. Items 1 and 4
are decided; previous recommendations for items 2/3 are superseded above.
The accepted ACC-009 closeout explicitly kept profile patronymic separate from
recipient patronymic. Retained N2 checkout source has profile fallbacks for
first/last name but only session override for middlename. This is a causal lead,
not current-host diagnosis or proof of the owner's private test account.

Collect a bounded fresh checkout/NP source supplement and hash-only identities
for prior account sources/order consumers. No credentials, customer/order rows,
sessions, dumps, logs or provider calls. Raw secret-bearing order/notification
sources remain undisclosed; request only their hashes and reuse accepted scoped
contracts where identities allow. Any necessary additional source disclosure is
a separate bounded need, never automatic permission to print private literals.

Prepare the dependency/acceptance map before patches. Keep default action,
checkout/profile bridge and RD-17 visuals separately reviewable/deployable when
feasible; decide final packaging from the actual interactions, not invented
independence. Do not start a separate early design model review by default.
Integrated independent review is required for each coherent executable package;
owner-only deployment/QA remain separate. Do not replay historical ACC-009
artifacts or merely replace one admission hash.

RD-18 waits until RD-17 and the owner-assigned prerequisite changes have passed
deployment and owner QA. One writer for shared CSS/header/shell. H1 and D2 remain
as recorded. No Notion/status/Git/paid-fallback/agent SSH/key/deploy authority.
No production DB write, schema change, backfill, payment/fiscal/CRM-notification
semantics or real test order/shipment/message is authorized by this amendment.
Preserve old snapshots and existing payment/credit/readiness behavior.

## Receiver precedence and button - final owner answer 2026-10-26

Owner selected option 1: new/default checkout uses current profile full name;
already entered receiver details stay authoritative for that checkout. The exact
button label is `Підставити дані з профілю`.

The button copies profile first name/surname/patronymic into the current order's
existing receiver controls through the existing single autosave/coordinator.
It does not change profile/address-book data or the telephone. Hide it when:
- the profile has no nonempty stored patronymic (including the explicit case
  where checkout has a manually entered patronymic and profile has none);
- all three name values already match the profile;
- name entry has not settled or a receiver update/payment coordination is active.

Show it after editing is finished when an eligible profile name tuple differs.
Re-evaluate from existing input/blur/save/repaint flow without a full page reload.
Use authoritative server profile names on deliberate copy, not trusted submitted
profile data; an empty profile patronymic must not erase the receiver value.
Retain explicit empty receiver patronymic and legacy receiver tuples on revisit.
No further behavior question is open for this feature.

## Exact owner statement: name display on address cards

For RD17-WP1B review evidence, the owner's original direct instruction in this
same task is preserved verbatim:

> Так, ми у вкладці адреси можемо і надалі не відображати ПІБ, але це стосується саме адрес у кабінеті, а не в чекауті.

The permission covers omitting full recipient names on account address cards,
not only patronymic. The earlier summary's narrower patronymic-only wording was
incomplete. This records existing owner authority; it does not introduce a new
decision or waive the separate checkout profile-name requirement. RD17-WP1B
therefore displays stored location/delivery details on its cards and preserves
the existing first/last-name controls on the separate add/edit form.
