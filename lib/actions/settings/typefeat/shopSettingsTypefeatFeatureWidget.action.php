<?php
/**
 * Include feature dialog as a pop-up widget in other shop backend sections.
 */
class shopSettingsTypefeatFeatureWidgetAction extends waViewAction
{
    public function execute()
    {
        $selection = ifset($this->params, "selection", "");
        $product_id = ifset($this->params, "product_id", null);

        $this->view->assign([
            "selection" => $selection,
            "product_id" => $product_id
        ]);
    }

    /**
     * Method to use in smarty backend templates.
     * {shopSettingsTypefeatFeatureWidgetAction::widget()}
     */
    public static function widget($options = [])
    {
        // Если НЕ бэкенд
        if (wa()->getEnv() != 'backend') { return; }
        // Если НЕТ селектора
        if (empty($options["selection"])) { return; }

        $action = new self($options);
        return $action->display();
    }
}