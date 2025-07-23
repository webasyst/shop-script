<?php
class shopSettingsAiActions extends waActions
{
    public function defaultAction()
    {
        if (wa()->whichUI() == '1.3') {
            $this->display([], 'templates/actions-legacy/settings/SettingsAi.html');
            return;
        }
        if (!(new waServicesApi())->isConnected()) {
            $this->display([], 'templates/actions/settings/SettingsAi.html');
            return;
        }

        $this->display([
            'is_teaser' => shopLicensing::isStandard(),
            'sections' => $this->getSections(),
            'wa_total' => $this->getRemainingAiCount(),
        ], 'templates/actions/settings/SettingsAi.html');
    }

    public function getSections()
    {
        $request = (new shopAiApiRequest())
                ->loadFieldsFromApi('store_product')
                ->loadFieldValuesFromSettings();

        unset(
            $request->fields['text_length'],
            $request->fields['product_name'],
            $request->fields['categories'],
            $request->fields['advantages'],
            $request->fields['traits']
        );

        return $request->getSectionsWithFields();
    }

    public function saveAction()
    {
        $request_data = waRequest::post('data', null, 'array');
        if ($request_data) {
            $request = (new shopAiApiRequest())
                ->loadFieldsFromApi('store_product')
                ->setFieldValues($request_data)
                ->saveFieldValuesToSettings();
        }

        $this->displayJson('ok');
    }

    protected function getRemainingAiCount()
    {
        try {
            $wa_service_api = new waServicesApi();
        } catch (Throwable $e) {
            return null;
        }

        if (method_exists($wa_service_api, 'isBrokenConnection') && $wa_service_api->isBrokenConnection()) {
            return null;
        }

        if (!$wa_service_api->isConnected()) {
            return null;
        }

        $res = $wa_service_api->getBalance('AI');
        if ($res['status'] != 200) {
            return null;
        }

        $balance_amount = ifset($res, 'response', 'amount', 0);
        $price_value = ifset($res, 'response', 'price', 0);
        $free_limits = ifset($res, 'response', 'free_limits', '');
        $remaining_free_calls = ifempty($res, 'response', 'remaining_free_calls', []);
        $remaining_pack = ifset($remaining_free_calls, 'pack', 0);
        unset($remaining_free_calls['pack']);
        if ($balance_amount > 0 && $price_value > 0) {
            $messages_count = intval(floor($balance_amount / $price_value));
        }

        $wa_total = ifset($messages_count, 0)
                    + ifset($remaining_free_calls, 'total', 0)
                    + ifset($remaining_pack, 0);

        return $wa_total;
    }
}
