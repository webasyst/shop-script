<?php

class shopProdSetBadgeDialogAction extends waViewAction
{
    public function execute()
    {
//        if (!$this->getUser()->getRights('shop', 'setscategories')) {
//            throw new waRightsException(_w('Access denied'));
//        }

        $this->view->assign([
            "badges" => $this->getBadges()
        ]);

        $this->setTemplate('templates/actions/prod/main/dialogs/products.set_badge.html');
    }

    protected function getBadges() {
        $result = [];

        $badges = shopProdListAction::getProductBadges();
        foreach ($badges as $badge) {
            $_badge = [
                "id" => $badge["id"],
                "name"  => $badge["name"],
                "icon"  => null,
                "value" => $badge["id"]
            ];

            switch ( $badge["id"] ) {
                case "new":
                    $_badge["icon"] = '<i class="fas fa-bolt"></i>';
                    break;
                case "lowprice":
                    $_badge["icon"] = '<i class="fas fa-piggy-bank"></i>';
                    break;
                case "bestseller":
                    $_badge["icon"] = '<i class="fas fa-chart-line"></i>';
                    break;
                case "custom":
                    $_badge["icon"] = '<i class="fas fa-code"></i>';
                    $_badge["value"] = $badge["code"];
                    break;
            }
            $result[] = $_badge;
        }

        $result[] = [
            "id" => "remove",
            "name"  => _w("Remove"),
            "icon"  => '<i class="fas fa-times"></i>',
            "value" => null
        ];

        return $result;
    }
}
