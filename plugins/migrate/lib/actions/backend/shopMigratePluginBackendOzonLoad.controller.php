<?php

class shopMigratePluginBackendOzonLoadController extends waJsonController
{
    public function execute()
    {
        try {
            $settings = new shopMigratePluginOzonSettings();
            $credentials = $settings->getCredentials();
            if ($credentials['client_id'] === '' || $credentials['api_key'] === '') {
                throw new waException(_wp('Save Client ID and API Key first.'));
            }

            $logger = new shopMigratePluginOzonLogger($settings->getLogMode());
            $api = new shopMigratePluginOzonApiClient($credentials['client_id'], $credentials['api_key'], $logger);
            $repository = new shopMigratePluginOzonSnapshotRepository();
            $builder = new shopMigratePluginOzonSnapshotBuilder($api, $repository, $settings);
            $snapshot_id = $builder->build();
            $snapshot = $repository->getSnapshotsModel()->getByIdSafe($snapshot_id);
            $warning = $this->extractSnapshotWarning($snapshot);

            $this->response = array(
                'snapshot_id' => $snapshot_id,
            );
            if ($warning !== '') {
                $this->response['warning'] = $warning;
            }
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }

    private function extractSnapshotWarning($snapshot)
    {
        if (!$snapshot || empty($snapshot['meta'])) {
            return '';
        }
        $meta = json_decode($snapshot['meta'], true);
        if (!is_array($meta)) {
            return '';
        }
        $pairs = ifset($meta['invalid_attribute_pairs'], array());
        if (!$pairs || !is_array($pairs)) {
            return '';
        }

        $formatted = array();
        foreach ($pairs as $pair) {
            $path = trim((string) ifset($pair['path'], ''));
            if ($path === '') {
                $path = sprintf(
                    'category_id=%d, type_id=%d',
                    (int) ifset($pair['description_category_id']),
                    (int) ifset($pair['type_id'])
                );
            }
            $products_count = (int) ifset($pair['products_count'], 0);
            $formatted[] = sprintf('%s (%d)', $path, $products_count);
        }

        $total_products = (int) ifset($meta['invalid_attribute_products_count'], 0);
        $pairs_count = (int) ifset($meta['invalid_attribute_pairs_count'], count($pairs));
        $preview_items = array_slice($formatted, 0, 5);
        $preview = implode('; ', $preview_items);
        $rest = max(0, $pairs_count - count($preview_items));
        if ($rest > 0) {
            $preview .= sprintf('; and %d more', $rest);
        }

        return sprintf(
            'Ozon API returned category/type errors while loading attributes. %d category/type pairs (%d products) will be imported without characteristics. Affected categories: %s',
            $pairs_count,
            $total_products,
            $preview
        );
    }
}
