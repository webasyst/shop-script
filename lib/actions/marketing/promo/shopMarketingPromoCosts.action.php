<?php

class shopMarketingPromoCostsAction extends shopMarketingViewAction
{
    protected $promo_model;
    protected $expense_model;

    protected $promo;

    public function preExecute()
    {
        // Models
        $this->promo_model = new shopPromoModel();
        $this->expense_model = new shopExpenseModel();

        // Promo
        $promo_id = waRequest::post('promo_id', null, waRequest::TYPE_INT);
        $this->promo = $this->promo_model->getById($promo_id);
    }

    public function execute()
    {
        $costs = $this->getCosts();

        $additional_html = $this->backendMarketingPromoExpensesEvent(ref([
            'expenses' => &$costs,
        ]));

        $this->view->assign([
            'costs' => $costs,
            'additional_html' => $additional_html,
        ]);
    }

    protected function getCosts()
    {
        if (empty($this->promo['id'])) {
            return [];
        }

        $fields = [
            'type' => 'promo',
            'name' => $this->promo['id'],
        ];
        $costs = (array)$this->expense_model->getByField($fields, 'id');

        return $costs;
    }

    protected function backendMarketingPromoExpensesEvent(&$params)
    {
        /**
         * Costs tab on single promo page in marketing section.
         * Hook allows to modify data before sending to template for rendering,
         * as well as add custom HTML to the tab.
         *
         * @event backend_marketing_promo_expenses
         * @param array [string]array $params
         * @param array [string]array $params['expenses']  list of expenses (writable)
         *
         * @return array[string][string]string $return[%plugin_id%]['top']    custom HTML to add above the costs table
         * @return array[string][string]string $return[%plugin_id%]['bottom'] custom HTML to add below the costs table
         */
        $event_result = wa()->event('backend_marketing_promo_expenses', $params);

        $additional_html = [
            'top'    => [],
            'bottom' => [],
        ];

        foreach($event_result as $res) {
            if (!is_array($res)) {
                continue;
            }
            foreach($res as $k => $v) {
                if (isset($additional_html[$k])) {
                    if (!is_array($v)) {
                        $v = [$v];
                    }
                    foreach($v as $html) {
                        $additional_html[$k][] = $html;
                    }
                }
            }
        }

        return $additional_html;
    }
}