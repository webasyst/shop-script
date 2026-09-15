<?php

class shopMigratePluginWbImageDeadlineException extends waException
{
}

/**
 * Downloads and imports a very small image batch at a time.
 *
 * Snapshot creation stores URLs only. This worker is shared by inline and
 * deferred image runs and intentionally never keeps an HTTP body in memory.
 */
class shopMigratePluginWbImageWorker
{
    const DEFAULT_BATCH_SIZE = 1;
    const MAX_URL_CANDIDATES = 5;
    const HTTP_TIMEOUT_SECONDS = 8;
    const DEADLINE_RESERVE_SECONDS = 4;
    const MAX_DOWNLOAD_BYTES = 52428800;
    const MAX_SOURCE_PIXELS = 60000000;
    const MEMORY_RESERVE_BYTES = 50331648;
    const GD_BYTES_PER_PIXEL = 8;
    const IMAGICK_BYTES_PER_PIXEL = 12;

    private $logger;
    private $images_model;
    private $image_map_model;

    public function __construct($logger = null)
    {
        $this->logger = $logger;
        $this->images_model = new shopProductImagesModel();
        $this->image_map_model = new shopMigratePluginWbImageMapModel();
    }

    /**
     * @param int   $product_id
     * @param array $images normalized rows from collectGroupImages()
     * @param int   $offset
     * @param int   $limit
     * @return array
     */
    public function process(
        $product_id,
        array $images,
        $offset = 0,
        $limit = self::DEFAULT_BATCH_SIZE,
        $deadline = null
    )
    {
        $product_id = (int) $product_id;
        $offset = max(0, (int) $offset);
        $limit = min(self::DEFAULT_BATCH_SIZE, max(1, (int) $limit));
        $total = count($images);
        $end = min($total, $offset + $limit);
        $result = array(
            'offset'   => $offset,
            'next'     => $end,
            'total'    => $total,
            'imported' => 0,
            'skipped'  => 0,
            'errors'   => array(),
            'done'     => $end >= $total,
        );

        for ($index = $offset; $index < $end; $index++) {
            $item = $images[$index];
            try {
                if (!$this->hasTimeForAttempt($deadline)) {
                    $result['next'] = $index;
                    $result['done'] = false;
                    break;
                }
                if ($this->importOne($product_id, $item, $deadline)) {
                    $result['imported']++;
                } else {
                    $result['skipped']++;
                }
            } catch (shopMigratePluginWbImageDeadlineException $e) {
                $result['next'] = $index;
                $result['done'] = false;
                break;
            } catch (Throwable $e) {
                $result['errors'][] = sprintf(
                    _wp('Image %d could not be imported: %s'),
                    $index + 1,
                    $e->getMessage()
                );
                $this->logError($e->getMessage(), array(
                    'product_id' => $product_id,
                    'nm_id'      => (int) ifset($item['nm_id'], 0),
                    'position'   => $index,
                ));
            }
        }

        return $result;
    }

    /**
     * Extracts a stable, deduplicated product gallery from raw card details.
     */
    public function collectGroupImages(array $cards)
    {
        $result = array();
        $seen = array();
        foreach ($cards as $card) {
            $nm_id = (int) ifset($card['nm_id'], 0);
            $details = $this->decodeJson(ifset($card['details'], array()));
            $photos = ifset($details['photos'], array());
            if (!is_array($photos)) {
                continue;
            }
            foreach ($photos as $position => $photo) {
                $candidates = $this->extractPhotoCandidates($photo);
                if (!$candidates) {
                    continue;
                }
                $canonical = reset($candidates);
                $source_key = sha1($this->normalizeUrl($canonical));
                if (isset($seen[$source_key])) {
                    $seen_index = $seen[$source_key];
                    $result[$seen_index]['nm_ids'][$nm_id] = $nm_id;
                    if (!isset($result[$seen_index]['nm_positions'][$nm_id])
                        || (int) $position < (int) $result[$seen_index]['nm_positions'][$nm_id]
                    ) {
                        $result[$seen_index]['nm_positions'][$nm_id] = (int) $position;
                    }
                    continue;
                }
                $seen[$source_key] = count($result);
                $result[] = array(
                    'nm_id'       => $nm_id,
                    'nm_ids'      => array($nm_id => $nm_id),
                    'nm_positions'=> array($nm_id => (int) $position),
                    'position'    => (int) $position,
                    'source_key'  => $source_key,
                    'candidates'  => $candidates,
                );
            }
        }
        return $result;
    }

    private function importOne($product_id, array $item, $deadline)
    {
        $source_key = (string) ifset($item['source_key'], '');
        if ($source_key === '') {
            return false;
        }

        $existing = $this->image_map_model->getByField(array(
            'shop_product_id' => $product_id,
            'source_key'      => $source_key,
        ));
        if ($existing) {
            $image = $this->images_model->getById((int) $existing['shop_image_id']);
            if ($image && (int) $image['product_id'] === $product_id) {
                return false;
            } else {
                $this->image_map_model->deleteById($existing['id']);
            }
        }

        $candidates = array_slice(
            array_values(array_unique((array) ifset($item['candidates'], array()))),
            0,
            self::MAX_URL_CANDIDATES
        );
        if (!$candidates) {
            return false;
        }

        $last_error = null;
        foreach ($candidates as $url) {
            $path = null;
            $wa_image = null;
            $added_image_id = 0;
            $attempt_started = false;
            try {
                if (!$this->hasTimeForAttempt($deadline)) {
                    // At least one URL has already failed in this batch. Do
                    // not restart the same candidate chain forever on every
                    // AJAX request: record the image error and advance.
                    if ($last_error instanceof Throwable) {
                        throw $last_error;
                    }
                    throw new shopMigratePluginWbImageDeadlineException(
                        _wp('The image will be retried in the next import batch.')
                    );
                }
                $attempt_started = true;
                $path = $this->download($url, $deadline);
                // The attempt gate above reserves download and processing
                // time. Once bytes have been downloaded, finish this image;
                // abandoning it here would repeat the same slow URL forever.
                $image_info = $this->validateForImport($path);
                $extension = $image_info['extension'];
                $wa_image = waImage::factory($path, $image_info['adapter']);
                $filename = sprintf(
                    'wildberries-%d-%d.%s',
                    (int) ifset($item['nm_id'], 0),
                    (int) ifset($item['position'], 0) + 1,
                    $extension
                );
                $image = $this->images_model->addImage($wa_image, $product_id, $filename);
                if (empty($image['id'])) {
                    throw new waException(_wp('Shop-Script did not save the image.'));
                }
                $added_image_id = (int) $image['id'];
                unset($wa_image);
                $this->image_map_model->link(
                    (int) ifset($item['nm_id'], 0),
                    $source_key,
                    $product_id,
                    $added_image_id,
                    max((int) $image_info['width'], (int) $image_info['height'])
                );
                $this->deleteTempFile($path);
                return true;
            } catch (shopMigratePluginWbImageDeadlineException $e) {
                unset($wa_image);
                if ($path) {
                    $this->deleteTempFile($path);
                }
                if ($attempt_started) {
                    // The candidate already consumed this request's time
                    // budget. Restarting it from candidate #1 can loop
                    // forever; record a bounded image error and move on.
                    if ($last_error instanceof Throwable) {
                        throw $last_error;
                    }
                    throw new waException(
                        _wp('The Wildberries image download did not finish within the import batch time limit.')
                    );
                }
                throw $e;
            } catch (Throwable $e) {
                $last_error = $e;
                unset($wa_image);
                if ($added_image_id > 0) {
                    try {
                        $this->images_model->delete($added_image_id);
                    } catch (Throwable $delete_error) {
                        $this->logError($delete_error->getMessage(), array(
                            'product_id' => $product_id,
                            'image_id'   => $added_image_id,
                        ));
                    }
                }
                if ($path) {
                    $this->deleteTempFile($path);
                }
            }
        }

        throw $last_error ?: new waException(_wp('No usable Wildberries image URL was found.'));
    }

    private function extractPhotoCandidates($photo)
    {
        if (is_string($photo)) {
            return $this->isAllowedUrl($photo) ? array($photo) : array();
        }
        if (!is_array($photo)) {
            return array();
        }
        $result = array();
        foreach (array('big', 'c516x688', 'c246x328', 'tm', 'square') as $key) {
            $url = trim((string) ifset($photo[$key], ''));
            if ($url !== '' && $this->isAllowedUrl($url)) {
                $result[] = $url;
            }
        }
        return array_values(array_unique($result));
    }

    private function download($url, $deadline = null)
    {
        if (!$this->isAllowedUrl($url)) {
            throw new waException(_wp('Wildberries returned an unsafe image URL.'));
        }
        $directory = wa()->getTempPath('plugins/migrate/wb-images', 'shop');
        if (!is_dir($directory) && !waFiles::create($directory)) {
            throw new waException(_wp('Unable to create a temporary image directory.'));
        }
        $path = tempnam($directory, 'wb-');
        if ($path === false) {
            throw new waException(_wp('Unable to allocate a temporary image file.'));
        }

        $context = stream_context_create(array(
            'http' => array(
                'method'          => 'GET',
                'timeout'         => self::HTTP_TIMEOUT_SECONDS,
                'follow_location' => 0,
                'max_redirects'   => 0,
                'header'          => "Accept: image/*\r\nUser-Agent: Shop-Script Wildberries importer\r\n",
            ),
            'ssl' => array(
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ),
        ));
        $source = @fopen($url, 'rb', false, $context);
        if (!$source) {
            $this->deleteTempFile($path);
            throw new waException(_wp('Unable to download the Wildberries image.'));
        }
        stream_set_timeout($source, self::HTTP_TIMEOUT_SECONDS);
        $target = @fopen($path, 'wb');
        if (!$target) {
            fclose($source);
            $this->deleteTempFile($path);
            throw new waException(_wp('Unable to open a temporary image file.'));
        }

        $bytes = 0;
        $attempt_deadline = microtime(true) + self::HTTP_TIMEOUT_SECONDS;
        try {
            while (!feof($source)) {
                if (microtime(true) >= $attempt_deadline) {
                    throw new waException(_wp('The Wildberries image download timed out.'));
                }
                if ($deadline !== null
                    && is_numeric($deadline)
                    && microtime(true) + self::DEADLINE_RESERVE_SECONDS >= (float) $deadline
                ) {
                    throw new shopMigratePluginWbImageDeadlineException(
                        _wp('The image will be retried in the next import batch.')
                    );
                }
                $chunk = fread($source, 65536);
                if ($chunk === false) {
                    throw new waException(_wp('The Wildberries image download was interrupted.'));
                }
                if ($chunk === '') {
                    $meta = stream_get_meta_data($source);
                    if (!empty($meta['timed_out'])) {
                        throw new waException(_wp('The Wildberries image download timed out.'));
                    }
                    continue;
                }
                $bytes += strlen($chunk);
                if ($bytes > self::MAX_DOWNLOAD_BYTES) {
                    throw new waException(_wp('The Wildberries image is too large.'));
                }
                $length = strlen($chunk);
                $written = 0;
                while ($written < $length) {
                    $count = fwrite($target, substr($chunk, $written));
                    if ($count === false || $count === 0) {
                        throw new waException(_wp('Unable to write a temporary image file.'));
                    }
                    $written += $count;
                }
            }
        } catch (Throwable $e) {
            fclose($source);
            fclose($target);
            $this->deleteTempFile($path);
            throw $e;
        }
        fclose($source);
        fclose($target);
        if ($bytes <= 0) {
            $this->deleteTempFile($path);
            throw new waException(_wp('Wildberries returned an empty image.'));
        }
        return $path;
    }

    private function validateForImport($path)
    {
        $info = @getimagesize($path);
        if (!$info || empty($info[0]) || empty($info[1])) {
            throw new waException(_wp('Wildberries returned an invalid image file.'));
        }
        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width * $height > self::MAX_SOURCE_PIXELS) {
            throw new waException(_wp('The Wildberries image dimensions are too large.'));
        }
        $extension = $this->extensionFromType((int) ifset($info[2], 0));
        if ($extension === '') {
            throw new waException(_wp('The Wildberries image format is not supported.'));
        }
        $adapter = $this->getSelectedAdapter();
        $bytes_per_pixel = $adapter === waImage::Imagick
            ? self::IMAGICK_BYTES_PER_PIXEL
            : self::GD_BYTES_PER_PIXEL;
        if (!$this->hasMemoryFor($width * $height * $bytes_per_pixel)) {
            throw new waException(_wp('Not enough PHP memory to process a Wildberries image.'));
        }
        return array(
            'adapter'   => $adapter,
            'extension' => $extension,
            'width'     => $width,
            'height'    => $height,
        );
    }

    private function getSelectedAdapter()
    {
        $selected = trim((string) waSystemConfig::systemOption('image_adapter'));
        $adapter = $this->normalizeSelectedAdapter($selected);
        if ($adapter === '') {
            throw new waException(_wp('The selected Webasyst image adapter is not supported.'));
        }

        if ($adapter === waImage::Imagick) {
            if (!extension_loaded('imagick') || !class_exists('Imagick')) {
                throw new waException(_wp('The selected Webasyst image adapter is not available.'));
            }
            return $adapter;
        }
        if ($adapter === waImage::Gd) {
            if (!extension_loaded('gd') || !function_exists('gd_info')) {
                throw new waException(_wp('The selected Webasyst image adapter is not available.'));
            }
            return $adapter;
        }

        throw new waException(_wp('The selected Webasyst image adapter is not supported.'));
    }

    private function normalizeSelectedAdapter($adapter)
    {
        $adapter = strtolower(trim((string) $adapter));
        if ($adapter === strtolower(waImage::Gd) || $adapter === 'gd2') {
            return waImage::Gd;
        }
        if ($adapter === strtolower(waImage::Imagick) || $adapter === 'imagemagick') {
            return waImage::Imagick;
        }
        return '';
    }

    private function hasTimeForAttempt($deadline)
    {
        if ($deadline === null || !is_numeric($deadline) || (float) $deadline <= 0) {
            return true;
        }
        return microtime(true) + self::HTTP_TIMEOUT_SECONDS + self::DEADLINE_RESERVE_SECONDS < (float) $deadline;
    }

    private function hasMemoryFor($estimated)
    {
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        if ($limit <= 0) {
            return true;
        }
        return memory_get_usage(true) + $estimated + self::MEMORY_RESERVE_BYTES < $limit;
    }

    private function parseMemoryLimit($value)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        if ($unit === 'g') {
            $number *= 1024;
            $unit = 'm';
        }
        if ($unit === 'm') {
            $number *= 1024;
            $unit = 'k';
        }
        if ($unit === 'k') {
            $number *= 1024;
        }
        return (int) $number;
    }

    private function extensionFromType($type)
    {
        $map = array(
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_GIF  => 'gif',
        );
        if (defined('IMAGETYPE_WEBP')) {
            $map[IMAGETYPE_WEBP] = 'webp';
        }
        return isset($map[$type]) ? $map[$type] : '';
    }

    private function isAllowedUrl($url)
    {
        $parts = @parse_url(trim((string) $url));
        if (!is_array($parts) || strtolower((string) ifset($parts['scheme'], '')) !== 'https') {
            return false;
        }
        $host = strtolower(rtrim((string) ifset($parts['host'], ''), '.'));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        foreach (array('wbbasket.ru', 'wb.ru', 'wildberries.ru', 'wbstatic.net') as $suffix) {
            if ($host === $suffix || substr($host, -strlen('.'.$suffix)) === '.'.$suffix) {
                return true;
            }
        }
        return false;
    }

    private function normalizeUrl($url)
    {
        return trim((string) $url);
    }

    private function decodeJson($value)
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function deleteTempFile($path)
    {
        if (is_string($path) && $path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    private function logError($message, array $context)
    {
        if ($this->logger && method_exists($this->logger, 'logError')) {
            $this->logger->logError($message, $context);
            return;
        }
        waLog::log('[WbImageWorker] '.$message.' '.json_encode($context), 'shop/plugins/migrate/migrate_wb.log');
    }
}
