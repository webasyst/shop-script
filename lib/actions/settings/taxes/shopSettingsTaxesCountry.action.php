<?php

/**
 * Used to load empty country blocks (several <tr> each) for tax settings page.
 */
class shopSettingsTaxesCountryAction extends waViewAction
{
    public function execute()
    {
        $country_iso3 = waRequest::request('country');
        if (!$country_iso3) {
            throw new waException('Country not specified.', 404);
        }

        $regions = array();
        switch ($country_iso3) {
            case '%AL':
                $country = array('name' => _w('All countries'));
                break;
            case '%EU':
                $country = array('name' => _w('All European countries'));
                break;
            case '%RW':
                $country = array('name' => _w('Rest of world'));
                break;
            default:
                // Country
                $cm = new waCountryModel();
                $country = $cm->get($country_iso3);
                if (!$country) {
                    throw new waException('Country not found.', 404);
                }

                // Country regions
                $rm = new waRegionModel();
                foreach($rm->getByCountry($country_iso3) as $r) {
                    $r['css_class'] = 'highlighted just-added hidden';
                    $r['tax_name'] = '';
                    $r['tax_value'] = '';
                    $r['params'] = array(
                        'tax_value_modifier' => '+',
                    );
                    $regions[] = $r;
                }
                break;
        }

        $this->view->assign('c', array(
            'css_class' => 'highlighted just-added one_rate',
            'name' => $country['name'],
            'iso3' => $country_iso3,
            'regions' => $regions,
            'global_rate' => '',
        ));
    }
}

