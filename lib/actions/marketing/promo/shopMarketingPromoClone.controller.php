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
     * @var int
     */
    protected $new_promo_id;

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
        $this->clonePromo();
        if (!empty($this->errors)) {
            return $this->errors;
        }

        $this->cloneRules();
        $this->cloneRoutes();

        $this->response = [
            'id' => $this->new_promo_id,
        ];
    }

    protected function clonePromo()
    {
        $this->promo_data = $this->promo_model->getById($this->promo_id);
        unset($this->promo_data['id']);
        if (empty($this->promo_id) || empty($this->promo_data)) {
            return $this->errors[] = _w('Promo not found.');
        }

        $this->promo_data['name'] = $this->promo_data['name'].' ('._w('copy').')';
        $this->promo_data['enabled'] = 0; // when cloning, be sure to turn off the promo

        $this->new_promo_id = $this->promo_model->insert($this->promo_data);
    }

    protected function cloneRules()
    {
        $this->rules = $this->promo_rules_model->getByField('promo_id', $this->promo_id, 'id');
        foreach ($this->rules as &$rule) {
            unset($rule['id']);
            $rule['promo_id'] = $this->new_promo_id;

            $part_of_name = '';
            foreach (explode('_', $rule['rule_type']) as $part) {
                $part_of_name .= ucfirst($part);
            }
            $method_name = "prepare{$part_of_name}Rule";
            /**
             * @uses shopMarketingPromoCloneController::prepareBannerRule();
             */
            if (method_exists($this, $method_name)) {
                $this->$method_name($rule);
            }

            $rule['rule_params'] = is_array($rule['rule_params']) ? waUtils::jsonEncode($rule['rule_params']) : $rule['rule_params'];
        }
        unset($rule);
        $this->promo_rules_model->multipleInsert(array_values($this->rules));
    }

    /**
     * Banner images need to be moved to the catalog of the new promo
     * @param $rule
     * @throws waException
     */
    protected function prepareBannerRule(&$rule)
    {
        $path = wa('shop')->getDataPath('promos/', true);
        $new_promo_folder = shopHelper::getFolderById($this->new_promo_id);
        if (!empty($rule['rule_params']['banners'])) {
            foreach ($rule['rule_params']['banners'] as &$banner) {
                $filename = $banner['image_filename'];
                $new_filename = shopPromoBannerHelper::generateImageName().'.'.pathinfo($filename, PATHINFO_EXTENSION);
                $source_path = shopPromoBannerHelper::getPromoBannerPath($this->promo_id, $filename);
                $target_path = $path.$new_promo_folder.$new_filename;

                try {
                    waFiles::copy($source_path, $target_path);
                    $banner['image_filename'] = $new_filename;
                } catch (Exception $e) {

                }
            }
            unset($banner);
        }
    }

    protected function cloneRoutes()
    {
        $this->storefronts = $this->promo_routes_model->getByField('promo_id', $this->promo_id, 'storefront');
        foreach ($this->storefronts as &$storefront) {
            $storefront['promo_id'] = $this->new_promo_id;
        }
        unset($storefront);
        $this->promo_routes_model->multipleInsert(array_values($this->storefronts));
    }
}