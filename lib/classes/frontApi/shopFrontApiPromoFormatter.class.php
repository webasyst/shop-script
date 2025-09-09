<?php

class shopFrontApiPromoFormatter extends shopFrontApiFormatter
{
    public function format($promo = [])
    {
        if (!$promo) {
            return [];
        }

        $allowed_fields = [
            'id' => 'integer',
            'name' => 'string',
            'enabled' => 'integer',
            'note' => 'string',
            'start_datetime' => 'string',
            'finish_datetime' => 'string',
        ];

        return array_intersect_key(self::formatFieldsToType($promo, $allowed_fields), $allowed_fields) + [
            'rules' => $this->formatRules($promo),
        ];
    }

    public function formatRules($promo)
    {
        if (!isset($promo['rules'])) {
            return [];
        }
        $rule_allowed_fields = [
            'id' => 'integer',
            'promo_id' => 'integer',
            'rule_type' => 'string',
        ];

        $params_allowed_fields = [
            'type' => 'string',
            'title' => 'string',
            'body' => 'string',
            'link' => 'string',
            'color' => 'string',
            'background_color' => 'string',
            'countdown_datetime' => 'string',
            'image' => 'string',
        ];

        foreach ($promo['rules'] as &$rule) {
            $promo_banner = ifset($rule, 'rule_params', 'banners', 0, []);
            $rule = array_intersect_key(self::formatFieldsToType($rule, $rule_allowed_fields), $rule_allowed_fields);
            if ($promo_banner) {
                $rule['rule_params']['banners'][0] = array_intersect_key(self::formatFieldsToType($promo_banner, $params_allowed_fields), $params_allowed_fields);
            }
        }

        return $promo['rules'];
    }
}
