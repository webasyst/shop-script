<?php

class shopSearch
{
    protected $options;

    public function __construct($options = array())
    {
        $this->options = $options;
    }

    public function onAdd($product_id)
    {
        // nothing
    }

    public function onUpdate($product_id)
    {
        // nothing
    }

    public function onDelete($product_id)
    {
        // nothing
    }

    public function search($query)
    {
        $model = new waModel();
        return array(
            'where' => array("p.name LIKE '".$model->escape($query, 'like')."'")
        );
    }

    public static function stem($word)
    {
        if (preg_match('/^[а-я]+$/ui', $word)) {
            return shopStemmerRU::stem($word);
        }  elseif (preg_match('/^[a-z]+$/i', $word)) {
            return shopStemmerEN::stem($word);
        } else {
            return $word;
        }
    }
}
