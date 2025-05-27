<?php

class shopAiApiRequest
{
    const FIELDS_CACHE_TTL = 36000; // 10 hours

    public $facility = null;
    public $fields = null;
    public $sections = null;
    public $values = [];

    /**
     * @throws waException
     */
    public function loadFieldsFromApi(string $facility): shopAiApiRequest
    {
        $this->facility = $facility;
        $cache = new waVarExportCache('ai_fields_'.$facility.'_'.wa()->getLocale(), self::FIELDS_CACHE_TTL, 'shop');
        $api_call = $cache->get();
        if (!$api_call) {
            $api = new waServicesApi();
            if (!$api->isConnected()) {
                return $this;
            }
            $api_call = $api->serviceCall('AI_OVERVIEW', [
                'locale' => wa()->getLocale(),
                'facility' => $facility,
            ]);
            if (empty($api_call['response']['fields']) || empty($api_call['response']['sections'])) {
                throw new waException('Unexpected response from WAID API');
            }
            $cache->set($api_call);
        }

        $this->sections = $api_call['response']['sections'];
        $this->fields = [];
        foreach($api_call['response']['fields'] as $f) {
            $this->fields[$f['id']] = $f;
        }

        return $this;
    }

    public function generate()
    {
        if (!$this->facility) {
            throw new waException('loadFieldsFromApi() must be called before generate()');
        }
        $api = new waServicesApi();
        if (!$api->isConnected()) {
            throw new waException('WAID is not connected');
        }

        $request_data = $this->values;
        $request_data['facility'] = $this->facility;
        $api_call = $api->serviceCall('AI', $request_data, 'POST');
        if (empty($api_call['response'])) {
            return [
                'error' => 'unable_to_connect',
                'error_description' => _w('Service temporarily unavailable. Please try again later.'),
            ];
        }

        return $api_call['response'];
    }

    public function getFieldsWithValues(): array
    {
        if (!$this->fields) {
            return [];
        }

        $all_fields = [];
        foreach ($this->fields as $f) {
            $f['value'] = ifset($this->values, $f['id'], '');
            $all_fields[$f['id']] = $f;
        }
        return $all_fields;
    }

    public function getSectionsWithFields(): array
    {
        if (!$this->sections) {
            return [];
        }

        $all_fields = $this->getFieldsWithValues();

        $result = [];
        foreach ($this->sections as $s) {
            $section = [
                'title' => $s['title'],
                'fields' => [],
            ];
            foreach ($s['fields'] as $f_id) {
                if (isset($all_fields[$f_id])) {
                    $section['fields'][] = $all_fields[$f_id];
                }
            }
            if ($section['fields']) {
                $result[] = $section;
            }
        }
        return $result;
    }

    public function setFieldValues(array $values): shopAiApiRequest
    {
        foreach ($values as $k => $v) {
            $this->setFieldValue($k, $v);
        }
        return $this;
    }

    public function setFieldValue($field_id, $value): shopAiApiRequest
    {
        $this->values[$field_id] = $value;
        return $this;
    }

    public static function fieldValuesSavedInSettings(): bool
    {
        $app_settings_model = new waAppSettingsModel();
        return !!$app_settings_model->get('shop', 'ai_fields');
    }

    public function loadFieldValuesFromSettings(): shopAiApiRequest
    {
        if (isset($this->fields['locale'])) {
            $this->values['locale'] = wa()->getLocale();
        }

        // Non-premium users are not allowed to benefit from custom AI settings
        if (shopLicensing::isPremium()) {
            $app_settings_model = new waAppSettingsModel();
            $values = $app_settings_model->get('shop', 'ai_fields');
            if ($values) {
                $this->setFieldValues(json_decode($values, true));
            }
        }

        return $this;
    }

    public function saveFieldValuesToSettings(): shopAiApiRequest
    {
        $values = $this->values;
        foreach ($values as $k => &$v) {
            if ($v === '' || $v === null) {
                unset($values[$k]);
            }
        }
        unset(
            $values['product_name'],
            $values['categories'],
            $values['advantages'],
            $values['traits'],
            $v
        );

        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set('shop', 'ai_fields', waUtils::jsonEncode($values));
        return $this;
    }

    /**
     * @param shopProduct|array $p
     */
    public function loadFieldValuesFromProduct($p): shopAiApiRequest
    {
        $api = new waServicesApi();
        if (!$api->isConnected()) {
            return $this;
        }
        if (!$this->fields) {
            throw new waException('Fields are not loaded');
        }

        if (isset($this->fields['product_name'])) {
            $this->values['product_name'] = $p['name'];
        }
        if (isset($this->fields['categories'])) {
            $this->values['categories'] = join(', ', array_column($p['categories'], 'name'));
        }
        $summary = trim(strip_tags((string) $p['summary']));
        $description = trim(strip_tags((string) $p['description']));
        if ($description || $summary) {
            if (isset($this->fields['traits'])) {
                $this->values['traits'] = $description;
                if (isset($this->fields['advantages'])) {
                    $this->values['traits'] = $summary;
                }
            } else if (isset($this->fields['advantages'])) {
                $this->values['advantages'] = $summary;
                $this->values['advantages'] .= "\n".$description;
            }
        }
        return $this;
    }
}
