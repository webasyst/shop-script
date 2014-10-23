<?php

class shopSearchWordModel extends waModel
{
    protected $table = 'shop_search_word';
    protected static $words_cache = array();

    /**
     * @param string $word
     * @return int
     */
    public function getId($word)
    {
        if (isset(self::$words_cache[$word])) {
            return self::$words_cache[$word];
        }
        if (mb_strlen($word) > 255) {
            $word = mb_strlen($word, 0, 255);
        }
        $sql = "SELECT * FROM ".$this->table." WHERE name LIKE '".$this->escape($word, 'like')."'";
        $row = $this->query($sql)->fetch();
        if ($row) {
            $id = $row['id'];
        } else {
            $id = $this->insert(array('name' => $word), 2);
        }
        if (count(self::$words_cache) > 5000) {
            self::$words_cache = array();
        }
        if ($id) {
            self::$words_cache[$word] = $id;
        }
        return $id;
    }

    public function getIds($words, $by_part = 0)
    {
        $where = array();
        foreach ($words as $w) {
            $w = trim($w);
            if ($w) {
                $w = shopSearch::stem($w);
                $where[] = "name LIKE '".$this->escape($w, 'like').($by_part && mb_strlen($w) >= $by_part ? '%' : '')."'";
            }
        }
        if ($where) {
            $sql = "SELECT id FROM ".$this->table." WHERE ".implode(' OR ', $where);
            return $this->query($sql)->fetchAll(null, true);
        }
        return array();
    }

}