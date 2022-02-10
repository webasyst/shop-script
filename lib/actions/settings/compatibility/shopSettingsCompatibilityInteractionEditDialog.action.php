<?php
/**
 * Type editor dialog HTML.
 */
class shopSettingsCompatibilityInteractionEditDialogAction extends waViewAction
{
    public function execute()
    {
        $id = waRequest::post('id', '', waRequest::TYPE_STRING_TRIM);
        $type = waRequest::post('type', '', waRequest::TYPE_STRING_TRIM);

        $frac_mode = shopFrac::getPluginFractionalMode($id, shopFrac::PLUGIN_MODE_FRAC, $type);
        $units_mode = shopFrac::getPluginFractionalMode($id, shopFrac::PLUGIN_MODE_UNITS, $type);

        $this->view->assign([
            'frac_mode' => $frac_mode,
            'units_mode' => $units_mode,
            'type' => $type
        ]);
    }
}
