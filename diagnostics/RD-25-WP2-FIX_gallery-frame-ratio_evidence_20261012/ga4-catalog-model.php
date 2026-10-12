<?php
namespace Opencart\Catalog\Model\Extension\PsEnhancedMeasurement\Analytics;
/**
 * Class PsEnhancedMeasurement
 *
 * @package Opencart\Catalog\Model\Extension\PsEnhancedMeasurement\Analytics
 */
class PsEnhancedMeasurement extends \Opencart\System\Engine\Model
{
    /**
     * @param array $args
     *
     * @return array
     */
    public function replaceCatalogViewCommonHeaderBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '<body>',
            'replace' => <<<HTML
            <body>
            {% if ps_enhanced_measurement_implementation == 'gtm' %}
            <!-- Google Tag Manager (noscript) -->
            <noscript>
                <iframe src="https://www.googletagmanager.com/ns.html?id={{ ps_enhanced_measurement_gtm_id }}" height="0" width="0" style="display:none; visibility:hidden;"></iframe>
            </noscript>
            <!-- End Google Tag Manager (noscript) -->
            {% endif %}
            HTML
        ];

        $views[] = [
            'search' => '</head>',
            'replace' => <<<HTML
            <script>
                // ST-2a.10: keep analytics from breaking storefront flows before consent/bootstrap.
                window.dataLayer = window.dataLayer || [];
                window.gtag = window.gtag || function(){ window.dataLayer.push(arguments); };

                var ps_dataLayer = {
                    filename_ext: {{ ps_enhanced_measurement_track_file_download_ext | default('[]') }},
                    tracking_delay: {{ ps_enhanced_measurement_tracking_delay | default(800) }},
                    adwords_user_data: {{ ps_enhanced_measurement_adwords_user_data | default('{}') }},
                    adwords_tracking: {{ ps_enhanced_measurement_adwords_tracking | default('{}') }},
                    ga4_tracking: {{ ps_enhanced_measurement_ga4_tracking | default('{}') }},
                    ga4_data: {},
                    init: function () {
                        document.querySelectorAll('a[href]').forEach(function(link, index) {
                            var urlObj = new URL(link.getAttribute('href'), window.location.origin);
                            var fileName = urlObj.pathname.split('/').pop();
                            var fileExtension = '.' + fileName.split('.').pop().toLowerCase();

                            if (this.filename_ext.includes(fileExtension)) {
                                link.setAttribute('data-ps-track-event', 'file_download');
                                link.setAttribute('data-ps-track-id', 'custom_' + index);

                                this.ga4_data['file_download_custom_' + index] = {
                                    event: 'file_download',
                                    file_extension: fileExtension,
                                    file_name: fileName,
                                    link_text: link.textContent.trim() || "Unknown",
                                    link_url: urlObj.href,
                                };
                            }
                        }, this);
                    },
                    getData: function(eventName, productId) {
                        var dataId = eventName + '_' + productId;

                        if (this.ga4_data.hasOwnProperty(dataId)) {
                            return this.ga4_data[dataId];
                        } else {
                            console.error('Enhanced Measurement (Error): `' + dataId + '` dataset does not exists!');
                        }

                        return null;
                    },
                    setData: function(data) {
                        this.ga4_data = Object.assign({}, this.ga4_data, data);
                    },
                    onClick: function(eventName, productId) {
                        var dataId = eventName + '_' + productId;

                        if (this.ga4_data.hasOwnProperty(dataId)) {
                            this.pushEventData(eventName, this.ga4_data[dataId]);
                        } else {
                            console.error('Enhanced Measurement (Error): `' + dataId + '` dataset does not exists!');
                        }
                    },
                    pushEventData: function(eventName, data) {
                        if (this.ga4_tracking[eventName]) {
                            {% if ps_enhanced_measurement_implementation == 'gtag' %}
                            if (typeof window.gtag === 'function') {
                                if (data.hasOwnProperty('ecommerce')) {
                                    window.gtag('event', eventName, data.ecommerce);
                                } else {
                                    window.gtag('event', eventName, data);
                                }
                            }
                            {% elseif ps_enhanced_measurement_implementation == 'gtm' %}
                            dataLayer.push({ ecommerce: null });
                            dataLayer.push( { 'event': eventName, ...data } );
                            {% endif %}
                            {% if ps_enhanced_measurement_console_log_ga4_events %}
                            this.debug('{{ ps_enhanced_measurement_implementation }}', 'event', eventName, data);
                            {% endif %}
                        }

                    {% if ps_enhanced_measurement_adwords_status %}
                        if (this.adwords_tracking.hasOwnProperty(eventName) && typeof window.gtag === 'function') {
                        {% if ps_enhanced_measurement_adwords_enhanced_conversion %}
                            window.gtag('set', 'user_data', this.adwords_user_data);
                            {% if ps_enhanced_measurement_console_log_adwords_events %}
                            this.debug('gtag', 'set', 'user_data', this.adwords_user_data);
                            {% endif %}
                        {% endif %}

                        var conversion_data = {'send_to': this.adwords_tracking[eventName]};

                        if (data.hasOwnProperty('ecommerce')) {
                            conversion_data.currency = data.ecommerce.currency;

                            if (eventName === 'purchase') {
                                conversion_data.value = data.ecommerce.value + data.ecommerce.tax + data.ecommerce.shipping;
                                conversion_data.transaction_id = data.ecommerce.transaction_id;
                            } else {
                                conversion_data.value = data.ecommerce.value;
                            }
                        } else {
                            conversion_data.value = data.value;
                            conversion_data.currency = data.currency;
                        }

                        window.gtag('event', 'conversion', conversion_data);
                        {% if ps_enhanced_measurement_console_log_adwords_events %}
                        this.debug('gtag', 'event', 'conversion', conversion_data);
                        {% endif %}
                        }
                    {% endif %}
                    },
                    debug: function(method, command, eventName, data) {
                        if (method == 'gtag') {
                            if (data.hasOwnProperty('ecommerce')) {
                                console.log("gtag('" + command + "', '" + eventName + "', " + JSON.stringify(data.ecommerce, undefined, 4) +");");
                            } else {
                                console.log("gtag('" + command + "', '" + eventName + "', " + JSON.stringify(data, undefined, 4) +");");
                            }
                        } else if (method == 'gtm') {
                            console.log('dataLayer.push({ ecommerce: null });\\r\\n');
                            console.log("dataLayer.push(" + JSON.stringify(Object.assign({}, {event: eventName}, data), undefined, 4) + ");");
                        }
                    }
                };

                window.onload = function() { ps_dataLayer.init(); };
            </script>
            </head>
            HTML
        ];

        $views[] = [
            'search' => '</head>',
            'replace' => <<<HTML
            {% if ps_login %}<script>ps_dataLayer.pushEventData('login', {{ ps_login }});</script>{% endif %}
            </head>
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewProductThumbBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '<a href="{{ href }}">',
            'replace' => '<a href="{{ href }}"
                data-ps-track-id="{{ product_id }}"
                {% if ps_track_select_promotion and special %}data-ps-track-event="select_promotion"
                {% elseif ps_track_select_item and not special %}data-ps-track-event="select_item"{% endif %}
            >'
        ];

        if (version_compare(VERSION, '4.1.0.0', '>=')) {
            $views[] = [
                'search' => '<button type="submit" formaction="{{ cart_add }}" data-bs-toggle="tooltip" title="{{ button_cart }}"',
                'replace' => '<button type="submit" formaction="{{ cart_add }}" data-bs-toggle="tooltip" title="{{ button_cart }}"
                data-ps-track-id="{{ product_id }}"
                {% if ps_has_options %}
                    {% if ps_track_select_promotion and special %}data-ps-track-event="select_promotion"
                    {% elseif ps_track_select_item and not special %}data-ps-track-event="select_item"{% endif %}
                {% else %}
                    {% if ps_track_add_to_cart %}data-ps-track-event="add_to_cart"{% endif %}
                {% endif %}
            '
            ];
        } else {
            $views[] = [
                'search' => '<button type="submit" formaction="{{ add_to_cart }}"',
                'replace' => '<button type="submit" formaction="{{ add_to_cart }}"
                data-ps-track-id="{{ product_id }}"
                {% if ps_has_options %}
                    {% if ps_track_select_promotion and special %}data-ps-track-event="select_promotion"
                    {% elseif ps_track_select_item and not special %}data-ps-track-event="select_item"{% endif %}
                {% else %}
                    {% if ps_track_add_to_cart %}data-ps-track-event="add_to_cart"{% endif %}
                {% endif %}
            '
            ];
        }

        if (version_compare(VERSION, '4.1.0.0', '>=')) {
            $views[] = [
                'search' => '<button type="submit" formaction="{{ wishlist_add }}" data-bs-toggle="tooltip" title="{{ button_wishlist }}"',
                'replace' => '<button type="submit" formaction="{{ wishlist_add }}" data-bs-toggle="tooltip" title="{{ button_wishlist }}"
                data-ps-track-id="{{ product_id }}"
                {% if ps_track_add_to_wishlist %}data-ps-track-event="add_to_wishlist"{% endif %}
            '
            ];
        } else {
            $views[] = [
                'search' => '<button type="submit" formaction="{{ add_to_wishlist }}"',
                'replace' => '<button type="submit" formaction="{{ add_to_wishlist }}"
                data-ps-track-id="{{ product_id }}"
                {% if ps_track_add_to_wishlist %}data-ps-track-event="add_to_wishlist"{% endif %}
            '
            ];
        }

        return $views;
    }

    public function replaceCatalogViewProductCategoryBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '{% if products %}',
            'replace' => <<<HTML
            {% if ps_merge_items %}<script>ps_dataLayer.setData({{ ps_merge_items }});</script>{% endif %}
            {% if ps_track_view_item_list and ps_view_item_list %}<script>ps_dataLayer.pushEventData('view_item_list', {{ ps_view_item_list }});</script>{% endif %}
            {% if products %}
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewProductSearchBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '{% if products %}',
            'replace' => <<<HTML
            {% if ps_merge_items %}<script>ps_dataLayer.setData({{ ps_merge_items }});</script>{% endif %}
            {% if ps_track_search and ps_search %}<script>ps_dataLayer.pushEventData('search', {{ ps_search }});</script>{% endif %}
            {% if ps_track_view_item_list and ps_view_item_list %}<script>ps_dataLayer.pushEventData('view_item_list', {{ ps_view_item_list }});</script>{% endif %}
            {% if products %}
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewProductSpecialBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '{% if products %}',
            'replace' => <<<HTML
            {% if ps_merge_items %}<script>ps_dataLayer.setData({{ ps_merge_items }});</script>{% endif %}
            {% if ps_track_view_item_list and ps_view_item_list %}<script>ps_dataLayer.pushEventData('view_item_list', {{ ps_view_item_list }});</script>{% endif %}
            {% if products %}
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewProductCompareBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '<a href="{{ product.href }}">',
            'replace' => '<a href="{{ product.href }}"
                data-ps-track-id="{{ product.product_id }}"
                {% if ps_track_select_promotion and product.special %}data-ps-track-event="select_promotion"
                {% elseif ps_track_select_item and not product.special %}data-ps-track-event="select_item"{% endif %}
            >',
        ];

        $views[] = [
            'search' => '<button type="submit" id="button-confirm"',
            'replace' => '<button type="submit" id="button-confirm"
                data-ps-track-id="{{ product.product_id }}"
                {% if product.ps_has_options %}
                    {% if ps_track_select_promotion and product.special %}data-ps-track-event="select_promotion"
                    {% elseif ps_track_select_item and not product.special %}data-ps-track-event="select_item"{% endif %}
                {% else %}
                    {% if ps_track_add_to_cart %}data-ps-track-event="add_to_cart"{% endif %}
                {% endif %}
            '
        ];

        $views[] = [
            'search' => '{% if products %}',
            'replace' => <<<HTML
            {% if ps_merge_items %}<script>ps_dataLayer.setData({{ ps_merge_items }});</script>{% endif %}
            {% if ps_track_view_item_list and ps_view_item_list %}<script>ps_dataLayer.pushEventData('view_item_list', {{ ps_view_item_list }});</script>{% endif %}
            {% if products %}
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewAccountWishlistBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '<div id="wishlist">{{ list }}</div>',
            'replace' => <<<HTML
            <div id="wishlist">{{ list }}</div>
            {% if ps_merge_items %}<script>ps_dataLayer.setData({{ ps_merge_items }});</script>{% endif %}
            {% if ps_track_view_item_list and ps_view_item_list %}<script>ps_dataLayer.pushEventData('view_item_list', {{ ps_view_item_list }});</script>{% endif %}
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewProductManufacturerInfoBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '{% if products %}',
            'replace' => <<<HTML
            {% if ps_merge_items %}<script>ps_dataLayer.setData({{ ps_merge_items }});</script>{% endif %}
            {% if ps_track_view_item_list and ps_view_item_list %}<script>ps_dataLayer.pushEventData('view_item_list', {{ ps_view_item_list }});</script>{% endif %}
            {% if products %}
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewAccountWishlistListBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '<a href="{{ product.href }}">',
            'replace' => '<a href="{{ product.href }}"
                data-ps-track-id="{{ product.product_id }}"
                {% if ps_track_select_promotion and product.special %}data-ps-track-event="select_promotion"
                {% elseif ps_track_select_item and not product.special %}data-ps-track-event="select_item"{% endif %}
            >',
        ];

        if (version_compare(VERSION, '4.1.0.0', '>=')) {
            $views[] = [
                'search' => '<button type="submit" form="form-product-{{ product_row }}" class="btn btn-primary mb-1">{{ button_cart }}</button>',
                'replace' => '<button type="submit" form="form-product-{{ product_row }}"
                data-ps-track-id="{{ product.product_id }}"
                {% if product.ps_has_options %}
                    {% if ps_track_select_promotion and product.special %}data-ps-track-event="select_promotion"
                    {% elseif ps_track_select_item and not product.special %}data-ps-track-event="select_item"{% endif %}
                {% else %}
                    {% if ps_track_add_to_cart %}data-ps-track-event="add_to_cart"{% endif %}
                {% endif %}
                 class="btn btn-primary mb-1">{{ button_cart }}</button>
            '
            ];
        } else {
            $views[] = [
                'search' => '<button type="submit" formaction="{{ add_to_cart }}"',
                'replace' => '<button type="submit" formaction="{{ add_to_cart }}"
                data-ps-track-id="{{ product.product_id }}"
                {% if product.ps_has_options %}
                    {% if ps_track_select_promotion and product.special %}data-ps-track-event="select_promotion"
                    {% elseif ps_track_select_item and not product.special %}data-ps-track-event="select_item"{% endif %}
                {% else %}
                    {% if ps_track_add_to_cart %}data-ps-track-event="add_to_cart"{% endif %}
                {% endif %}
            '
            ];
        }

        return $views;
    }

    public function replaceCatalogViewAccountDownloadBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '<a href="{{ download.href }}"',
            'replace' => '<a href="{{ download.href }}"
                data-ps-track-id="{{ download.order_id }}"
                {% if ps_track_file_download %}data-ps-track-event="file_download"{% endif %}
            ',
        ];

        $views[] = [
            'search' => '{{ footer }}',
            'replace' => <<<HTML
            {% if ps_merge_items %}<script>ps_dataLayer.setData({{ ps_merge_items }});</script>{% endif %}
            {{ footer }}
            HTML
        ];

        return $views;
    }


    public function replaceCatalogViewAccountOrderInfoBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '<a href="{{ product.href }}">',
            'replace' => '<a href="{{ product.href }}"
                data-ps-track-id="{{ product.product_id }}"
                {% if ps_track_select_promotion and product.special %}data-ps-track-event="select_promotion"
                {% elseif ps_track_select_item and not product.special %}data-ps-track-event="select_item"{% endif %}
            >',
        ];

        $views[] = [
            'search' => '<a href="{{ product.reorder }}"',
            'replace' => '<a href="{{ product.reorder }}"
                data-ps-track-id="{{ product.product_id }}"
                {% if product.ps_has_options %}
                    {% if ps_track_select_promotion and product.special %}data-ps-track-event="select_promotion"
                    {% elseif ps_track_select_item and not product.special %}data-ps-track-event="select_item"{% endif %}
                {% else %}
                    {% if ps_track_add_to_cart %}data-ps-track-event="add_to_cart"{% endif %}
                {% endif %}
            '
        ];

        $views[] = [
            'search' => '{{ footer }}',
            'replace' => <<<HTML
            {% if ps_merge_items %}<script>ps_dataLayer.setData({{ ps_merge_items }});</script>{% endif %}
            {% if ps_track_view_item_list and ps_view_item_list %}<script>ps_dataLayer.pushEventData('view_item_list', {{ ps_view_item_list }});</script>{% endif %}
            {{ footer }}
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewProductProductBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '<button type="submit" id="button-cart"',
            'replace' => '<input type="hidden" name="category_id" value="{{ ps_category_id }}">
            <button type="submit" id="button-cart"'
        ];

        if (version_compare(VERSION, '4.1.0.0', '>=')) {
            $views[] = [
                'search' => '<button type="submit" formaction="{{ wishlist_add }}"',
                'replace' => '<button type="submit" formaction="{{ wishlist_add }}"
                data-ps-track-id="{{ product_id }}"
                {% if ps_track_add_to_wishlist %}data-ps-track-event="add_to_wishlist"{% endif %}
            '
            ];
        } else {
            $views[] = [
                'search' => '<button type="submit" formaction="{{ add_to_wishlist }}"',
                'replace' => '<button type="submit" formaction="{{ add_to_wishlist }}"
                data-ps-track-id="{{ product_id }}"
                {% if ps_track_add_to_wishlist %}data-ps-track-event="add_to_wishlist"{% endif %}
            '
            ];
        }

        $views[] = [
            'search' => 'if (json[\'success\']) {',
            'replace' => <<<HTML
            {% if ps_track_add_to_cart %}
            if (json['ps_add_to_cart']) {
                ps_dataLayer.pushEventData('add_to_cart', json['ps_add_to_cart']);
            }
            {% endif %}
            if (json['success']) {
            HTML
        ];

        $views[] = [
            'search' => '{{ footer }}',
            'replace' => <<<HTML
            {% if ps_merge_items %}<script>ps_dataLayer.setData({{ ps_merge_items }});</script>{% endif %}
            {% if ps_track_view_promotion and ps_view_promotion %}<script>ps_dataLayer.pushEventData('view_promotion', {{ ps_view_promotion }});</script>{% endif %}
            {% if ps_track_view_item and ps_view_item %}<script>ps_dataLayer.pushEventData('view_item', {{ ps_view_item }});</script>{% endif %}
            {{ footer }}
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewAccountSuccessBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '{{ text_message }}',
            'replace' => <<<HTML
            {{ text_message }}
            {% if ps_track_sign_up and ps_sign_up %}<script>ps_dataLayer.pushEventData('sign_up', {{ ps_sign_up }});</script>{% endif %}
            {% if ps_track_generate_lead and ps_generate_lead_newsletter %}<script>ps_dataLayer.pushEventData('generate_lead', {{ ps_generate_lead_newsletter }});</script>{% endif %}
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewCheckoutSuccessBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '{{ text_message }}',
            'replace' => <<<HTML
            {{ text_message }}
            {% if ps_track_purchase and ps_purchase %}<script>ps_dataLayer.pushEventData('purchase', {{ ps_purchase }});</script>{% endif %}
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewCheckoutRegisterBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => 'if (json[\'success\']) {',
            'replace' => <<<HTML
            {% if ps_track_sign_up %}
            if (json['ps_sign_up']) {
                ps_dataLayer.pushEventData('sign_up', json['ps_sign_up']);
            }
            {% endif %}
            {% if ps_track_generate_lead %}
            if (json['ps_generate_lead_newsletter']) {
                ps_dataLayer.pushEventData('generate_lead', json['ps_generate_lead_newsletter']);
            }
            {% endif %}
            if (json['success']) {
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewAccountAccountBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '<h2>{{ text_my_account }}</h2>',
            'replace' => <<<HTML
            <h2>{{ text_my_account }}</h2>
            {% if ps_track_generate_lead and ps_generate_lead_newsletter %}<script>ps_dataLayer.pushEventData('generate_lead', {{ ps_generate_lead_newsletter }});</script>{% endif %}
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewInformationContactSuccessBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '{{ text_message }}',
            'replace' => <<<HTML
            {{ text_message }}
            {% if ps_track_generate_lead and ps_generate_lead_contact_form %}<script>ps_dataLayer.pushEventData('generate_lead', {{ ps_generate_lead_contact_form }});</script>{% endif %}
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewCheckoutPaymentMethodBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => 'if (json[\'success\']) {',
            'replace' => <<<HTML
            {% if ps_track_add_payment_info %}
            if (json['ps_add_payment_info']) {
                ps_dataLayer.pushEventData('add_payment_info', json['ps_add_payment_info']);
            }
            {% endif %}
            if (json['success']) {
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewCheckoutShippingMethodBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => 'if (json[\'success\']) {',
            'replace' => <<<HTML
            {% if ps_track_add_shipping_info %}
            if (json['ps_add_shipping_info']) {
                ps_dataLayer.pushEventData('add_shipping_info', json['ps_add_shipping_info']);
            }
            {% endif %}
            if (json['success']) {
            HTML
        ];

        return $views;
    }

    public function replaceCatalogViewCheckoutCheckoutBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '<h1>{{ heading_title }}</h1>',
            'replace' => '<h1>{{ heading_title }}</h1>
            {% if ps_track_begin_checkout and ps_begin_checkout %}<script>ps_dataLayer.pushEventData(\'begin_checkout\', {{ ps_begin_checkout }});</script>{% endif %}
            {% if ps_track_qualify_lead and ps_qualify_lead %}<script>ps_dataLayer.pushEventData(\'qualify_lead\', {{ ps_qualify_lead }});</script>{% endif %}'
        ];

        return $views;
    }

    public function replaceCatalogViewCheckoutCartBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '<div id="shopping-cart">{{ list }}</div>',
            'replace' => '<div id="shopping-cart">{{ list }}</div>
            {% if ps_track_view_cart and ps_view_cart %}<script>ps_dataLayer.pushEventData(\'view_cart\', {{ ps_view_cart }});</script>{% endif %}'
        ];

        return $views;
    }

    /**
     * Main checkout page - list controller
     *
     * @param array $args
     * @return array
     */
    public function replaceCatalogViewCheckoutCartListBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '<a href="{{ product.href }}">',
            'replace' => '<a href="{{ product.href }}"
                data-ps-track-id="{{ product.cart_id }}"
                {% if ps_track_select_promotion and product.special %}data-ps-track-event="select_promotion"
                {% elseif ps_track_select_item and not product.special %}data-ps-track-event="select_item"{% endif %}
            >',
        ];

        $views[] = [
            'search' => '<input type="text" name="quantity"',
            'replace' => '<input type="text" id="product-quantity-{{ product.cart_id }}" name="quantity"',
        ];

        if (version_compare(VERSION, '4.1.0.0', '<=')) {
            $views[] = [
                'search' => '<button type="submit" formaction="{{ product_edit }}"',
                'replace' => '<button type="submit" formaction="{{ product_edit }}"
                data-ps-track-id="{{ product.cart_id }}"
                {% if ps_track_add_to_cart or ps_track_remove_from_cart %}data-ps-track-event="update_cart"{% endif %}
            ',
            ];

            $views[] = [
                'search' => '<button type="submit" formaction="{{ product_remove }}"',
                'replace' => '<button type="submit" formaction="{{ product_remove }}"
                data-ps-track-id="{{ product.cart_id }}"
                {% if ps_track_remove_from_cart %}data-ps-track-event="remove_from_cart"{% endif %}
            ',
            ];
        } else { // OpenCart >= 4.1.0.1
            $views[] = [
                'search' => '<button type="submit" formaction="{{ edit }}"',
                'replace' => '<button type="submit" formaction="{{ edit }}"
                data-ps-track-id="{{ product.cart_id }}"
                {% if ps_track_add_to_cart or ps_track_remove_from_cart %}data-ps-track-event="update_cart"{% endif %}
            ',
            ];

            $views[] = [
                'search' => '<a href="{{ product.remove }}"',
                'replace' => '<a href="{{ product.remove }}"
                data-ps-track-id="{{ product.cart_id }}"
                {% if ps_track_remove_from_cart %}data-ps-track-event="remove_from_cart"{% endif %}
            ',
            ];
        }

        $views[] = [
            'search' => '<table class="table table-bordered">',
            'replace' => '{% if ps_merge_items %}<script>ps_dataLayer.setData({{ ps_merge_items }});</script>{% endif %}
            <table class="table table-bordered">'
        ];

        return $views;
    }

    /**
     * Dropdown cart button list
     *
     * @param array $args
     * @return array
     */
    public function replaceCatalogViewCheckoutCartInfoBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '<a href="{{ product.href }}">',
            'replace' => '<a href="{{ product.href }}"
                data-ps-track-id="{{ product.cart_id }}"
                {% if ps_track_select_promotion and product.special %}data-ps-track-event="select_promotion"
                {% elseif ps_track_select_item and not product.special %}data-ps-track-event="select_item"{% endif %}
            >',
        ];

        $views[] = [
            'search' => '<button type="submit" data-bs-toggle="tooltip" title="{{ button_remove }}"',
            'replace' => '<button type="submit" data-bs-toggle="tooltip" title="{{ button_remove }}"
                data-ps-track-id="{{ product.cart_id }}"
                {% if ps_track_remove_from_cart %}data-ps-track-event="remove_from_cart"{% endif %}
            ',
        ];

        $views[] = [
            'search' => '<button type="button" data-bs-toggle="dropdown"',
            'replace' => '{% if ps_merge_items %}<script>ps_dataLayer.setData({{ ps_merge_items }});</script>{% endif %}
            <button type="button" data-bs-toggle="dropdown"'
        ];

        return $views;
    }

    public function replaceCatalogViewCheckoutConfirmBefore(array $args): array
    {
        $views = [];

        $views[] = [
            'search' => '<a href="{{ product.href }}">',
            'replace' => '<a href="{{ product.href }}"
                data-ps-track-id="{{ product.cart_id }}"
                {% if ps_track_select_promotion and product.special %}data-ps-track-event="select_promotion"
                {% elseif ps_track_select_item and not product.special %}data-ps-track-event="select_item"{% endif %}
            >',
        ];

        return $views;
    }

    public function saveGA4ClientId($orderId, $clientId): void
    {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "ps_refund_order` (`refund_id`, `order_id`, `user_id`, `client_id`, `refunded`, `date_added`) VALUES (NULL, '" . (int) $orderId . "', '" . $this->customer->getId() . "', '" . $this->db->escape($clientId) . "', '0', NOW())");
    }

    public function hasOptions(int $product_id): bool
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product_option` WHERE `product_id` = '" . (int) $product_id . "' AND `required` = '1'");

        if ((int) $query->row['total'] > 0) {
            return true;
        }

        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product_subscription` WHERE `product_id` = '" . (int) $product_id . "'");

        if ((int) $query->row['total'] > 0) {
            return true;
        }

        return false;
    }

    public function getProductOptionInfo($options, $productId): array
    {
        $option_price = 0;
        $variant = [];

        foreach ($options as $product_option_id => $value) {
            $option_query = $this->db->query("SELECT po.`product_option_id`, po.`option_id`, od.`name`, o.`type` FROM `" . DB_PREFIX . "product_option` po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.`option_id` = o.`option_id`) LEFT JOIN `" . DB_PREFIX . "option_description` od ON (o.`option_id` = od.`option_id`) WHERE po.`product_option_id` = '" . (int) $product_option_id . "' AND po.`product_id` = '" . (int) $productId . "' AND od.`language_id` = '" . (int) $this->config->get('config_language_id') . "'");

            if ($option_query->num_rows) {
                if ($option_query->row['type'] == 'select' || $option_query->row['type'] == 'radio') {
                    $option_value_query = $this->db->query("SELECT pov.`option_value_id`, ovd.`name`, pov.`quantity`, pov.`subtract`, pov.`price`, pov.`price_prefix`, pov.`points`, pov.`points_prefix`, pov.`weight`, pov.`weight_prefix` FROM `" . DB_PREFIX . "product_option_value` pov LEFT JOIN `" . DB_PREFIX . "option_value` ov ON (pov.`option_value_id` = ov.`option_value_id`) LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (ov.`option_value_id` = ovd.`option_value_id`) WHERE pov.`product_option_value_id` = '" . (int) $value . "' AND pov.`product_option_id` = '" . (int) $product_option_id . "' AND ovd.`language_id` = '" . (int) $this->config->get('config_language_id') . "'");

                    if ($option_value_query->num_rows) {
                        if ($option_value_query->row['price_prefix'] == '+') {
                            $option_price += $option_value_query->row['price'];
                        } elseif ($option_value_query->row['price_prefix'] == '-') {
                            $option_price -= $option_value_query->row['price'];
                        }

                        $variant[] = html_entity_decode($option_query->row['name'] . ': ' . $option_value_query->row['name'], ENT_QUOTES, 'UTF-8');
                    }
                } elseif ($option_query->row['type'] == 'checkbox' && is_array($value)) {
                    foreach ($value as $product_option_value_id) {
                        $option_value_query = $this->db->query("SELECT pov.`option_value_id`, pov.`quantity`, pov.`subtract`, pov.`price`, pov.`price_prefix`, pov.`points`, pov.`points_prefix`, pov.`weight`, pov.`weight_prefix`, ovd.`name` FROM `" . DB_PREFIX . "product_option_value` `pov` LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (pov.`option_value_id` = ovd.option_value_id) WHERE pov.product_option_value_id = '" . (int) $product_option_value_id . "' AND pov.product_option_id = '" . (int) $product_option_id . "' AND ovd.language_id = '" . (int) $this->config->get('config_language_id') . "'");

                        if ($option_value_query->num_rows) {
                            if ($option_value_query->row['price_prefix'] == '+') {
                                $option_price += $option_value_query->row['price'];
                            } elseif ($option_value_query->row['price_prefix'] == '-') {
                                $option_price -= $option_value_query->row['price'];
                            }

                            $variant[] = html_entity_decode($option_query->row['name'] . ': ' . $option_value_query->row['name'], ENT_QUOTES, 'UTF-8');
                        }
                    }
                } elseif ($option_query->row['type'] == 'text' || $option_query->row['type'] == 'textarea' || $option_query->row['type'] == 'file' || $option_query->row['type'] == 'date' || $option_query->row['type'] == 'datetime' || $option_query->row['type'] == 'time') {
                    $variant[] = html_entity_decode($option_query->row['name'] . ': ' . $value, ENT_QUOTES, 'UTF-8');
                }

            }
        }

        return [
            'option_price' => $option_price,
            'variant' => $variant,
        ];
    }

    public function getProductDiscount($productId, $quantity)
    {
        $product_discount_query = $this->db->query("SELECT `price` FROM `" . DB_PREFIX . "product_discount` WHERE `product_id` = '" . (int) $productId . "' AND `customer_group_id` = '" . (int) $this->config->get('config_customer_group_id') . "' AND `quantity` <= '" . (int) $quantity . "' AND ((`date_start` = '0000-00-00' OR `date_start` < NOW()) AND (`date_end` = '0000-00-00' OR `date_end` > NOW())) ORDER BY `quantity` DESC, `priority` ASC, `price` ASC LIMIT 1");

        if ($product_discount_query->num_rows) {
            return $product_discount_query->row['price'];
        }

        return false;
    }

    public function getProductSpecial($productId)
    {
        if (version_compare(VERSION, '4.1.0.0', '>=')) {
            $product_special_query = $this->db->query("SELECT (CASE WHEN `ps`.`type` = 'P' THEN (`ps`.`price` * (`p`.`price` / 100)) WHEN `ps`.`type` = 'S' THEN (`p`.`price` - `ps`.`price`) ELSE `ps`.`price` END) AS `price` FROM `" . DB_PREFIX . "product_discount` `ps` LEFT JOIN `" . DB_PREFIX . "product` `p` ON (`p`.`product_id` = `ps`.`product_id` AND `p`.`status` = '1' AND `p`.`date_available` <= NOW()) WHERE `ps`.`product_id` = '" . (int) $productId . "' AND `ps`.`customer_group_id` = '" . (int) $this->config->get('config_customer_group_id') . "' AND `ps`.`quantity` = '1' AND `ps`.`special` = '1' AND ((`ps`.`date_start` = '0000-00-00' OR `ps`.`date_start` < NOW()) AND (`ps`.`date_end` = '0000-00-00' OR `ps`.`date_end` > NOW())) ORDER BY `ps`.`priority` ASC, `ps`.`price` ASC LIMIT 1");
        } else {
            $product_special_query = $this->db->query("SELECT `price` FROM `" . DB_PREFIX . "product_special` WHERE `product_id` = '" . (int) $productId . "' AND `customer_group_id` = '" . (int) $this->config->get('config_customer_group_id') . "' AND ((`date_start` = '0000-00-00' OR `date_start` < NOW()) AND (`date_end` = '0000-00-00' OR `date_end` > NOW())) ORDER BY `priority` ASC, `price` ASC LIMIT 1");
        }

        if ($product_special_query->num_rows) {
            return $product_special_query->row['price'];
        }

        return false;
    }

    public function getProductScubscription($productId, $subscriptionPlanId)
    {
        $subscription_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_subscription` ps LEFT JOIN `" . DB_PREFIX . "subscription_plan` sp ON (ps.`subscription_plan_id` = sp.`subscription_plan_id`) LEFT JOIN `" . DB_PREFIX . "subscription_plan_description` spd ON (sp.`subscription_plan_id` = spd.`subscription_plan_id`) WHERE ps.`product_id` = '" . (int) $productId . "' AND ps.`subscription_plan_id` = '" . (int) $subscriptionPlanId . "' AND ps.`customer_group_id` = '" . (int) $this->config->get('config_customer_group_id') . "' AND spd.`language_id` = '" . (int) $this->config->get('config_language_id') . "' AND sp.`status` = '1'");

        if ($subscription_query->num_rows) {
            return $subscription_query->row;
        }

        return false;
    }

    public function getManufacturerNameByProductId($product_id)
    {
        $query = $this->db->query("
            SELECT m.name
            FROM " . DB_PREFIX . "manufacturer m
            INNER JOIN " . DB_PREFIX . "product p ON m.manufacturer_id = p.manufacturer_id
            WHERE p.product_id = '" . (int) $product_id . "'
            LIMIT 1
        ");

        if ($query->num_rows) {
            return ['name' => $query->row['name']];
        }

        return false;
    }

    public function getCategories(int $product_id): array
    {
        $product_category_data = [];

        $query = $this->db->query("SELECT `category_id` FROM `" . DB_PREFIX . "product_to_category` WHERE `product_id` = '" . (int) $product_id . "'");

        foreach ($query->rows as $result) {
            $product_category_data[] = $result['category_id'];
        }

        return $product_category_data;
    }

    public function getCategoryType1(int $category_id): array
    {
        $query = $this->db->query("SELECT
            DISTINCT *,
            (SELECT cd1.`name` FROM `" . DB_PREFIX . "category_path` cp LEFT JOIN `" . DB_PREFIX . "category_description` cd1 ON (cp.`path_id` = cd1.`category_id`) WHERE cp.`category_id` = c.`category_id` AND cd1.`language_id` = '" . (int) $this->config->get('config_language_id') . "' ORDER BY cp.`level` DESC LIMIT 1) AS `last_category_name`
        FROM `" . DB_PREFIX . "category` c
        LEFT JOIN `" . DB_PREFIX . "category_description` cd2 ON (c.`category_id` = cd2.`category_id`)
        WHERE c.`category_id` = '" . (int) $category_id . "' AND cd2.`language_id` = '" . (int) $this->config->get('config_language_id') . "'");

        return $query->row;
    }

    public function getCategoryType2(int $category_id): array
    {
        $query = $this->db->query("SELECT
                DISTINCT *,
                (SELECT GROUP_CONCAT(cd1.`name` ORDER BY `level` SEPARATOR ' &gt; ') FROM `" . DB_PREFIX . "category_path` cp LEFT JOIN `" . DB_PREFIX . "category_description` cd1 ON (cp.`path_id` = cd1.`category_id` AND cp.`category_id` != cp.`path_id`) WHERE cp.`category_id` = c.`category_id` AND cd1.`language_id` = '" . (int) $this->config->get('config_language_id') . "' GROUP BY cp.`category_id`) AS `path`
            FROM `" . DB_PREFIX . "category` c
            LEFT JOIN `" . DB_PREFIX . "category_description` cd2 ON (c.`category_id` = cd2.`category_id`)
            WHERE c.`category_id` = '" . (int) $category_id . "' AND cd2.`language_id` = '" . (int) $this->config->get('config_language_id') . "'");

        return $query->row;
    }

    public function getCategoryType3(int $category_id): array
    {
        $query = $this->db->query("SELECT cd1.`name`
            FROM `" . DB_PREFIX . "category_path` cp
            LEFT JOIN `" . DB_PREFIX . "category_description` cd1 ON (cp.`path_id` = cd1.`category_id`)
            WHERE cp.`category_id` = '" . (int) $category_id . "' AND cd1.`language_id` = '" . (int) $this->config->get('config_language_id') . "'
            ORDER BY cp.`level` ASC");

        return $query->rows;
    }
}
