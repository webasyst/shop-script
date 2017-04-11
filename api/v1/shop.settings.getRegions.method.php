<?php

class shopSettingsGetRegionsMethod extends shopApiMethod
{
    protected $courier_allowed = true;
    public function execute()
    {
        $country = waRequest::get('country', null, 'string');

        $rm = new waRegionModel();

        if ($country) {
            // Load regions for a single country
            $this->response = array(
                $country => $rm->getByCountryWithFav($country),
            );
        } else {
            // Load regions for all countries at once
            $this->response = array();
            foreach($rm->getAll() as $r) {
                $this->response[$r['country_iso3']][] = $r;
            }
            foreach($this->response as $iso => $regions) {
                $this->response[$iso] = $rm->getByCountryWithFav($regions);
            }
        }

        // Format response
        foreach($this->response as $iso => $regions) {
            foreach($regions as $i => $r) {
                $this->response[$iso][$i] = array(
                    'code' => $r['code'],
                    'name' => $r['name'],
                );
            }
        }
    }
}
