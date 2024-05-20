<?php

class shopStocksAction extends waViewAction
{
    public function execute()
    {
        $tab = null;
        $content = null;
        if (wa()->whichUI('shop') === '1.3') {
            $tab = $this->getRequest()->request('tab');
            $content = $this->getTabContent($tab);
        }
        $transfers = $this->getTransfers();


        /**
         * Show stocks and transfers
         *
         * @param string $tab
         * @param string $content Html. Result from shopStocksBalanceAction
         * @param array $transfers  Html result from shopTransferListAction and other info from transfers
         *
         * @event backend_stocks.stocks
         */
        $params = array(
            'tab' => $tab,
            'content' => $content,
            'transfers' => $transfers
        );

        $backend_stocks_hook = wa('shop')->event('backend_stocks.stocks', $params);
        $this->view->assign('backend_stocks_hook', $backend_stocks_hook);

        $this->view->assign(array(
            'tab' => $tab,
            'content' => $content,
            'transfers' => $transfers
        ));
    }

    public function getTabContent($tab)
    {
        $vars = $this->view->getVars();
        $this->view->clearAllAssign();
        $html = '';
        if ($tab === 'balance') {
            $action = new shopStocksBalanceAction();
            $html = $action->display();
        } else if ($tab === 'log') {
            $action = new shopStocksLogAction();
            $html = $action->display();
        }

        $this->view->clearAllAssign();
        $this->view->assign($vars);

        return $html;
    }

    public function getTransfers()
    {
        $vars = $this->view->getVars();
        $this->view->clearAllAssign();
        $action = new shopTransferListAction(array(
            'disabled_lazyload' => true,
            'disabled_sort' => true,
            'filter' => 'status=sent',
            'limit' => 500
        ));
        $html = $action->display();
        $count = $action->count();
        $total_count = $action->getTotalCount();
        $this->view->clearAllAssign();
        $this->view->assign($vars);
        return array(
            'html' => $html,
            'count' => $count,
            'total_count' => $total_count,
            'rest_count' => max($total_count - $count, 0),
            'limit' => $action->getDefaultLimit()
        );
    }
}
