<?php
declare(strict_types=1);

function success_state(string $st): array {
    $orders = [
        'a' => ['id' => 10482, 'logged' => false, 'first15' => false, 'pay' => 'cod', 'ship' => 'Нова Пошта — у відділення', 'shipText' => 'Київ, Відділення №27', 'payMethod' => 'Оплата при отриманні (накладений платіж)',
            'items' => [[1, 'Pokémon TCG: Scarlet & Violet — Prismatic Evolutions Elite Trainer Box', '3450.00₴'], [2, 'One Piece Card Game OP-09 Booster Pack', '420.00₴']],
            'totals' => [['Сума', '3870.00₴'], ['Нова Пошта — у відділення', '0.00₴'], ['Всього', '3870.00₴']]],
        'b' => ['id' => 10483, 'logged' => true, 'first15' => true, 'pay' => 'hutko', 'ship' => 'Нова Пошта — кур’єр', 'shipText' => 'Львів, вул. Городоцька, 15', 'payMethod' => 'Оплата карткою онлайн (Hutko)',
            'items' => [[1, 'Pokémon TCG: Surging Sparks Booster Display (36 бустерів)', '6900.00₴']],
            'totals' => [['Сума', '6900.00₴'], ['Нова Пошта — кур’єр', '0.00₴'], ['Всього', '6900.00₴']]],
        'c' => ['id' => 10484, 'logged' => false, 'first15' => false, 'pay' => 'iban', 'ship' => 'Нова Пошта — у поштомат', 'shipText' => 'Одеса, Поштомат №4410', 'payMethod' => 'Оплата на рахунок (IBAN)',
            'items' => [[1, 'Disney Lorcana: Azurite Sea Booster Box', '5600.00₴'], [3, 'Ultra Pro Deck Protector Sleeves — Matte Black (100)', '1050.00₴']],
            'totals' => [['Сума', '6650.00₴'], ['Нова Пошта — у поштомат', '0.00₴'], ['Всього', '6650.00₴']]],
        'd' => ['id' => 10485, 'logged' => true, 'first15' => false, 'pay' => 'cod', 'ship' => 'Нова Пошта — у відділення', 'shipText' => 'Харків, Відділення №112 (до 30 кг на одне місце)', 'payMethod' => 'Оплата при отриманні (накладений платіж)',
            'items' => [
                [1, 'Pokémon TCG: Scarlet & Violet — Prismatic Evolutions Super-Premium Collection (англійською мовою, запечатаний)', '4990.00₴'],
                [2, 'Pokémon TCG: Scarlet & Violet 151 Ultra-Premium Collection — Mew (англійською мовою)', '11800.00₴'],
                [1, 'One Piece Card Game OP-10 Royal Blood Booster Box (24 бустери, японською мовою)', '3200.00₴'],
                [4, 'Magic: The Gathering — Duskmourn: House of Horror Play Booster (англійською мовою)', '1040.00₴'],
                [1, 'Ultra Pro Premium PRO-Binder 12-Pocket Zippered — Pokémon Charizard, Pikachu, Eevee', '1450.00₴'],
                [2, 'Dragon Shield Matte Sleeves Standard Size 100 шт. — Jet Black / Crimson / Petrol', '980.00₴']],
            'totals' => [['Сума', '23460.00₴'], ['Нова Пошта — у відділення', '0.00₴'], ['Всього', '23460.00₴']]],
    ];
    return $orders[$st] ?? $orders['a'];
}

function render_page(string $page): string {
    global $logged;
    switch ($page) {
        case 'success':
            $st = (string)($_GET['st'] ?? 'a');
            $data = ['breadcrumbs' => crumbs(['Кошик', 'Оформити замовлення', 'Замовлення прийнято']), 'heading_title' => 'Ваше замовлення прийняте!',
                'continue' => '/', 'column_left' => '', 'column_right' => '', 'content_top' => '', 'content_bottom' => '', 'order_data' => [], 'order_items' => [], 'order_totals' => [],
                'ga4_purchase_payload' => null, 'is_logged' => false, 'history_url' => ''];
            if ($st === 'e') {
                $data['text_message'] = '<p>Замовлення в грі — ми вже збираємо ваш лут 🎴</p>
<p>У разі виникнення питань напишіть <a href="https://t.me/boostershop_tcg" target="_blank" rel="noopener" class="bs-telegram-button">нам в Telegram</a>.</p>
<p>Чек надійде вам у SMS або Viber після обробки замовлення.</p>
<p>Ми не телефонуємо і не пишемо для підтвердження без потреби. Якщо всі дані в замовленні заповнені коректно — просто тихо і швидко відправляємо 🙂</p>
<p>Дякуємо, що обрали <strong>Booster Shop</strong> 🖤</p>
<p><strong>Вдалого анпакінгу 🎁</strong></p>';
            } else {
                $o = success_state($st);
                $data['order_data'] = ['order_id' => $o['id'], 'shipping_method' => $o['ship'], 'shipping_display_text' => $o['shipText'], 'payment_method' => $o['payMethod'], 'payment_code' => $o['pay'],
                    'is_hutko' => $o['pay'] === 'hutko', 'is_cod' => $o['pay'] === 'cod', 'is_iban_bank_transfer' => $o['pay'] === 'iban', 'show_first15_offer' => $o['first15']];
                foreach ($o['items'] as $i) $data['order_items'][] = ['name' => $i[1], 'model' => 'X', 'quantity' => $i[0], 'price' => $i[2], 'total' => $i[2]];
                foreach ($o['totals'] as $t) $data['order_totals'][] = ['code' => 'x', 'title' => $t[0], 'text' => $t[1]];
                $data['is_logged'] = $o['logged'];
                $data['history_url'] = $o['logged'] ? '#history' : '';
                $data['ga4_purchase_payload'] = '{"transaction_id":"' . $o['id'] . '","value":1}';
                $logged = $o['logged'];
            }
            // ps_dataLayer stub so the GA4 line can be counted in the console.
            $data['content_bottom'] = '';
            $stub = '<script>window.ps_dataLayer={pushEventData:function(e,p){window.__ga4=(window.__ga4||[]);window.__ga4.push([e,p]);console.log("GA4",e,JSON.stringify(p));}};</script>';
            return str_replace('</head>', $stub . '</head>', header_html(['title' => 'Ваше замовлення прийняте!', 'route' => 'checkout/success'])) . tpl('checkout/success', $data) . footer_html();
        case 'failure':
            $lang = []; $_ = [];
            include $GLOBALS['W'] . '/extension/ukrainian/catalog/language/uk-ua/checkout/failure.php';
            $data = ['breadcrumbs' => crumbs(['Кошик покупок', 'Оформлення замовлення', $_['text_failure']]), 'heading_title' => $_['heading_title'],
                'text_message' => sprintf($_['text_message'], 'index.php?route=information/contact'), 'continue' => '/',
                'column_left' => '', 'column_right' => '', 'content_top' => '', 'content_bottom' => ''];
            return header_html(['title' => $_['heading_title'], 'route' => 'checkout/failure']) . tpl('checkout/failure', $data) . footer_html();
        default:
            if (function_exists('render_page_more')) return render_page_more($page);
            return 'unknown page';
    }
}

if (is_file(__DIR__ . '/fixtures_more.php')) require __DIR__ . '/fixtures_more.php';
