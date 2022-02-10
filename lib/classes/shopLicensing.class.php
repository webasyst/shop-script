<?php

class shopLicensing
{
    /**
     * Whether Shop app is in Standard (non-premium) mode.
     *
     * @return bool
     */
    public static function isStandard()
    {
        return !self::isPremium();
    }

    /**
     * Whether Shop app is in Premium mode. In this mode, more features
     * are available to be turned on.
     *
     * Premium mode is enabled if any of premium features are turned on, or if app
     * at any point in past had a license with premium features enabled.
     *
     * @return bool
     */
    public static function isPremium()
    {
        // Used to have Premium license in the past?
        $is_premium = wa()->getSetting('license_premium', '', 'shop');
        if ($is_premium) {
            return true;
        }

        // If any premium feature is enabled, force Premium license mode.
        // Installer checks this occasionally and enforces licensing penalties.
        if (self::isAnyPremiumFeatureEnabled()) {
            return true;
        }

        // Ask Installer if we have a proper license.
        if (self::hasPremiumLicense()) {
            $app_settings = new waAppSettingsModel();
            $app_settings->set('shop', 'license_premium', date('Y-m-d H:i:s'));
            return true;
        }

        return false;
    }

    /**
     * @return bool whether Shop app has any premium feature turned on
     */
    public static function isAnyPremiumFeatureEnabled()
    {
        return shopFrac::isEnabled() || shopUnits::isEnabled();
    }

    /**
     * @return bool whether installation has a license bound to it with Premium features enabled
     */
    public static function hasPremiumLicense()
    {
        $license = self::getLicense();
        return !empty($license['options']['edition']) && $license['options']['edition'] === 'PREMIUM';
    }

    /**
     * @return bool whether installation has a proper license bound to it
     */
    public static function hasLicense()
    {
        $license = self::getLicense();
        return !empty($license['status']);
    }

    /**
     * @return null|array
     */
    protected static function getLicense()
    {
        try {
            if (wa()->appExists('installer')) {
                wa('installer');
                return installerHelper::checkLicense('shop');
            }
        } catch (waException $e) {
        }

        return null;
    }
}
