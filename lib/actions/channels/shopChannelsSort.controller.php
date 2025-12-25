<?php

class shopChannelsSortController extends waJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new waRightsException('Access denied');
        }

        $sorting = waRequest::request('sorting', [], waRequest::TYPE_ARRAY);
        if (empty($sorting)) {
            $this->errors[] = ['error_description' => 'sorting is required'];
            return;
        }

        $sales_channel_model = new shopSalesChannelModel();
        foreach ($sorting as $_data) {
            $id = (int) ifempty($_data, 'id', 0);
            $sort = (int) ifempty($_data, 'sort', 0);
            if (empty($id) || $id < 1) {
                continue;
            }
            try {
                $sales_channel_model->updateById($id, ['sort' => $sort]);
            } catch (waDbException $dbe) {
                $this->errors[] = ['error_description' => $dbe->getMessage()];
                return;
            }
        }

        $this->response = true;
    }
}
