<?php

class shopChannelsOtherRequestsController extends waJsonController
{
    public function execute()
    {
        $method = waRequest::post('method', null, waRequest::TYPE_STRING);
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            $this->setError('Method not allowed');
        }
    }

    private function getMaxChats()
    {
        $token = waRequest::post('token', null, waRequest::TYPE_STRING);
        if ($token) {
            $options = ['format' => waNet::FORMAT_JSON];
            $headers = ['Authorization' => $token];
            $network = new waNet($options, $headers);
            try {
                $response = $network->query('https://platform-api.max.ru/chats');
                $this->response['chats'] = ifset($response, 'chats', []);
            } catch (waNetException $wne) {
                $message = waUtils::jsonDecode($wne->getMessage(), true);
                $message = ifset($message, 'message', $message);
                $this->errors[] = $message;
            }
        }
    }

    private function getMaxBot()
    {
        $token = waRequest::post('token', null, waRequest::TYPE_STRING);
        if ($token) {
            $options = ['format' => waNet::FORMAT_JSON];
            $headers = ['Authorization' => $token];
            $network = new waNet($options, $headers);
            try {
                $this->response['bot'] = $network->query('https://platform-api.max.ru/me');
            } catch (waNetException $wne) {
                $message = waUtils::jsonDecode($wne->getMessage(), true);
                $message = ifset($message, 'message', $message);
                $this->errors[] = $message;
            }
        }
    }
}
