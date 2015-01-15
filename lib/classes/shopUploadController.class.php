<?php

abstract class shopUploadController extends waJsonController
{
    protected $name = 'files';
    public function execute()
    {
        $this->response['files'] = array();

        $this->getStorage()->close();
        if (waRequest::server('HTTP_X_FILE_NAME')) {
            $name = waRequest::server('HTTP_X_FILE_NAME');
            $size = waRequest::server('HTTP_X_FILE_SIZE');
            $file_path = wa()->getTempPath('shop/upload/').$name;
            $append_file = is_file($file_path) && $size > filesize($file_path);
            clearstatcache();
            file_put_contents($file_path, fopen('php://input', 'r'), $append_file ? FILE_APPEND : 0);
            $file = new waRequestFile(array(
                'name'     => $name,
                'type'     => waRequest::server('HTTP_X_FILE_TYPE'),
                'size'     => $size,
                'tmp_name' => $file_path,
                'error'    => 0
            ));

            try {
                $this->response['files'][] = $this->save($file);
            } catch (Exception $e) {
                $this->response['files'][] = array(
                    'error' => $e->getMessage()
                );
            }
        } else {
            $files = waRequest::file($this->name);
            foreach ($files as $file) {
                if ($file->error_code != UPLOAD_ERR_OK) {
                    $this->response['files'][] = array(
                        'error' => $file->error
                    );
                } else {
                    try {
                        $this->response['files'][] = $this->save($file);
                    } catch (Exception $e) {
                        $this->response['files'][] = array(
                            'name'  => $file->name,
                            'error' => $e->getMessage()
                        );
                    }
                }
            }
        }
    }

    abstract protected function save(waRequestFile $file);

    public function display()
    {
        $this->getResponse()->addHeader('Content-type', 'application/json');
        $this->getResponse()->sendHeaders();
        echo json_encode($this->response);
    }
}
