<?php
/**
 * @since 9.4.1
 */
class shopMainMenu
{
    public static function get($options=[])
    {
        $wa_url = wa()->getRootUrl();
        $wa_app_url = wa('shop')->getAppUrl(null, true);

        $result = [
            "orders" => [
                "id" => "orders",
                "name" => _w("Orders"),
                "icon" => '<i class="fas fa-shopping-cart"></i>',
                "url" => "{$wa_app_url}?action=orders#/orders/",
                "userRights" => ['orders'],
                "placement" => "body",
            ],
            "customers" => [
                "id" => "customers",
                "name" => _w("Customers"),
                "icon" => '<i class="fas fa-user-friends"></i>',
                "url" => "{$wa_app_url}?action=customers#/shop/",
                "userRights" => ['customers'],
                "placement" => "body",
            ],
            "catalog" => [
                "id" => "catalog",
                "name" => _w("Products"),
                "icon" => '<i class="fas fa-archive"></i>',
                "userRights" => ['products'],
                "placement" => "body",
                "url" => "",
                "submenu" => [
                    [
                        "name" => _w("Catalog"),
                        "url" => "{$wa_app_url}products/"
                    ],
                    [
                        "name" => _w("Categories"),
                        "url" => "{$wa_app_url}products/categories/",
                        "userRights" => ['setscategories'],
                    ],
                    [
                        "name" => _w("Sets"),
                        "url" => "{$wa_app_url}products/sets/",
                        "userRights" => ['setscategories'],
                    ],
                    [
                        "name" => _w("Reviews"),
                        "url" => "{$wa_app_url}?action=products#/reviews/"
                    ],
                    [
                        "name" => _w("Services"),
                        "url" => "{$wa_app_url}?action=products#/services/",
                        "userRights" => ['services'],
                    ]
                ]
            ],
            "stock" => [
                "id" => "stock",
                "name" => _w("Stock"),
                "icon" => '<i class="fas fa-home"></i>',
                "userRights" => ['products'],
                "placement" => "body",
                "url" => "",
                "submenu" => [
                    [
                        "name" => _w("In stock now"),
                        "url" => "{$wa_app_url}?action=products#/stocks/"
                    ],
                    [
                        "name" => _w("Transfers"),
                        "url" => "{$wa_app_url}?action=products#/stocks/transfers/"
                    ],
                    [
                        "name" => _w("Stock log"),
                        "url" => "{$wa_app_url}?action=products#/stocks/log/",
                    ],
                ]
            ],
            "marketing" => [
                "id" => "marketing",
                "name" => _w("Marketing"),
                "icon" => '<i class="fas fa-bullhorn"></i>',
                "url" => "{$wa_app_url}marketing/",
                "userRights" => ['marketing'],
                "placement" => "body",
                "submenu" => [
                    [
                        "name" => _w("Promos on website"),
                        "url" => "{$wa_app_url}marketing/"
                    ],
                    [
                        "name" => _w("Coupons"),
                        "url" => "{$wa_app_url}marketing/coupons/"
                    ],
                    [
                        "name" => _w("Marketing costs"),
                        "url" => "{$wa_app_url}marketing/costs/"
                    ],
                    [
                        "name" => _w("A/B tests"),
                        "url" => "{$wa_app_url}marketing/abtesting/"
                    ],
                    [
                        "name" => _w("Discounts"),
                        "userRights" => ['setup_marketing'],
                        "url" => "{$wa_app_url}marketing/discounts/"
                    ],
                    [
                        "name" => _w("Follow-ups"),
                        "userRights" => ['setup_marketing'],
                        "url" => "{$wa_app_url}marketing/followups/"
                    ],
                    [
                        "name" => _w("Recommendations"),
                        "userRights" => ['setup_marketing'],
                        "url" => "{$wa_app_url}marketing/recommendations/"
                    ],
                    [
                        "name" => _w("Affiliate program"),
                        "userRights" => ['setup_marketing'],
                        "url" => "{$wa_app_url}marketing/affiliate/"
                    ]
                ]
            ],
            "reports" => [
                "id" => "reports",
                "name" => _w("Reports"),
                "icon" => '<i class="fas fa-chart-bar"></i>',
                "url" => "{$wa_app_url}?action=reports",
                "userRights" => ['reports'],
                "placement" => "body",
                "submenu" => [
                    [
                        "name" => _w("Sales"),
                        "url" => "{$wa_app_url}?action=reports"
                    ],
                    [
                        "name" => _w("Customers"),
                        "url" => "{$wa_app_url}?action=reports#/customers/"
                    ],
                    [
                        "name" => _w("Cohorts"),
                        "url" => "{$wa_app_url}?action=reports#/cohorts/"
                    ],
                    [
                        "name" => _w("Products"),
                        "url" => "{$wa_app_url}?action=reports#/products/"
                    ]
                ]
            ],
            "storefront" => [
                "id" => "storefront",
                "name" => _w("Storefront"),
                "icon" => '<i class="fas fa-store"></i>',
                "url" => "{$wa_app_url}?action=storefronts#/design/themes/",
                "userRights" => ['design', 'pages'],
                "placement" => "body",
                "submenu" => [
                    [
                        "name" => _w("Design"),
                        "url" => "{$wa_app_url}?action=storefronts#/design/themes/"
                    ],
                    [
                        "name" => _w("Pages"),
                        "url" => "{$wa_app_url}?action=storefronts#/design/pages/"
                    ]
                ]
            ],
            "plugins" => [
                "id" => "plugins",
                "name" => _w("Plugins"),
                "icon" => '<svg><use xlink:href="'.$wa_url.'wa-apps/shop/img/backend/products/product/icons.svg?v='.wa()->getVersion('shop').'#plugins"></use></svg>',
                "url" => "{$wa_app_url}?action=plugins#/",
                "userRights" => ['settings'],
                "placement" => "body",
            ],
            "import" => [
                "id" => "import",
                "name" => _w("Import / Export"),
                "icon" => '<i class="fas fa-exchange-alt"></i>',
                "url" => "{$wa_app_url}?action=importexport",
                "userRights" => ['importexport'],
                "placement" => "footer",
            ],
            "settings" => [
                "id" => "settings",
                "name" => _w("Settings"),
                "icon" => '<i class="fas fa-cog"></i>',
                "url" => "{$wa_app_url}?action=settings",
                "userRights" => ['settings'],
                "placement" => "footer",
            ],
        ];

        // This set of icons is used when Font Awesome is not available on the page (i.e. WA 1.3 design mode)
        if (!empty($options['inline_icons'])) {
            $result["orders"]["icon"] = '<svg viewBox="0 0 576 512"><path fill="currentColor" d="M567.938 243.908L462.25 85.374A48.003 48.003 0 0 0 422.311 64H153.689a48 48 0 0 0-39.938 21.374L8.062 243.908A47.994 47.994 0 0 0 0 270.533V400c0 26.51 21.49 48 48 48h480c26.51 0 48-21.49 48-48V270.533a47.994 47.994 0 0 0-8.062-26.625zM162.252 128h251.497l85.333 128H376l-32 64H232l-32-64H76.918l85.334-128z"></path></svg>';
            $result["customers"]["icon"] = '<svg viewBox="0 0 640 512"><path fill="currentColor" d="M192 256c61.9 0 112-50.1 112-112S253.9 32 192 32 80 82.1 80 144s50.1 112 112 112zm76.8 32h-8.3c-20.8 10-43.9 16-68.5 16s-47.6-6-68.5-16h-8.3C51.6 288 0 339.6 0 403.2V432c0 26.5 21.5 48 48 48h288c26.5 0 48-21.5 48-48v-28.8c0-63.6-51.6-115.2-115.2-115.2zM480 256c53 0 96-43 96-96s-43-96-96-96-96 43-96 96 43 96 96 96zm48 32h-3.8c-13.9 4.8-28.6 8-44.2 8s-30.3-3.2-44.2-8H432c-20.4 0-39.2 5.9-55.7 15.4 24.4 26.3 39.7 61.2 39.7 99.8v38.4c0 2.2-.5 4.3-.6 6.4H592c26.5 0 48-21.5 48-48 0-61.9-50.1-112-112-112z"></path></svg>';
            $result["catalog"]["icon"] = '<svg viewBox="0 0 640 512"><path fill="currentColor" d="M497.941 225.941L286.059 14.059A48 48 0 0 0 252.118 0H48C21.49 0 0 21.49 0 48v204.118a48 48 0 0 0 14.059 33.941l211.882 211.882c18.744 18.745 49.136 18.746 67.882 0l204.118-204.118c18.745-18.745 18.745-49.137 0-67.882zM112 160c-26.51 0-48-21.49-48-48s21.49-48 48-48 48 21.49 48 48-21.49 48-48 48zm513.941 133.823L421.823 497.941c-18.745 18.745-49.137 18.745-67.882 0l-.36-.36L527.64 323.522c16.999-16.999 26.36-39.6 26.36-63.64s-9.362-46.641-26.36-63.64L331.397 0h48.721a48 48 0 0 1 33.941 14.059l211.882 211.882c18.745 18.745 18.745 49.137 0 67.882z"></path></svg>';
            $result["marketing"]["icon"] = '<svg viewBox="0 0 576 512"><path fill="currentColor" d="M576 240c0-23.63-12.95-44.04-32-55.12V32.01C544 23.26 537.02 0 512 0c-7.12 0-14.19 2.38-19.98 7.02l-85.03 68.03C364.28 109.19 310.66 128 256 128H64c-35.35 0-64 28.65-64 64v96c0 35.35 28.65 64 64 64h33.7c-1.39 10.48-2.18 21.14-2.18 32 0 39.77 9.26 77.35 25.56 110.94 5.19 10.69 16.52 17.06 28.4 17.06h74.28c26.05 0 41.69-29.84 25.9-50.56-16.4-21.52-26.15-48.36-26.15-77.44 0-11.11 1.62-21.79 4.41-32H256c54.66 0 108.28 18.81 150.98 52.95l85.03 68.03a32.023 32.023 0 0 0 19.98 7.02c24.92 0 32-22.78 32-32V295.13C563.05 284.04 576 263.63 576 240zm-96 141.42l-33.05-26.44C392.95 311.78 325.12 288 256 288v-96c69.12 0 136.95-23.78 190.95-66.98L480 98.58v282.84z"></path></svg>';
            $result["reports"]["icon"] = '<svg viewBox="0 0 512 512"><path fill="currentColor" d="M332.8 320h38.4c6.4 0 12.8-6.4 12.8-12.8V172.8c0-6.4-6.4-12.8-12.8-12.8h-38.4c-6.4 0-12.8 6.4-12.8 12.8v134.4c0 6.4 6.4 12.8 12.8 12.8zm96 0h38.4c6.4 0 12.8-6.4 12.8-12.8V76.8c0-6.4-6.4-12.8-12.8-12.8h-38.4c-6.4 0-12.8 6.4-12.8 12.8v230.4c0 6.4 6.4 12.8 12.8 12.8zm-288 0h38.4c6.4 0 12.8-6.4 12.8-12.8v-70.4c0-6.4-6.4-12.8-12.8-12.8h-38.4c-6.4 0-12.8 6.4-12.8 12.8v70.4c0 6.4 6.4 12.8 12.8 12.8zm96 0h38.4c6.4 0 12.8-6.4 12.8-12.8V108.8c0-6.4-6.4-12.8-12.8-12.8h-38.4c-6.4 0-12.8 6.4-12.8 12.8v198.4c0 6.4 6.4 12.8 12.8 12.8zM496 384H64V80c0-8.84-7.16-16-16-16H16C7.16 64 0 71.16 0 80v336c0 17.67 14.33 32 32 32h464c8.84 0 16-7.16 16-16v-32c0-8.84-7.16-16-16-16z"></path></svg>';
            $result["storefront"]["icon"] = '<svg viewBox="0 0 616 512"><path fill="currentColor" d="M602 118.6L537.1 15C531.3 5.7 521 0 510 0H106C95 0 84.7 5.7 78.9 15L14 118.6c-33.5 53.5-3.8 127.9 58.8 136.4 4.5.6 9.1.9 13.7.9 29.6 0 55.8-13 73.8-33.1 18 20.1 44.3 33.1 73.8 33.1 29.6 0 55.8-13 73.8-33.1 18 20.1 44.3 33.1 73.8 33.1 29.6 0 55.8-13 73.8-33.1 18.1 20.1 44.3 33.1 73.8 33.1 4.7 0 9.2-.3 13.7-.9 62.8-8.4 92.6-82.8 59-136.4zM529.5 288c-10 0-19.9-1.5-29.5-3.8V384H116v-99.8c-9.6 2.2-19.5 3.8-29.5 3.8-6 0-12.1-.4-18-1.2-5.6-.8-11.1-2.1-16.4-3.6V480c0 17.7 14.3 32 32 32h448c17.7 0 32-14.3 32-32V283.2c-5.4 1.6-10.8 2.9-16.4 3.6-6.1.8-12.1 1.2-18.2 1.2z"></path></svg>';
            $result["import"]["icon"] = '<svg viewBox="0 0 512 512"><path fill="currentColor" d="M0 168v-16c0-13.255 10.745-24 24-24h360V80c0-21.367 25.899-32.042 40.971-16.971l80 80c9.372 9.373 9.372 24.569 0 33.941l-80 80C409.956 271.982 384 261.456 384 240v-48H24c-13.255 0-24-10.745-24-24zm488 152H128v-48c0-21.314-25.862-32.08-40.971-16.971l-80 80c-9.372 9.373-9.372 24.569 0 33.941l80 80C102.057 463.997 128 453.437 128 432v-48h360c13.255 0 24-10.745 24-24v-16c0-13.255-10.745-24-24-24z"></path></svg>';
            $result["settings"]["icon"] = '<svg viewBox="0 0 512 512"><path fill="currentColor" d="M444.788 291.1l42.616 24.599c4.867 2.809 7.126 8.618 5.459 13.985-11.07 35.642-29.97 67.842-54.689 94.586a12.016 12.016 0 0 1-14.832 2.254l-42.584-24.595a191.577 191.577 0 0 1-60.759 35.13v49.182a12.01 12.01 0 0 1-9.377 11.718c-34.956 7.85-72.499 8.256-109.219.007-5.49-1.233-9.403-6.096-9.403-11.723v-49.184a191.555 191.555 0 0 1-60.759-35.13l-42.584 24.595a12.016 12.016 0 0 1-14.832-2.254c-24.718-26.744-43.619-58.944-54.689-94.586-1.667-5.366.592-11.175 5.459-13.985L67.212 291.1a193.48 193.48 0 0 1 0-70.199l-42.616-24.599c-4.867-2.809-7.126-8.618-5.459-13.985 11.07-35.642 29.97-67.842 54.689-94.586a12.016 12.016 0 0 1 14.832-2.254l42.584 24.595a191.577 191.577 0 0 1 60.759-35.13V25.759a12.01 12.01 0 0 1 9.377-11.718c34.956-7.85 72.499-8.256 109.219-.007 5.49 1.233 9.403 6.096 9.403 11.723v49.184a191.555 191.555 0 0 1 60.759 35.13l42.584-24.595a12.016 12.016 0 0 1 14.832 2.254c24.718 26.744 43.619 58.944 54.689 94.586 1.667 5.366-.592 11.175-5.459 13.985L444.788 220.9a193.485 193.485 0 0 1 0 70.2zM336 256c0-44.112-35.888-80-80-80s-80 35.888-80 80 35.888 80 80 80 80-35.888 80-80z"></path></svg>';
        }

        self::backendExtendedMenuEvent($result, $options);

        return array_values($result);
    }

    /**
     * Helper for plugins to create 1-st level sections in main menu.
     * If section with this id already exists, will return existing record.
     *
     * Possible additional $options:
     * - icon: font awesome acon for section, e.g. '<i class="fas fa-cog"></i>'
     * - insert_before: new section will be inserted before given section id.
     * - insert_after: new section will be inserted after given section id.
     *   When neither insert_before or insert_after is specified, new section is added at the end.
     * - placement: can be 'body' or 'footer'. Defaults to same as section (if specified)
     *   insert_before or insert_after, or 'body' otherwise.
     * - url: new section will open given page. Only supported by sections with no submenu.
     * - submenu: list of submenu links, each being array ['url' => string, 'name' => string]
     *
     * @param array|null &$menu to add section to; during backend_extended_menu event this should be $params['menu']
     * @param string $section_id new section id
     * @param string $title human readable section name
     * @param array $options optional additional parameters
     * @return array  section just created
     * @since 10.0.2
     */
    public static function createSection(&$menu, $section_id, $title, $options=[])
    {
        if ($menu !== null && isset($menu[$section_id])) {
            return $menu[$section_id];
        }

        $section = [
            'id' => $section_id,
            'name' => $title,
        ] + $options + [
            'icon' => '<i class="fas fa-solid fa-cogs"></i>',
        ];
        unset($section['insert_before'], $section['insert_after']);

        if (empty($section['url'])) {
            $section['submenu'] = [];
        } else if (!empty($section['submenu'])) {
            unset($section['url']);
        } else if ($section['url'][0] == '?') {
            $section['url'] = wa('shop')->getAppUrl(null, true).$section['url'];
        }

        if (!isset($section['placement'])) {
            if (isset($options['insert_before']) && isset($menu[$options['insert_before']]['placement'])) {
                $section['placement'] = $menu[$options['insert_before']]['placement'];
            } else if (isset($options['insert_after']) && isset($menu[$options['insert_after']]['placement'])) {
                $section['placement'] = $menu[$options['insert_after']]['placement'];
            }
            if (!isset($section['placement'])) {
                $section['placement'] = 'body';
            }
        }

        $offset = false;
        if (isset($options['insert_before'])) {
            $offset = array_search($options['insert_before'], array_keys($menu));
        } else if (isset($options['insert_after'])) {
            $offset = array_search($options['insert_after'], array_keys($menu));
            if ($offset !== false) {
                $offset += 1;
            }
        }

        if ($offset === false) {
            $menu[$section_id] = $section;
        } else {
            $menu = array_merge(
                array_slice($menu, 0, $offset),
                [$section_id => $section],
                array_slice($menu, $offset, null)
            );
        }

        return $menu[$section_id];
    }

    /**
     * Helper for plugins to create 2-nd level subsections in main menu.
     *
     * @param array|null &$menu to add section to; during backend_extended_menu event this should be $params['menu']
     * @param string $parent_id section to add new link to
     * @param string $title human readable link name
     * @param string $url link to open
     * @param array $options additional parameters (not used; reserved for future use)
     * @return array subsection just created
     * @throws waException when parent section $parent_id does not exist in (non-null) $menu
     * @since 10.0.2
     */
    public static function createSubsection(&$menu, $parent_id, $title, $url, $options=[])
    {
        if ($menu !== null && !isset($menu[$parent_id])) {
            throw new waException('Section with id='.$parent_id.' does not exist.');
        }

        if ($url && $url[0] == '?') {
            $url = wa('shop')->getAppUrl(null, true).$url;
        }

        $subsection = [
            "url" => $url,
            "name" => $title,
        ] + $options;
        $menu[$parent_id]['submenu'][] = $subsection;
        return $subsection;
    }

    // not used since 10.2
    protected static function getPluginsSubmenu()
    {
        $result = [];

        $wa_app_url = wa('shop')->getAppUrl(null, true);
        $installer_url = wa()->getConfig()->getBackendUrl(true).'installer/';

        $result[] = [
            "name" => _w("Browse plugins"),
            "url" => "{$wa_app_url}?module=plugins&page=home",
        ];

        // Online marketplaces
        if (wa()->getLocale() == 'ru_RU') {
            $result[] = [
                "name" => _w("Marketplaces"),
                "url" => "{$wa_app_url}?module=plugins&page=marketplaces",
            ];
            $result[] = [
                "name" => _w("Онлайн-кассы"),
                "url" => "{$wa_app_url}?module=plugins&page=onlinecash",
            ];
        }

        $result[] = [
            "name" => _w("Installed"),
            "url" => "{$wa_app_url}?module=plugins#installed/"
        ];

        return $result;
    }

    protected static function backendExtendedMenuEvent(&$menu, $options)
    {
        $no_submenu = [];
        foreach($menu as $id => $item) {
            if (!isset($item['submenu'])) {
                $no_submenu[$id] = true;
            }
        }

        /**
         * @event backend_extended_menu
         * @since 9.4.1
         */
        wa('shop')->event('backend_extended_menu', ref([
            'options' => $options,
            'menu' => &$menu,
        ]));

        // make sure plugins did not add submenu items where not allowed
        foreach($no_submenu as $id => $_) {
            unset($menu[$id]['submenu']);
        }

        // Make sure all menu items have ids
        foreach($menu as $id => &$item) {
            if (!isset($item['id'])) {
                $item['id'] = $id;
            }
        }
        unset($item);
    }
}
