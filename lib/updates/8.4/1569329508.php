<?php

$_file_paths = [
    wa()->getAppPath('lib/actions/coupons/shopCoupons.action.php', 'shop'),
    wa()->getAppPath('lib/actions/coupons/shopCouponsEditor.action.php', 'shop'),
    wa()->getAppPath('lib/actions/promos/shopPromos.actions.php', 'shop'),
    wa()->getAppPath('lib/actions/reports/shopReportsAbtesting.action.php', 'shop'),
    wa()->getAppPath('lib/actions/reports/shopReportsmarketingcosts.actions.php', 'shop'),
    wa()->getAppPath('lib/actions/settings/affiliate/shopSettingsAffiliate.action.php', 'shop'),
    wa()->getAppPath('lib/actions/settings/affiliate/shopSettingsAffiliateEnable.controller.php', 'shop'),
    wa()->getAppPath('lib/actions/settings/discount/shopSettingsDiscounts.action.php', 'shop'),
    wa()->getAppPath('lib/actions/settings/discount/shopSettingsDiscountsCategory.action.php', 'shop'),
    wa()->getAppPath('lib/actions/settings/discount/shopSettingsDiscountsCombineSave.controller.php', 'shop'),
    wa()->getAppPath('lib/actions/settings/discount/shopSettingsDiscountsCoupons.action.php', 'shop'),
    wa()->getAppPath('lib/actions/settings/discount/shopSettingsDiscountsCustomerTotal.action.php', 'shop'),
//    wa()->getAppPath('lib/actions/settings/discount/shopSettingsDiscountsEnable.controller.php', 'shop'),
    wa()->getAppPath('lib/actions/settings/discount/shopSettingsDiscountsOrderTotal.action.php', 'shop'),
    wa()->getAppPath('lib/actions/settings/followups/shopSettingsFollowups.action.php', 'shop'),
    wa()->getAppPath('lib/actions/settings/followups/shopSettingsFollowupsTest.controller.php', 'shop'),
    wa()->getAppPath('lib/actions/settings/recommendations/shopSettingsRecommendations.action.php', 'shop'),
    wa()->getAppPath('lib/actions/settings/recommendations/shopSettingsRecommendationsSave.controller.php', 'shop'),
    wa()->getAppPath('lib/actions/orders/shopOrdersWebPushSubscribe.controller.php', 'shop'),
    wa()->getAppPath('templates/actions/promos/PromosDefault.html', 'shop'),
    wa()->getAppPath('templates/actions/promos/PromosEditor.html', 'shop'),
    wa()->getAppPath('templates/actions/coupons/Coupons.html', 'shop'),
    wa()->getAppPath('templates/actions/coupons/CouponsEditor.html', 'shop'),
    wa()->getAppPath('templates/actions/promos/PromosDefault.html', 'shop'),
    wa()->getAppPath('templates/actions/promos/PromosEditor.html', 'shop'),
    wa()->getAppPath('templates/actions/reports/mcosts_editor.html', 'shop'),
    wa()->getAppPath('templates/actions/reports/mcosts_rows.html', 'shop'),
    wa()->getAppPath('templates/actions/reports/ReportsAbtesting.html', 'shop'),
    wa()->getAppPath('templates/actions/reports/ReportsMarketingcosts.html', 'shop'),
    wa()->getAppPath('templates/actions/settings/SettingsDiscountsCategory.html', 'shop'),
    wa()->getAppPath('templates/actions/settings/SettingsDiscountsCoupons.html', 'shop'),
    wa()->getAppPath('templates/actions/settings/SettingsDiscountsCustomerTotal.html', 'shop'),
    wa()->getAppPath('templates/actions/settings/SettingsDiscountsOrderTotal.html', 'shop'),
    wa()->getAppPath('templates/actions/settings/SettingsFollowups.html', 'shop'),
    wa()->getAppPath('templates/actions/settings/SettingsRecommendations.html', 'shop'),
];
foreach ($_file_paths as $_file_path) {
    if (file_exists($_file_path)) {
        try {
            waFiles::delete($_file_path);
        } catch (Exception $e) {
        }
    }
}

waAppConfig::clearAutoloadCache('shop');