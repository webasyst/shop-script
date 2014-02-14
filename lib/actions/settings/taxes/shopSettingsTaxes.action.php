<?php

/**
 * Tax settings form, and save, and delete contoller.
 */
class shopSettingsTaxesAction extends waViewAction
{
    /** @var shopTaxModel */
    protected $tm = null;
    /** @var shopTaxRegionsModel */
    protected $trm = null;
    /** @var shopTaxZipCodesModel */
    protected $tzcm = null;

    public function execute()
    {
        $this->tm = $tm = new shopTaxModel();
        $taxes = $tm->getAll('id');

        $tax_id = waRequest::request('id');
        if (!$tax_id) {
            $tax_id = $taxes ? key($taxes) : 'new';
        }
        if (!empty($taxes[$tax_id])) {
            $tax = $taxes[$tax_id];
        } else if ($tax_id == 'new') {
            $tax = $tm->getEmptyRow();
            $tax_id = null;
        } else {
            throw new waException('Tax record not found.', 404);
        }

        $this->trm = $trm = new shopTaxRegionsModel();
        $this->tzcm = $tzcm = new shopTaxZipCodesModel();

        $countries = $this->getCountryList();
        $tax = $this->processPostData($tax);
        if ($tax['id'] && !$tax_id) {
            $tax_id = $tax['id'];
        }
        if ($tax_id) {
            $taxes[$tax_id] = $tax;
        }
        uasort($taxes, wa_lambda('$a,$b', 'return strcmp($a["name"], $b["name"]);'));

        $this->view->assign('tax_countries', $this->getTaxCountries($tax, $countries));
        $this->view->assign('tax_zip_codes', $this->getTaxZipCodes($tax));
        $this->view->assign('countries', $countries);
        $this->view->assign('taxes', $taxes);
        $this->view->assign('tax', $tax);
        
        $checkout_settings = $this->getConfig()->getCheckoutSettings();
        $this->view->assign('billing_address_required', isset($checkout_settings['contactinfo']['fields']['address.billing']));
    }

    protected function getTaxCountries($tax, $countries)
    {
        $tax_countries = array();

        // Collect data for all countries that have tax rates set
        foreach($this->trm->getByTax($tax['id']) as $r) {

            // Init country
            if (!isset($tax_countries[$r['country_iso3']])) {
                switch ($r['country_iso3']) {
                    case '%AL':
                        $c = array('name' => _w('All countries'));
                        break;
                    case '%EU':
                        $c = array('name' => _w('All European countries'));
                        break;
                    case '%RW':
                        $c = array('name' => _w('Rest of world'));
                        break;
                    default:
                        $c = ifset($countries[$r['country_iso3']], array(
                            'name' => 'Unknown country: '.$r['country_iso3'],
                        ));
                        break;
                }
                $tax_countries[$r['country_iso3']] = array(
                    'css_class' => null, // set later
                    'iso3' => $r['country_iso3'],
                    'name' => $c['name'],
                    'regions_data' => array(), // unset later
                    'global_rate' => null, // set later
                    'regions' => array(), // filled in later
                );
            }

            // Remember regions data to process later
            if ($r['region_code'] === null) {
                $tax_countries[$r['country_iso3']]['global_rate'] = (float) str_replace(',', '.', $r['tax_value']);
            } else {
                $tax_countries[$r['country_iso3']]['regions_data'][$r['region_code']] = $r;
            }
        }

        // Init regions
        $rm = new waRegionModel();
        foreach($rm->getByCountry(array_keys($tax_countries)) as $r) {
            $c =& $tax_countries[$r['country_iso3']];
            if (!$c['regions_data']) {
                $r['css_class'] = 'hidden';
            } else if ($c['global_rate'] === null) {
                $r['css_class'] = 'regions_simple';
            } else {
                $r['css_class'] = 'regions_advanced';
            }

            if (empty($c['regions_data'][$r['code']])) {
                $r['tax_name'] = '';
                $r['tax_value'] = '';
                $r['params'] = array(
                    'tax_value_modifier' => '+',
                );
            } else {
                $r = $c['regions_data'][$r['code']] + $r;
                $r['params'] = $r['params'] ? unserialize($r['params']) : array(
                    'tax_value_modifier' => '',
                );
                $r['tax_value'] = (float) str_replace(',', '.', $r['tax_value']);
            }

            $c['regions'][] = $r;
        }
        unset($c);

        // Cleanup
        foreach($tax_countries as &$c) {
            if (!$c['regions_data']) {
                $c['css_class'] = 'one_rate';
            } else if ($c['global_rate'] === null) {
                $c['css_class'] = 'regions_simple';
            } else {
                $c['css_class'] = 'regions_advanced';
            }
            unset($c['regions_data']);
        }
        unset($c);

        // Sort countries by name
        uasort($tax_countries, array($this, 'sortHelper'));

        return $tax_countries;
    }

    public function sortHelper($a, $b)
    {
        if ($a['iso3']{0} === '%' && $b['iso3']{0} !== '%') {
            return 1;
        }
        if ($a['iso3']{0} !== '%' && $b['iso3']{0} === '%') {
            return -1;
        }
        return strcmp($a['name'], $b['name']);
    }

    protected function getTaxZipCodes($tax)
    {
        $result = $this->tzcm->getByTax($tax['id']);
        if (!$result) {
            $result = array();
        }
        foreach($result as &$row) {
            $row['zip_expr'] = str_replace('%', '*', $row['zip_expr']);
            $row['tax_value'] = (float) str_replace(',', '.', $row['tax_value']);
        }
        unset($row);
        return $result;
    }

    protected function getCountryList()
    {
        $cm = new waCountryModel();
        return $cm->all();
    }

    protected function processPostData($old_tax)
    {
        if (!waRequest::post()) {
            return $old_tax;
        }

        $tm = $this->tm;
        if (waRequest::post('delete')) {
            if ($old_tax['id']) {
                $tm->deleteById($old_tax['id']);
                $this->trm->deleteByField('tax_id', $old_tax['id']);
                $this->tzcm->deleteByField('tax_id', $old_tax['id']);
            }
            echo json_encode(array('status' => 'ok', 'data' => 'ok'));
            exit;
        }

        //
        // Prepare data for shopTaxes::save()
        //
        $tax_data = waRequest::post('tax');
        if (!is_array($tax_data)) {
            return $old_tax;
        }
        if (!empty($old_tax['id'])) {
            $tax_data['id'] = $old_tax['id'];
        }

        // countries
        $tax_data['countries'] = array();
        $tax_countries = waRequest::post('countries'); // country global rate: iso3 => float
        if ($tax_countries && is_array($tax_countries)) {
            $tax_country_regions = waRequest::post('country_regions'); // rates by region: iso3 => region_code => float
            if (!is_array($tax_country_regions)) {
                $tax_country_regions = array();
            }

            foreach($tax_countries as $country_iso3 => $country_global_rate) {
                $tax_data['countries'][$country_iso3] = array(
                    'global_rate' => $country_global_rate,
                );
                if (!empty($tax_country_regions[$country_iso3]) && is_array($tax_country_regions[$country_iso3])) {
                    $tax_data['countries'][$country_iso3]['regions'] = $tax_country_regions[$country_iso3];
                }
            }
        }

        // zip codes
        $tax_data['zip_codes'] = array();
        $zip_codes = waRequest::post('tax_zip_codes');
        $zip_rates = waRequest::post('tax_zip_rates');
        if (is_array($zip_codes) && is_array($zip_rates)) {
            foreach($zip_codes as $i => $code) {
                if ($code) {
                    $tax_data['zip_codes'][$code] = ifset($zip_rates[$i], 0);
                }
            }
        }

        return shopTaxes::save($tax_data);
    }
}

