<?php

/**
 * Second checkout step. Determine shipping region: country + region + city.
 * May also ask for zip code if set up in settings.
 */
class shopCheckoutRegionStep extends shopCheckoutStep
{
    public function prepare($data)
    {
        // Read shop checkout settings
        $cfg = $this->getCheckoutConfig();

        // Is shipping step disabled altogether?
        if (empty($cfg['shipping']['used'])) {
            $result = $this->addRenderedHtml([
                'disabled' => true,
            ], $data, []);
            return array(
                'result'       => $result,
                'errors'       => [],
                'can_continue' => true,
            );
        }

        // List of pre-defined locations
        if (ifempty($cfg, 'shipping', 'mode', shopCheckoutConfig::SHIPPING_MODE_TYPE_DEFAULT) == shopCheckoutConfig::SHIPPING_MODE_TYPE_DEFAULT) {
            // Default mode is like fixed mode with a single location
            // for which we hide the selector.
            $cfg_locations = [
                [
                    'name'    => '',
                    'enabled' => true,
                ] + ifempty($cfg, 'shipping', 'fixed_delivery_area', [])
            ];
        } else {
            $cfg_locations = ifempty($cfg, 'shipping', 'locations_list', []);
        }

        // Load countries.
        $country_model = new waCountryModel();
        $countries = [];
        $countries_by_id = [];
        foreach ($country_model->allWithFav() as $c) {
            $c = [
                'id'          => $c['iso3letter'],
                'name'        => $c['name'],
                'fav'         => $c['fav_sort'],
                'has_regions' => null,
                'regions'     => [],
            ];
            $countries[] =& $c;
            if (!isset($countries_by_id[$c['id']])) {
                $countries_by_id[$c['id']] =& $c;
            } else {
                $c['is_copy'] = true;
                unset($c['regions'], $c['has_regions']);
            }
            unset($c);
        }

        // Filter out disabled locations and figure out default selected one
        $default_location_id = null;
        foreach ($cfg_locations as $loc_id => $cfg_loc) {

            // Some locations are disabled by admin too lazy to remove them
            if (empty($cfg_loc['enabled'])) {
                unset($cfg_locations[$loc_id]);
                continue;
            }

            // Make sure country exists, if specified
            $country_id = ifset($cfg_loc, 'country', null);
            if ($country_id) {
                if (!isset($countries_by_id[$country_id])) {
                    continue;
                }
                // Remember fixed region to fetch its name from DB later
                $region_id = ifset($cfg_loc, 'region', null);
                if ($region_id) {
                    $countries_by_id[$country_id]['regions'][] = [
                        'id'         => $region_id,
                        'name'       => $region_id,
                        'has_cities' => false,
                    ];
                }
            }

            // Default selected location: either first one or one marked as default
            if ($default_location_id === null || !empty($cfg_loc['default'])) {
                $default_location_id = $loc_id;
            }
        }

        // If there are no locations, force default mode
        // (shop is misconfigured; should never happen)
        if (!$cfg_locations) {
            $default_location_id = null;
            $cfg_locations = [
                [
                    'name'    => '',
                    'enabled' => true,
                    'country' => null,
                    'region'  => null,
                    'city'    => null,
                ]
            ];
        }

        // Get address from contact (prefer shipping)
        /** @var waContact $contact */
        $contact = $data['contact'];
        $address_from_contact = $contact->get('address.shipping', 'js');
        if (!$address_from_contact) {
            $address_from_contact = $contact->get('address', 'js');
        }
        if (isset($address_from_contact[0])) {
            $address_from_contact = $address_from_contact[0];
        }
        $address_from_contact = ifset($address_from_contact, 'data', []);

        // Values in form are either from POST or customer address in DB. Load both.
        $we_have_input = !empty($data['input']['region']) && is_array($data['input']['region']);
        if ($we_have_input) {
            $address_from_input = ifset($data, 'input', 'region', []);
            $location_id = ifempty($data['input']['region'], 'location_id', $default_location_id);
        } else {
            $location_id = $default_location_id;
            $address_from_input = [];
        }

        $contact_id = ifset($data, 'contact', 'id', null);
        $user_id_from_input = ifset($data, 'input', 'auth', 'user_id', '');
        if ($contact_id && $user_id_from_input != $contact_id) {
            // User just logged in: combine input and address from contact
            $address = array_filter($address_from_input) + $address_from_contact;
        } elseif ($we_have_input) {
            // It's a POST. Use address from input
            $address = $address_from_input;
        } else {
            // It's initial page load. Use address from contact.
            $address = $address_from_contact;
        }

        // Figure out what values to show in form fields
        $selected_values = $this->getSelectedValues($cfg, $cfg_locations, $location_id, $default_location_id, $address);

        if (empty($selected_values['country_id'])) {
            $selected_values['country_id'] = $this->getDefaultCountryID($countries_by_id);
        }

        // For selected country load all regions.
        // For countries used in locations load names of fixed regions.
        // Change region to region_id in $selected_values if selected country has regions
        $region_model = new waRegionModel();
        foreach ($countries_by_id as &$c) {

            // Load all regions of currently selected country
            $load_all = $c['id'] == $selected_values['country_id'];

            // We don't have to load all regions of currently selected country,
            // when a location is selected, it fixes country, and all other
            // locations of that country also have region fixed.
            if ($load_all && null !== $selected_values['location_id']) {                    // ...a location is selected...
                if (null !== $cfg_locations[$selected_values['location_id']]['country']) {  // ...it fixes country...
                    $load_all = false;
                    foreach ($cfg_locations as $loc_id => $cfg_loc) {                        // ...and all locations...
                        if ($cfg_loc['country'] == $selected_values['country_id']) {        // ...of that country...
                            if ($cfg_loc['region'] === null) {                              // ...have region fixed.
                                $load_all = true;
                                break;
                            }
                        }
                    }
                }
            }

            if ($load_all) {
                // Load all regions for currently selected country
                $raw_regions = $region_model->getByCountryWithFav($c['id']);
                $c['has_regions'] = !!$raw_regions;
            } elseif ($c['regions']) {
                // Load fixed regions for not-currently-selected country
                $raw_regions = $region_model->getByField([
                    'code'         => array_column($c['regions'], 'id'),
                    'country_iso3' => $c['id'],
                ], true);
                $c['has_regions'] = !!$raw_regions;
            } else {
                $raw_regions = [];
                $c['has_regions'] = null; // means we dont know
            }

            $c['regions'] = array_map(function ($r) {
                return [
                    'id'            => $r['code'],
                    'name'          => $r['name'],
                    'fav'           => $r['fav_sort'],
                    'region_center' => $r['region_center'],
                    'has_cities'    => false,
                ];
            }, $raw_regions);

            // If selected country has regions, it means value from user is not a name but an id
            if ($c['id'] == $selected_values['country_id'] && $c['has_regions']) {
                $selected_values['region_id'] = $selected_values['region'];
                $selected_values['region'] = null;
            }
        }
        unset($c);

        // Build a list of locations for template/JSON.
        $locations = array();
        foreach ($cfg_locations as $loc_id => $cfg_loc) {

            $country_id = ifset($cfg_loc, 'country', null);
            $region_id = ifset($cfg_loc, 'region', null);
            $city_id = ifset($cfg_loc, 'city', null);

            $loc = array(
                'id'         => $loc_id,
                'name'       => ifset($cfg_loc, 'name', ''),
                'country_id' => $country_id,

                'region_id' => null, // see below
                'region'    => null,

                'city_id' => null, // lists of cities are not implemented (note $selected_values and has_cities flag, too)
                'city'    => $city_id,

                'country_locked' => $country_id !== null,
                'region_locked'  => $region_id !== null,
                'city_locked'    => $city_id !== null,
            );

            if ($country_id !== null) {
                if ($countries_by_id[$country_id]['has_regions']) {
                    $loc['region_id'] = $region_id;
                } else {
                    $loc['region'] = $region_id;
                }
            }

            $locations[] = $loc;
        }

        // Validation
        $errors = [];
        if (empty($selected_values['country_id'])) {
            $errors[] = [
                'name'    => 'region[country]',
                'text'    => _w('This field is required.'),
                'section' => $this->getId(),
            ];
        } elseif (empty($countries_by_id[$selected_values['country_id']])) {
            $errors[] = [
                'name'    => 'region[country]',
                'text'    => _w('This field is required.'),
                'section' => $this->getId(),
            ];
        }
        if (empty($errors)) {
            if (empty($selected_values['region']) && empty($selected_values['region_id'])) {
                $errors[] = [
                    'name'    => 'region[region]',
                    'text'    => _w('This field is required.'),
                    'section' => $this->getId(),
                ];
            } else {
                // Make sure region exists if country has them
                $c = ifset($countries_by_id, $selected_values['country_id'], null);
                if ($c['has_regions']) {
                    $selected_region = null;
                    foreach ($c['regions'] as $r) {
                        if ($r['id'] == $selected_values['region_id']) {
                            $selected_region = $r;
                        }
                    }
                    if ($selected_region) {
                        // Attempt to select city automatically if set in region settings
                        if (empty($selected_values['city']) && !empty($selected_region['region_center'])) {
                            $selected_values['city'] = $selected_region['region_center'];
                        }
                    } else {
                        $errors[] = [
                            'name'    => 'region[region]',
                            'text'    => _w('This field is required.'),
                            'section' => $this->getId(),
                        ];
                    }
                }
            }
        }
        if (empty($errors) && empty($selected_values['city'])) {
            $errors[] = [
                'name'    => 'region[city]',
                'text'    => _w('This field is required.'),
                'section' => $this->getId(),
            ];
        }
        if (!empty($cfg['shipping']['ask_zip']) && empty($selected_values['zip'])) {
            $errors[] = [
                'name'    => 'region[zip]',
                'text'    => _w('This field is required.'),
                'section' => $this->getId(),
            ];
        }

        $result = $this->addRenderedHtml([
            'selected_values' => $selected_values,
            'locations'       => array_values($locations),
            'countries'       => array_values($countries),
        ], $data, $errors);

        return array(
            'result'       => $result,
            'errors'       => $errors,
            'can_continue' => !$errors,
        );
    }

    /*

    Variables passed to template by prepare()

    countries: [
      {
        id: 'rus',
        name: 'Россия',

        // Country may or may not have list of regions.
        // Selected country have all regions listed, unless region is fixed.
        // Non-selected country have SOME regions listed (i.e. regions used in locations).
        regions: [
          {
            id: '77',
            name: 'Москва',
            // May or may not have list of cities
            cities: [
              {
                id: 'Москва',
                name: 'Москва'
              }
            ]
          },
        ],
      }
    ],

    locations: [
        {
          id: id, // location id
          country_id: id|null,

          // Can be specified an id from a list of regions in country
          // OR user-supplied string in case region does not have a list of cities
          region_id: id|null,
          region: string|null,

          // Can be specified an id from a list of cities in region,
          // OR user-supplied string in case region does not have a list of cities
          city_id: id|null,
          city: string|null,

          country_locked: true|false,
          region_locked: true|false,
          city_locked: true|false
        }
    ],

    // Currently selected by user
    selected_values: {
      location_id: id|null,
      country_id: id|null,
      // one of them will be set depending on whether country has list of regions
      // Both may be null if user did not select anything
      region_id: id|null,
      region: string|null,
      // one of them will be set depending on whether region has list of cities
      // Both may be null if user did not select anything
      city_id: id|null,
      city: string|null,
      // undefined will not show ZIP field
      zip: string|null|undefined
    }

    */

    /**
     * @param $countries
     * @return string
     */
    protected function getDefaultCountryID($countries)
    {
        /**
         * @var shopConfig $shop_config
         */
        $shop_config = wa('shop')->getConfig();
        $default_country = $shop_config->getGeneralSettings('country');

        $result = '';
        if (isset($countries[$default_country])) {
            $result = $default_country;
        }

        return $result;
    }

    protected function getSelectedValues($cfg, $cfg_locations, $location_id, $default_location_id, $address)
    {
        // Is location properly selected?
        if (empty($cfg_locations[$location_id])) {
            $location_id = $default_location_id;
            if (empty($cfg_locations[$location_id])) {
                $location_id = key($cfg_locations);
            }
        }

        $address = ((array)$address) + [
                'country' => '',
                'region'  => '',
                'city'    => '',
                'zip'     => '',
            ];

        // At this point we don't know whether country has regions
        // or region has cities, so we assume it's a full name, not id.

        $selected_values = [
            'location_id' => $location_id,
            'country_id'  => trim($address['country']),
            'region_id'   => null,
            'region'      => trim($address['region']),
            'city_id'     => null,
            'city'        => trim($address['city']),
        ];
        if (!empty($cfg['shipping']['ask_zip'])) {
            $selected_values['zip'] = trim($address['zip']);
        }

        // Replace values fixed in selected location
        $location = $cfg_locations[$location_id];

        if (!empty($location['country'])) {
            // Do not apply region to another country it does not belong to
            if ($selected_values['country_id'] && $selected_values['country_id'] != $location['country']) {
                $selected_values['region'] = null;
                $selected_values['city'] = null;
            }
            // Force country fixed in location
            $selected_values['country_id'] = $location['country'];
        }
        if (!empty($location['region'])) {
            // Do not apply city to another region it does not belong to
            if ($selected_values['region'] && $selected_values['region'] != $location['region']) {
                $selected_values['city'] = null;
            }
            // Force region fixed in location
            $selected_values['region'] = $location['region'];
        }
        if (!empty($location['city'])) {
            // Force city fixed in location
            $selected_values['city'] = $location['city'];
        }

        return $selected_values;
    }

    public function getTemplatePath()
    {
        return 'region.html';
    }
}
