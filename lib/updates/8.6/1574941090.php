<?php

class shopPromoBannersMigrationUpdate
{
    /**
     * @var shopPromoModel
     */
    protected $promo_model;

    /**
     * @var shopPromoRulesModel
     */
    protected $promo_rules_model;

    /**
     * @var array
     */
    protected $promos;

    /**
     * @var array
     */
    protected $banner_rules;

    public function __construct()
    {
        $this->promo_model = new shopPromoModel();
        $this->promo_rules_model = new shopPromoRulesModel();
    }

    public function run()
    {
        $this->lock();

        try {
            $this->promo_model->query("SELECT `link` FROM `shop_promo` WHERE 0");
            $this->bannersMigration();
            $this->tableStructureUpdate();
        } catch (waDbException $e) {
        }

        $this->unlock();
    }

    protected function bannersMigration()
    {
        $this->promos = $this->promo_model->getAll('id');
        if (empty($this->promos)) {
            return;
        }

        $this->buildBannerRules();
        $this->saveBannerRules();
    }

    protected function buildBannerRules()
    {
        foreach ($this->promos as $promo_id => $promo) {
            $banner_rule = [
                'promo_id'    => $promo_id,
                'rule_type'   => 'banner',
                'rule_params' => [
                    'banners' => [
                        [
                            'type'               => 'link',
                            'image_filename'     => $promo_id.'.'.$promo['ext'],
                            'title'              => $promo['title'],
                            'body'               => $promo['body'],
                            'link'               => $promo['link'],
                            'color'              => $promo['color'],
                            'background_color'   => $promo['background_color'],
                            'countdown_datetime' => $promo['countdown_datetime'],
                        ],
                    ],
                ],
            ];
            $banner_rule['rule_params'] = waUtils::jsonEncode($banner_rule['rule_params']);

            $this->banner_rules[] = $banner_rule;
        }
    }

    protected function saveBannerRules()
    {
        $this->promo_rules_model->multipleInsert($this->banner_rules);
    }

    protected function tableStructureUpdate()
    {
        $sqls = [
            'ALTER TABLE `shop_promo` DROP COLUMN `type`',
            'ALTER TABLE `shop_promo` DROP COLUMN `body`',
            'ALTER TABLE `shop_promo` DROP COLUMN `link`',
            'ALTER TABLE `shop_promo` DROP COLUMN `color`',
            'ALTER TABLE `shop_promo` DROP COLUMN `background_color`',
            'ALTER TABLE `shop_promo` DROP COLUMN `ext`',
            'ALTER TABLE `shop_promo` DROP COLUMN `countdown_datetime`',
            'ALTER TABLE `shop_promo` CHANGE `title` `name` text null',
        ];

        foreach ($sqls as $sql) {
            $this->promo_model->exec($sql);
        }
    }

    protected function lock()
    {
        try {
            $sql = "LOCK TABLES
                shop_promo WRITE,
                shop_promo_rules WRITE";
            $this->promo_model->exec($sql);
        } catch (waDbException $e) {
        }
    }

    protected function unlock()
    {
        try {
            $this->promo_model->exec('UNLOCK TABLES');
        } catch (waDbException $e) {
        }
    }
}

$update = new shopPromoBannersMigrationUpdate();
$update->run();