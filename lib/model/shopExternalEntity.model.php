<?php
/**
 * Links Shop-Script entities (products, SKUs, categories, orders) to external systems
 * such as marketplaces or stock-keeping software.
 */
class shopExternalEntityModel extends waModel
{
    protected $table = 'shop_external_entity';

    const ENTITY_TYPE_PRODUCT = 'product';
    const ENTITY_TYPE_SKU = 'sku';
    const ENTITY_TYPE_CATEGORY = 'category';
    const ENTITY_TYPE_ORDER = 'order';

    /**
     * Returns external entities for the given entity type and entity ids.
     *
     * Result is keyed by entity_id, each entity_id maps to a list of external-entity rows:
     *   entity_id => [
     *       ['entity_id', 'plugin', 'external_id', 'channel_id', 'data', 'url_template'],
     *       ...
     *   ]
     *
     * The 'data' column is JSON and is decoded into an array when it holds valid JSON.
     *
     * @param string $entity_type
     * @param int[] $entity_ids List of product/sku/order/category ids
     * @param bool $return_full_rows true for full table rows; false to return essential only
     * @return array
     */
    public function getByEntity(string $entity_type, array $entity_ids, bool $return_full_rows=false)
    {
        $entity_ids = array_map('intval', (array)$entity_ids);
        $entity_ids = array_values(array_filter($entity_ids));
        if (!$entity_type || !$entity_ids) {
            return array();
        }

        $rows = $this->getByField([
            'entity_type' => $entity_type,
            'entity_id' => $entity_ids,
        ], true);

        $result = array();
        foreach ($rows as $row) {
            if (!empty($row['data'])) {
                $decoded = json_decode($row['data'], true);
                if (is_array($decoded)) {
                    $row['data'] = $decoded;
                }
            }
            if (!$return_full_rows) {
                $row = array_intersect_key($row, [
                    'entity_id'=>1, 'plugin'=>1, 'external_id'=>1, 'channel_id'=>1, 'data'=>1, 'url_template'=>1,
                ]);
            }
            $result[$row['entity_id']][] = $row;
        }

        return $result;
    }

    /**
     * Renders an external URL from a link row's 'url_template'.
     *
     * Supported tokens: {prefix.key} (exact, case-sensitive, no whitespace). Prefixes map to
     * rows passed in $vars: 'p' (product), 's' (sku), 'o' (order), 'c' (category); 'link' always
     * resolves to the trimmed link row (plugin, external_id, channel_id, entity_id, data).
     * 'url_template' itself is not addressable. {link.data.*} follows the decoded JSON data to
     * arbitrary depth; an unresolved {link.data.*} token is replaced with '', any other
     * unresolved token is left literal. Substituted values are inserted raw (no URL encoding).
     *
     * @param array $link_row External-entity row, expected to hold a 'url_template' key
     * @param array $vars Maps prefixes to source rows, e.g. ['p' => $product, 's' => $sku]
     * @return string|null null when url_template is null/empty, otherwise the rendered string
     */
    public function buildExternalUrl(array $link_row, array $vars = array()): ?string
    {
        $url_template = ifset($link_row, 'url_template', '');
        if ($url_template === null || $url_template === '') {
            return null;
        }

        // link.* = trimmed row only; url_template is intentionally not addressable
        $link_vars = array_intersect_key($link_row, [
            'plugin' => 1, 'external_id' => 1, 'channel_id' => 1, 'entity_id' => 1,
        ]);
        if (is_array(ifset($link_row, 'data', null))) {
            $link_vars['data'] = $link_row['data'];
        }

        // Sentinel distinguishing "resolved to null" from "unresolved (missing key)"
        $unresolved = new stdClass();
        $resolve = function (string $prefix, array $path) use ($vars, $link_vars, $unresolved) {
            switch ($prefix) {
                case 'p': $src = ifset($vars, 'p', null); break;
                case 's': $src = ifset($vars, 's', null); break;
                case 'o': $src = ifset($vars, 'o', null); break;
                case 'c': $src = ifset($vars, 'c', null); break;
                case 'link': $src = $link_vars; break;
                default: return $unresolved;
            }
            if (!is_array($src)) {
                return $unresolved;
            }
            $value = $src;
            foreach ($path as $key) {
                if (is_array($value) && array_key_exists($key, $value)) {
                    $value = $value[$key];
                } else {
                    return $unresolved;
                }
            }
            return $value;
        };

        return preg_replace_callback('/\{([a-zA-Z_]+)((?:\.[a-zA-Z0-9_]+)*)\}/', function ($m) use ($resolve, $unresolved) {
            $prefix = $m[1];
            $path = $m[2] === '' ? [] : explode('.', substr($m[2], 1));
            $value = $resolve($prefix, $path);
            if ($value === $unresolved) {
                // {link.data.*} that cannot be resolved -> empty string
                if ($prefix === 'link' && !empty($path) && $path[0] === 'data') {
                    return '';
                }
                // any other unresolved token -> left literal
                return $m[0];
            }
            if ($value === null) {
                return '';
            }
            if (is_scalar($value)) {
                return (string)$value;
            }
            // non-scalar (array/object) cannot be embedded in a URL
            return '';
        }, $url_template);
    }
}
