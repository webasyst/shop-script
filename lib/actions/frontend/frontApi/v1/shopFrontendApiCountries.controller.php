<?php

class shopFrontendApiCountriesController extends shopFrontApiJsonController
{
    public function get($token = null)
    {
        $with_regions = waRequest::request('regions', 0, waRequest::TYPE_INT);
        $country_iso = waRequest::request('country', null, waRequest::TYPE_STRING_TRIM);

        $country_model = new waCountryModel();
        $region_model = new waRegionModel();
        if ($country_iso) {
            $countries[] = $country_model->get($country_iso);
            if ($with_regions) {
                $regions = $region_model->getByCountryWithFav($country_iso);
            }
        } else {
            $countries = $country_model->allWithFav();
            if ($with_regions) {
                $regions = $region_model->getAll();
            }
        }

        if ($with_regions) {
            foreach ($countries as &$_country) {
                foreach ($regions as $region) {
                    if ($_country['iso3letter'] == $region['country_iso3']) {
                        $_country['regions'][] = $region;
                    }
                }
            }
            unset($_country);
        }

        $this->response = $countries;
    }
}
