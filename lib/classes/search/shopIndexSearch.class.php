<?php

class shopIndexSearch extends shopSearch
{

    protected static $index_model;
    protected static $word_model;

    public function __construct($options = array())
    {
        parent::__construct($options);
        $this->options['weights'] = wa('shop')->getConfig()->getOption('search_weights');
        if (!$this->options['weights']) {
            $this->options['weights'] = array();
        }
        $this->options['ignore'] = wa('shop')->getConfig()->getOption('search_ignore');
        if ($this->options['ignore']) {
            $_tmp = array();
            $n = mb_strlen($this->options['ignore']);
            for ($i = 0; $i < $n; $i++) {
                $_tmp[] = mb_substr($this->options['ignore'], $i, 1);
            }
            $this->options['ignore'] = $_tmp;
        }
        $this->options['by_part'] = wa('shop')->getConfig()->getOption('search_by_part');
    }

    public function onAdd($product_id)
    {
        $this->indexProduct($product_id, false);
    }

    public function onUpdate($product_id)
    {
        $this->indexProduct($product_id);
    }

    /**
     * @return shopSearchIndexModel
     */
    protected function getIndexModel()
    {
        if (!self::$index_model) {
            self::$index_model = new shopSearchIndexModel();
        }
        return self::$index_model;
    }

    /**
     * @return shopSearchWordModel
     */
    protected function getWordModel()
    {
        if (!self::$word_model) {
            self::$word_model = new shopSearchWordModel();
        }
        return self::$word_model;
    }

    public function indexProduct($product_id, $update = true)
    {
        $index_model = $this->getIndexModel();
        $word_model = $this->getWordModel();

        if (is_array($product_id)) {
            $p = $product_id;
            $product_id = $p['id'];
        } else {
            $p = new shopProduct($product_id);
        }



        $index = array();
        foreach (array('name', 'summary', 'description') as $k) {
            $v = $p[$k];
            if ($k != 'name') {
                $v = strip_tags(str_replace('><', '> <', $v));
            }
            if ($v) {
                $this->addToIndex($index, $v, $k);
            }
        }
        // skus
        if (isset($p['skus'])) {
            foreach ($p['skus'] as $sku) {
                if ($sku['sku']) {
                    $this->addToIndex($index, $sku['sku'], 'sku', false, true);
                }
                if ($sku['name']) {
                    $this->addToIndex($index, $sku['name'], false, true, true);
                }
            }
        }

        if (!empty($p['type_id'])) {
            $this->addToIndex($index, isset($p['type_name']) ? $p['type_name'] : $p['type']['name'], 'type');
        }
        if (!empty($p['tags'])) {
            $this->addToIndex($index, $p['tags'], 'tag', false);
        }
        if (!empty($p['features'])) {
            $this->addToIndex($index, $p['features'], 'feature');
        }

        // delete old index
        if ($update) {
            $index_model->deleteByField('product_id', $product_id);
        }

        // save new data
        if ($index) {
            $data = array();
            foreach ($index as $word_id => $weight) {
                $data[] = array(
                    'product_id' => $p['id'],
                    'word_id' => $word_id,
                    'weight' => $weight
                );
            }
            $index_model->multipleInsert($data);
        }
    }

    protected function addToIndex(&$index, $strings, $type, $split = true, $set_max_for_existed = false)
    {
        $temp_index = array();
        $weight = $this->getWeight($type);
        if (!$weight) {
            return;
        }
        foreach ((array)$strings as $string) {
            if (is_array($string)) {
                $this->addToIndex($index, $string, $type, $split);
                continue;
            }
            $words = $this->getWordIds($string);
            if (!$split && count($words) > 1) {
                $index_weight = max(round($weight / count($words)), $weight * 0.35);
                $this->addWordToIndex($temp_index, $this->getWordModel()->getId($string), $weight);
            } else {
                $index_weight = $weight;
            }
            foreach ($words as $word_id) {
                $this->addWordToIndex($temp_index, $word_id, $index_weight, true);
            }
        }
        foreach ($temp_index as $word_id => $weight) {
            $this->addWordToIndex($index, $word_id, $weight, $set_max_for_existed);
        }
    }

    protected function addWordToIndex(&$index, $word_id, $weight, $set_max_for_existed = false)
    {
        if (isset($index[$word_id])) {
            if ($set_max_for_existed) {
                $index[$word_id] = max($index[$word_id], $weight);
            } else {
                $index[$word_id] += $weight;
            }
        } else {
            $index[$word_id] = $weight;
        }
    }

    public function getWordIds($string, $only_exist = false)
    {
        $words = preg_split("/([\s,;:]+|[\.!\?](\s+|$))/su", $string, null, PREG_SPLIT_NO_EMPTY);
        $additional_words = array();
        foreach ($words as $i => $w) {
            if ($this->options['ignore']) {
                $clear_w = str_replace($this->options['ignore'], '', $w);
            } else {
                $clear_w = $w;
            }
            if ($clear_w) {
                $words[$i] = mb_strtolower($clear_w);
                if ($word_forms = $this->getWordForms($words[$i], $only_exist)) {
                    $additional_words = array_merge($additional_words, $word_forms);
                }
            } else {
                unset($words[$i]);
            }
        }
        if ($additional_words) {
            $words = array_merge($words, $additional_words);
        }
        $words = array_unique($words);

        $result = array();
        $word_model = $this->getWordModel();
        if ($only_exist) {
            return $word_model->getIds($words, $this->options['by_part']);
        }
        foreach ($words as $w) {
            if ($w) {
                if ($w_id = $word_model->getId(shopSearch::stem($w))) {
                    $result[] = $w_id;
                }
            }
        }
        return array_unique($result);
    }

    protected function getWordForms($word, $search = false)
    {
        $result = array();
        if (preg_match("/[0-9]/", mb_substr($word, 0, 1))) {
            return $result;
        }
        if (strpbrk($word, '/.!?|<>[]«»()-') !== false) {
            $result = preg_split("/[\/\.!\?|<>\[\]«»\(\)-]/u", $word, null, PREG_SPLIT_NO_EMPTY);
            if ($result) {
                $n = count($result);
                $w = "";
                for ($i = 0; $i < $n; $i++) {
                    $result[$i] = $this->options['ignore'] ? str_replace($this->options['ignore'], '', $result[$i]) : $result[$i];
                    $w .= $result[$i];
                    if ($w) {
                        $result[] = $w;
                    }
                }
            }
        }

        if (preg_match_all('/[0-9]+/is', $word, $matches)) {
            foreach ($matches[0] as $w) {
                $result[] = $w;
            }
        }
        if (preg_match_all('/[^0-9]+[0-9]+/is', $word, $matches)) {
            foreach ($matches[0] as $w) {
                $result[] = $w;
            }
        }
        return $result;
    }

    protected function getWeight($type)
    {
        if (isset($this->options['weights'][$type])) {
            return $this->options['weights'][$type];
        } elseif (isset($this->options['weights']['other'])) {
            return $this->options['weights']['other'];
        } else {
            return 0;
        }
    }

    public function onDelete($product_id)
    {
        $index_model = new shopSearchIndexModel();
        $index_model->deleteByField('product_id', $product_id);
    }

    public function search($query)
    {
        $word_model = new shopSearchWordModel();
        $word_ids = $word_model->getByString($query);
        $result = array();
        $result['joins'] = array(
            array(
                'table' => 'shop_search_index',
                'alias' => 'si'
            )
        );
        $result['where'] = array('si.word_id IN ('.implode(",", $word_ids).')');
        if (count($word_ids) > 1) {
            $result['fields'] = array("SUM(si.weight) AS weight");
            $result['order_by'] = 'weight DESC';
            $result['group_by'] = 'p.id';
        } else {
            $result['fields'] = array("si.weight");
            $result['order_by'] = 'si.weight DESC';
        }
        return $result;
    }
}