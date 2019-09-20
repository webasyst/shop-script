<?php

class shopMarketingPromoCloneController extends waJsonController
{
    /**
     * @var shopPromoModel
     */
    protected $promo_model;

    /**
     * @var shopPromoRoutesModel
     */
    protected $promo_routes_model;

    /**
     * @var shopPromoRulesModel
     */
    protected $promo_rules_model;

    /**
     * @var null|int
     */
    protected $promo_id;

    /**
     * @var array
     */
    protected $promo_data;

    /**
     * @var array
     */
    protected $rules;

    /**
     * @var array
     */
    protected $storefronts;

    public function preExecute()
    {
        // Models
        $this->promo_model = new shopPromoModel();
        $this->promo_routes_model = new shopPromoRoutesModel();
        $this->promo_rules_model = new shopPromoRulesModel();

        $this->promo_id = waRequest::post('promo_id', null, waRequest::TYPE_INT);
    }

    public function execute()
    {
        // Promo
        $this->promo_data = $this->promo_model->getById($this->promo_id);
        unset($this->promo_data['id']);
        if (empty($this->promo_id) || empty($this->promo_data)) {
            return $this->errors[] = _w('Promo not found.');
        }

        $this->promo_data['title'] = $this->promo_data['title'].' ('._w('copy').')';
        $this->promo_data['enabled'] = 0; // when cloning, be sure to turn off the promo

        $new_promo_id = $this->promo_model->insert($this->promo_data);

        // Save promo image
        if (!empty($this->promo_data['ext'])) {
            $path = wa('shop')->getDataPath('promos/', true);
            $source_path = $path.sprintf('%s.%s', $this->promo_id, $this->promo_data['ext']);
            $target_path = $path.sprintf('%s.%s', $new_promo_id, $this->promo_data['ext']);
            try {
                waFiles::copy($source_path, $target_path);
            } catch (Exception $e) {

            }
        }

        // Rules
        $this->rules = $this->promo_rules_model->getByField('promo_id', $this->promo_id, 'id');
        foreach ($this->rules as &$rule) {
            unset($rule['id']);
            $rule['promo_id'] = $new_promo_id;
            $rule['rule_params'] = is_array($rule['rule_params']) ? waUtils::jsonEncode($rule['rule_params']) : $rule['rule_params'];
        }
        unset($rule);
        $this->promo_rules_model->multipleInsert(array_values($this->rules));

        // Routes
        $this->storefronts = $this->promo_routes_model->getByField('promo_id', $this->promo_id, 'storefront');
        foreach ($this->storefronts as &$storefront) {
            $storefront['promo_id'] = $new_promo_id;
        }
        unset($storefront);
        $this->promo_routes_model->multipleInsert(array_values($this->storefronts));

        $this->response = [
            'id' => $new_promo_id,
        ];
    }
}