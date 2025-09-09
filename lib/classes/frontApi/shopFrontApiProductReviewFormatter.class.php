<?php
class shopFrontApiProductReviewFormatter extends shopFrontApiFormatter
{
    public function format(array $review)
    {
        $review['author_name'] = ifset($review, 'name', ifset($review, 'author', 'name', null));
        $review['author_photo_url_20'] = self::urlToAbsolute(ifset($review, 'author', 'photo_url_20', null));
        $review['author_photo_url_50'] = self::urlToAbsolute(ifset($review, 'author', 'photo_url_50', null));
        $review['comments'] = ifset($review, 'comments', []);

        if ($review['comments']) {
            foreach ($review['comments'] as $i => &$c) {
                if (isset($this->options['max_depth']) && $this->options['max_depth'] >= 0) {
                    if ($c['depth'] > $this->options['max_depth']) {
                        unset($review['comments'][$i]);
                        continue;
                    }
                }
                $c = $this->format($c);
            }
            unset($c);
            if (!empty($this->options['comments_as_tree'])) {
                $review['comments'] = $this->buildTree($review['comments']);
            }
        }

        if ($review['images']) {
            $review['images'] = array_map([$this, 'formatImage'], $review['images']);
        }

        $schema = [
            'id' => 'integer',
            'parent_id' => 'integer',
            'datetime' => 'string',
            'depth' => 'integer',
            'title' => 'string',
            'text' => 'string',
            'rate' => 'number',
            //'images_count' => 'integer',
            'images' => 'array',
            'author_name' => 'string',
            'author_photo_url_20' => 'string',
            'author_photo_url_50' => 'string',
            'comments' => 'array',
        ];

        if ($review['parent_id'] > 0) {
            unset($schema['rate']);
        }

        $review = self::formatFieldsToType($review, $schema);
        return array_intersect_key($review, $schema);
    }

    public function formatImage($img)
    {
        $schema = [
            'id' => 'integer',
            'description' => 'string',
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
            'original_filename' => 'string',
            'url_0' => 'string',
            'url_1' => 'string',
            'url_2' => 'string',
            'url_3' => 'string',
            'url_4' => 'string',
            'url_5' => 'string',
            'url_6' => 'string',
        ];

        for ($i = 0; $i < 7; $i++) {
            $key = 'url_'.$i;
            if (!empty($img[$key])) {
                $img[$key] = self::urlToAbsolute($img[$key]);
            }
        }

        $img = self::formatFieldsToType($img, $schema);
        return array_intersect_key($img, $schema);
    }

    protected function buildTree($comments)
    {
        $top_level = [];
        foreach ($comments as &$c) {
            if (!isset($c['comments'])) {
                $c['comments'] = [];
            }
            if (isset($comments[$c['parent_id']])) {
                $comments[$c['parent_id']]['comments'][] = $c;
            } else {
                $top_level[$c['id']] = $c;
            }
        }
        unset($c);
        return array_intersect_key($comments, $top_level);
    }
}
