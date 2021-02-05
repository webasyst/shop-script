<?php
/**
 * Download sku file. Used on Sku tab.
 */
class shopProdDownloadSkuFileController extends waViewController
{
    public function execute()
    {
        $sku_id = waRequest::get('sku_id', null, waRequest::TYPE_INT);
        $product_id = waRequest::get('product_id', null, waRequest::TYPE_INT);

        $file = self::getFileParams($sku_id, $product_id);

        if (!file_exists($file['path'])) {
            throw new waException(_w("File not found"), 404);
        }

        waFiles::readFile($file['path'], "sku_file_{$product_id}_{$sku_id}{$file['ext']}", true, true);
    }

    protected static function getFileParams($sku_id, $product_id)
    {
        $product_skus_model = new shopProductSkusModel();
        $file_name = $product_skus_model->getByField(self::formatParams($sku_id, $product_id))['file_name'];
        $path = "sku_file/{$sku_id}";
        $dot_position = strripos($file_name, '.');
        if ($dot_position !== false) {
            $file['ext'] = substr($file_name, $dot_position);
            $path .= $file['ext'];
        } else {
            $file['ext'] = '';
            $path .= '.';
        }

        $file['path'] = shopProduct::getPath($product_id, $path);

        return $file;
    }

    public static function getSkuFileUrl($sku_id, $product_id)
    {
        $download_params = array(
            'module' => 'prod',
            'action' => 'downloadSkuFile',
            'sku_id' => $sku_id,
            'product_id' => $product_id,
        );
        return "?" . http_build_query($download_params);
    }

    // Also removes parameters file from product_sku table
    public static function checkSkuFile($sku_id, $product_id)
    {
        $file = self::getFileParams($sku_id, $product_id);

        if (file_exists($file['path'])) {
            return true;
        } else {
            $empty_fields = array(
                'file_name' => null,
                'file_size' => null,
                'file_description' => null,
            );
            $product_skus_model = new shopProductSkusModel();
            $product_skus_model->updateByField(self::formatParams($sku_id, $product_id), $empty_fields);

            return false;
        }
    }

    protected static function formatParams($sku_id, $product_id)
    {
        return array(
            'id' => $sku_id,
            'product_id' => $product_id,
        );
    }
}