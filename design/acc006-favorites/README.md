# ACC-006 visual step — «Обране» boards

Copied 2026-10-28 from the Claude Design canvas «ACC-006 · Улюблене»
https://claude.ai/artifact/DdqHSf3Gy7gfTdrEaL5TPA (version 1793190235-0f25).

## Owner decisions (2026-10-28)
- Catalog tile: variant **A**, desktop and mobile: a round heart at the bottom-right of the image slot.
- Product page: variant **A** on desktop (a square heart after «У кошик»), variant **C** on mobile (a round heart on the gallery photo).
- Name: **«Обране»**.
- «Обране» account page: the heart sits at the bottom-right of the photo, the same as the tile.
- Boards marked «обрано» show the chosen variant. The other variants stay on the boards for history only.

| File | Board |
|---|---|
| `Main.dc.html` | Catalog tile, 3 variants, desktop (A chosen) |
| `Card-Mobile.dc.html` | Catalog tile, 3 variants, mobile (A chosen) |
| `Product.dc.html` | Product page purchase column, 3 variants, desktop (A chosen) |
| `Product-Mobile.dc.html` | Product page, 3 variants, mobile (C chosen) |
| `Favorites.dc.html` | Account «Обране», desktop |
| `Favorites-Mobile.dc.html` | Account «Обране», mobile |
| `Naming.dc.html` | Name options («Обране» chosen) and notices |

## How to read them
- Read them as markup specs inside `<x-dc>`. Inline styles are mock code. Map values to `boostershop-ds.css` tokens and use `bs-*` classes; do not copy the markup.
- Photos are placeholders. Product names and prices are live examples, not content.
- Heart color is a tweak prop. The default, red `#DC2626`, is the current decision.
- The account sidebar on `Favorites.dc.html` is simplified. The live RD-16 shell is the source.
