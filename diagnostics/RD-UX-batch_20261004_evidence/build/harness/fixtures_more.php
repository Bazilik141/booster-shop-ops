<?php
declare(strict_types=1);

function fx_products(int $n, string $q = ''): array {
    $names = [
        ['Бустер Pokémon TCG: Mega Evolution — Chaos Rising (Англійське видання)', '330.00₴', null],
        ['Pokémon TCG: Scarlet & Violet — Prismatic Evolutions Elite Trainer Box (Англійське видання)', '3900.00₴', '3450.00₴'],
        ['Містері бокс Pokémon TCG: Mystery Mix Standard (Японське видання)', '750.00₴', null],
        ['Містері бокс Pokémon TCG: Mystery Mix XL (Японське видання)', '1100.00₴', null],
        ['Бустер бокс Pokémon TCG: Surging Sparks Booster Display (36 бустерів)', '6900.00₴', null],
        ['Pokémon TCG: Scarlet & Violet 151 Ultra-Premium Collection — Mew', '11800.00₴', null],
        ['One Piece Card Game OP-10 Royal Blood Booster Box (24 бустери, японською мовою)', '3200.00₴', null],
        ['Набір Pokémon TCG: Pikachu ex Premium Collection', '1450.00₴', '1290.00₴'],
    ];
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $p = $names[$i % count($names)];
        $out[] = tpl('product/thumb', ['product_id' => 100 + $i, 'thumb' => 'image/fixture/p' . (($i % 8) + 1) . '.svg', 'name' => $p[0], 'href' => '#p' . $i,
            'price' => $p[1], 'special' => $p[2], 'price_value' => (float)$p[1], 'special_value' => $p[2] ? (float)$p[2] : 0, 'minimum' => 1, 'cart_add' => '#', 'cart' => '#', 'button_cart' => 'Купити',
            'bs_state' => $i === 3 ? 'preorder' : '', 'bs_eta' => $i === 3 ? 'Очікується 20.10' : '']);
    }
    return $out;
}

function fx_cart_products(): array {
    return [
        ['cart_id' => 1, 'href' => '#', 'thumb' => 'image/fixture/p1.svg', 'name' => 'Бустер Pokémon TCG: Mega Evolution — Chaos Rising', 'quantity' => 2, 'total' => '660.00₴'],
        ['cart_id' => 2, 'href' => '#', 'thumb' => 'image/fixture/p3.svg', 'name' => 'Містері бокс Pokémon TCG: Mystery Mix Standard', 'quantity' => 1, 'total' => '750.00₴'],
    ];
}

function render_page_more(string $page): string {
    $q = (string)($_GET['search'] ?? 'pokemon');
    $sorts = [];
    foreach ([['p.sort_order-ASC', 'За замовчуванням'], ['pd.name-ASC', 'Назва (А - Я)'], ['p.price-ASC', 'Ціна (найнижча)'], ['p.price-DESC', 'Ціна (найвища)']] as $s) {
        $sorts[] = ['value' => $s[0], 'text' => $s[1], 'href' => 'index.php?route=' . ($page === 'search' ? 'product/search&search=' . urlencode($q) : 'product/category&path=59') . '&sort=' . explode('-', $s[0])[0] . '&order=' . explode('-', $s[0])[1]];
    }
    switch ($page) {
        case 'search':
            $empty = ($q === 'zzz' || $q === '');
            $limits = [];
            foreach ([15, 25, 50, 75, 100] as $l) $limits[] = ['value' => $l, 'text' => (string)$l, 'href' => 'index.php?route=product/search&search=' . urlencode($q) . '&limit=' . $l];
            $data = ['breadcrumbs' => crumbs(['Пошук']), 'heading_title' => 'Пошук - ' . $q, 'entry_search' => 'Пошук:', 'text_keyword' => 'Ключові слова',
                'entry_description' => 'Шукати в описі товарів', 'text_category' => 'Всі категорії', 'text_sub_category' => 'Пошук у підкатегоріях', 'button_search' => 'Пошук',
                'text_search' => 'Результати пошуку', 'compare' => '#compare', 'text_compare' => 'Порівняння товарів (0)', 'button_list' => 'Список', 'button_grid' => 'Сітка',
                'text_sort' => 'Сортування:', 'sorts' => $sorts, 'text_limit' => 'Показати:', 'limits' => $limits, 'sort' => $_GET['sort'] ?? 'p.sort_order', 'order' => $_GET['order'] ?? 'ASC',
                'limit' => (int)($_GET['limit'] ?? 15), 'text_no_results' => 'Немає товарів, які відповідають критеріям пошуку.', 'language' => 'uk-ua', 'search' => $q,
                'description' => !empty($_GET['description']), 'category_id' => (int)($_GET['category_id'] ?? 0), 'sub_category' => !empty($_GET['sub_category']),
                'categories' => [['category_id' => 59, 'name' => 'Pokémon', 'children' => [['category_id' => 60, 'name' => 'Бустери Pokémon', 'children' => []], ['category_id' => 61, 'name' => 'Бустер бокси Pokémon', 'children' => []]]], ['category_id' => 70, 'name' => 'One Piece', 'children' => []]],
                'products' => $empty ? [] : fx_products(6, $q), 'pagination' => '', 'results' => $empty ? '' : 'Показано з 1 по 6 із 6 (1 сторінок)',
                'column_left' => '', 'column_right' => '', 'content_top' => '', 'content_bottom' => ''];
            return header_html(['title' => 'Пошук - ' . $q, 'route' => 'product/search', 'robots' => 'noindex,follow']) . tpl('product/search', $data) . footer_html();
        case 'category':
            $checked = isset($_GET['filter']) && $_GET['filter'] !== '' ? explode(',', (string)$_GET['filter']) : [];
            $groups = [
                ['filter_group_id' => 1, 'name' => 'Тип товару', 'filter' => [['filter_id' => 11, 'name' => 'Блістер бокс'], ['filter_id' => 12, 'name' => 'Бустер'], ['filter_id' => 13, 'name' => 'Бустер бокс'], ['filter_id' => 14, 'name' => 'Набори'], ['filter_id' => 15, 'name' => 'Аксесуар']]],
                ['filter_group_id' => 2, 'name' => 'Країна', 'filter' => [['filter_id' => 21, 'name' => 'Корея'], ['filter_id' => 22, 'name' => 'США/Європа'], ['filter_id' => 23, 'name' => 'Україна'], ['filter_id' => 24, 'name' => 'Японія']]],
            ];
            $nofilter = !empty($_GET['nofilter']);
            $module = $nofilter ? '' : ext_tpl('extension/opencart/catalog/view/template/module/filter.twig', ['heading_title' => 'Фільтр', 'button_filter' => 'Пошук', 'filter_groups' => $groups,
                'filter_category' => $checked, 'action' => 'http://' . $_SERVER['HTTP_HOST'] . '/index.php?route=product/category&language=uk-ua&path=59']);
            $colRight = tpl('common/column_right', ['modules' => $module ? [$module] : []]);
            $active = [];
            $names = [11 => 'Блістер бокс', 12 => 'Бустер', 13 => 'Бустер бокс', 14 => 'Набори', 15 => 'Аксесуар', 21 => 'Корея', 22 => 'США/Європа', 23 => 'Україна', 24 => 'Японія'];
            foreach ($checked as $id) if (isset($names[(int)$id])) $active[] = ['label' => $names[(int)$id], 'remove_url' => 'index.php?route=product/category&path=59&filter=' . implode(',', array_diff($checked, [$id]))];
            $nosubs = !empty($_GET['nosubs']);
            $subs = $nosubs ? [] : [['name' => 'Бустери', 'href' => '#s1', 'product_count' => 15, 'active' => false], ['name' => 'Бустер бокси', 'href' => '#s2', 'product_count' => 13, 'active' => false], ['name' => 'Набори', 'href' => '#s3', 'product_count' => 13, 'active' => false], ['name' => 'Фігурки та декор', 'href' => '#s4', 'product_count' => 21, 'active' => false]];
            $data = ['breadcrumbs' => crumbs(['Pokémon']), 'heading_title' => 'Pokémon', 'category_code' => 'pokemon', 'category_is_subcategory' => false, 'category_heading_short' => '',
                'product_total' => 48, 'products_total_label' => 'товарів', 'sub_categories' => $subs, 'sorts' => $sorts, 'current_sort' => 'p.sort_order-ASC', 'text_sort' => 'Сортування',
                'active_filters' => $active, 'reset_url' => 'index.php?route=product/category&path=59', 'column_right' => $colRight, 'column_left' => '', 'content_top' => '', 'content_bottom' => '',
                'products' => fx_products(8), 'categories' => [], 'pagination' => '', 'results' => 'Показано з 1 по 8 із 48 (6 сторінок)', 'description' => '<p>Опис категорії Pokémon для SEO.</p>',
                'text_no_results' => 'Немає товарів', 'continue' => '/', 'button_continue' => 'Продовжити'];
            $links = [['href' => 'http://' . $_SERVER['HTTP_HOST'] . '/index.php?route=product/category&path=59&filter=' . urlencode((string)($_GET['filter'] ?? '')), 'rel' => 'canonical']];
            return header_html(['title' => 'Pokémon', 'route' => 'product/category', 'links' => $links]) . tpl('product/category', $data) . footer_html();
        case 'info':
            $desc = '<p>Вступ до сторінки оплати і доставки.</p>';
            foreach (['Оплата', 'Доставка Новою поштою', 'Повернення', 'Контакти'] as $h) $desc .= '<h2>' . $h . '</h2>' . str_repeat('<p>Текст розділу «' . $h . '». Достатньо довгий абзац, щоб сторінка прокручувалась і можна було перевірити якорі змісту під липкою шапкою.</p>', 6);
            $data = ['breadcrumbs' => crumbs(['Оплата і доставка']), 'heading_title' => 'Оплата і доставка', 'description' => $desc, 'column_left' => '', 'column_right' => '', 'content_top' => '', 'content_bottom' => '', 'continue' => '/'];
            return header_html(['title' => 'Оплата і доставка', 'route' => 'information/information']) . tpl('information/information', $data) . footer_html();
        case 'stack':
            // Product-like page: the live product.twig sticky buy bar (its own inline <style>, markup) + a filled mini-cart.
            $pt = file_get_contents($GLOBALS['W'] . '/catalog/view/template/product/product.twig');
            $a = strpos($pt, '{# ===== R-04: Sticky Add-to-Cart');
            $styleA = strpos($pt, '<style>', $a); $styleB = strpos($pt, '</style>', $styleA) + 8;
            $style = substr($pt, $styleA, $styleB - $styleA);
            $bar = '<div class="bs-sticky-atc" data-product-id="1"><div class="bs-sticky-atc__inner"><div class="bs-qty"><button type="button">−</button><input class="bs-qty__input" value="1"><button type="button">+</button></div>'
                . '<button type="button" class="bs-btn bs-btn-primary bs-sticky-atc__cta" data-sticky-add-to-cart>Купити · 330₴</button></div></div>';
            $body = '<div id="product-product" class="container"><ul class="breadcrumb"><li class="breadcrumb-item"><a href="/">Головна</a></li><li class="breadcrumb-item"><a href="#">Товар</a></li></ul>'
                . '<div id="content"><h1>Товар (fixture)</h1>' . str_repeat('<p>Опис товару. Достатньо тексту для прокрутки сторінки під липкою шапкою.</p>', 40)
                . '<ul class="nav nav-tabs"><li class="nav-item"><a class="nav-link" id="bs-tab-review-link" href="#tab-review">Відгуки</a></li></ul><div id="tab-review">Відгуки</div></div></div>'
                . $style . $bar . '<script>document.body.classList.add("bs-sticky-atc-visible");</script>';
            return header_html(['title' => 'Товар', 'route' => 'product/product', 'cart_products' => fx_cart_products(), 'cart_qty' => 3, 'cart_text' => '3 товар(ів) - 1410.00₴',
                'cart_totals' => [['code' => 'sub_total', 'text' => '1410.00₴']]]) . $body . footer_html();
        case 'checkout':
            $body = '<div id="checkout-checkout" class="container bs-co"><ul class="breadcrumb"><li class="breadcrumb-item"><a href="/">Головна</a></li><li class="breadcrumb-item"><a href="#">Оформлення</a></li></ul>'
                . '<div class="bs-co-grid"><div class="bs-co-col"><div class="bs-co-card" style="padding:16px;background:#fff">' . str_repeat('<p><label>Поле</label><input class="form-control"></p>', 30) . '</div></div>'
                . '<aside class="bs-co-aside"><div class="bs-co-card" style="padding:16px;background:#fff"><h3>Ваше замовлення</h3><p>Сума: 1410₴</p><button class="btn btn-primary">Підтвердити замовлення</button></div></aside></div></div>';
            return header_html(['title' => 'Оформлення', 'route' => 'checkout/checkout']) . $body . footer_html();
    }
    return 'unknown page';
}
