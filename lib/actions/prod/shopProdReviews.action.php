<?php
/**
 * /products/<id>/reviews/
 */
class shopProdReviewsAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::param('id', '', 'int');
        $product = new shopProduct($product_id);
        if (!$product['id']) {
            throw new waException(_w("Unknown product"), 404);
        }

        $sort = waRequest::request('sort', "datetime", waRequest::TYPE_STRING);
        $page = waRequest::request('page', 1, waRequest::TYPE_INT);
        $limit = self::getLimit();
        $order = waRequest::request('order', 'DESC', waRequest::TYPE_STRING);
        $offset = waRequest::request('offset', 0, waRequest::TYPE_INT);
        $filters = waRequest::request('filters', [], waRequest::TYPE_ARRAY);
        $status = (!empty($filters["status"]) ? $filters["status"] : null);
        $images_count = self::getImagesCount($filters);
        $frontend_urls = shopProdGeneralAction::getFrontendUrls($product)[0];

        foreach ($filters as $id => $filter) {
            /** фильтр "все" равнозначен его отсутствию */
            if ($filter === 'all') {
                unset($filters[$id]);
            }
        }
        $options = [
            "offset" => $offset,
            "limit"  => $limit,
            "sort"   => $sort,
            "order"  => $order,
            "where"  => [
                "product_id" => $product["id"],
                "filters"    => $filters
            ]
        ];
        list($reviews_count, $reviews) = self::getReviews($options);
        if ($limit > $reviews_count) {
            $limit = self::getFormattedLimit($reviews_count);
        }

        $this->view->assign([
            'frontend_urls'     => $frontend_urls,
            'product'           => $product,
            'formatted_product' => self::formatProduct($product),
            "reviews"           => $reviews,
            'reviews_count'     => $reviews_count,
            "filters"           => self::getFilters($reviews_count),
            "active_filters"    => [
                "page"         => $page,
                "limit"        => (string)$limit,
                "sort"         => $sort,
                "order"        => $order,
                "sort_order"   => (!empty($sort) && !empty($order) ? mb_strtolower($sort."_".$order) : null),
                "status"       => $status,
                "images_count" => $images_count
            ],
            "pagination" => [
                "page"  => $page,
                "pages" => ceil($reviews_count/$limit)
            ],
            "review_states" => [
                "deleted"    => shopProductReviewsModel::STATUS_DELETED,
                "published"  => shopProductReviewsModel::STATUS_PUBLISHED,
                "moderation" => shopProductReviewsModel::STATUS_MODERATION
            ]
        ]);

        $this->setLayout(new shopBackendProductsEditSectionLayout([
            'product' => $product,
            'content_id' => 'reviews',
        ]));
    }

    protected function formatProduct($product)
    {
        return [
            "id" => $product["id"],
        ];
    }

    /**
     * @param $options
     * @return array|mixed
     * @throws waException
     */
    protected function getReviews($options)
    {
        $filters = ifset($options, 'where', 'filters', 'status', '');
        $offset  = $options['offset'];
        $limit   = $options['limit'];
        unset($options['where']['filters']['status'], $options['limit'], $options['offset']);

        $product_reviews_model = new shopProductReviewsModel();
        $reviews = $product_reviews_model->getList('*,is_new,contact,product,images', $options);
        $product_reviews_model->checkForNew($reviews);
        $reviews = array_values($reviews);
        $reviews = self::treeComments($reviews, $filters);
        $count   = count($reviews);
        $reviews = array_slice($reviews, (int) $offset, $limit);
        foreach ($reviews as &$review) {
            $review = self::formatReview($review);
        }

        return [$count, $reviews];
    }

    /**
     * Формирование дерева комментариев из 'плоского'
     * массива отзывов и комментариев
     *
     * @param array $reviews
     * @param array $status
     * @return array|mixed
     */
    private function treeComments($reviews = [], $status = '')
    {
        $filtered = [];
        foreach ($reviews as &$review) {
            $review['reviews'] = [];
            if (!empty($status) && $review['status'] === $status) {
                $filtered[] = $review['review_id'];
            }
        }
        $filtered = array_unique($filtered);
        if (!empty($status)) {
            foreach ($reviews as $id => &$review) {
                if (!in_array($review['review_id'], $filtered) && !in_array($review['id'], $filtered)) {
                    unset($reviews[$id]);
                }
            }
        }
        foreach ($reviews as $id => &$review) {
            if ($review['parent_id'] === '0') {
                continue;
            } else {
                /** распихиваем комментарии в под комментарии, а их в отзывы */
                foreach ($reviews as &$sub_review) {
                    if ($sub_review['id'] === $review['parent_id']) {
                        $sub_review['reviews'][] = &$review;
                    }
                }
            }
        }
        foreach (array_keys($reviews) as $id) {
            if (
                !empty($status)
                && empty($reviews[$id]['reviews'])
                && $reviews[$id]['status'] !== $status
            ) {
                /** при фильтрации удаляем отзывы с пустыми комментариями */
                unset($reviews[$id]);
            } elseif ($reviews[$id]['parent_id'] !== '0') {
                /** оставляем только родительские отзывы */
                unset($reviews[$id]);
            }
        }

        return array_values($reviews);
    }

    public static function formatReview($review)
    {
        $_inner_reviews = [];
        if (!empty($review["reviews"])) {
            foreach ($review["reviews"] as $_inner_review) {
                $_inner_reviews[] = self::formatReview($_inner_review);
            }
        }

        // show rating (stars) only for top-level reviews
        $show_rate = empty($review["parent_id"]);

        $review = [
            "id"            => $review["id"],
            "author"        => $review["author"],
            "datetime"      => $review["datetime"],
            "humandatetime" => waDateTime::format( 'humandatetime', $review["datetime"] ),
            "is_new"        => $review["is_new"],
            "rate"          => (!empty($review["rate"]) ? (float)$review["rate"] : null),
            "show_rate"     => $show_rate,
            "status"        => $review["status"],
            "name"          => $review["name"],
            "text"          => nl2br($review["text"]),
            "title"         => $review["title"],
            "images"        => self::formatImages($review["images"]),
            "reviews"       => $_inner_reviews,

            "site"          => ifset($review, "site", null),
            "contact_id"    => (!empty($review["contact_id"]) ? $review["contact_id"] : null),

            // VUE model
            "status_model"  => ( $review["status"] === shopProductReviewsModel::STATUS_PUBLISHED ),
            "disabled"      => ( $review["status"] === shopProductReviewsModel::STATUS_DELETED )
        ];

        return $review;
    }

    protected static function formatImages($images = [])
    {
        $result = [];

        if (!empty($images)) {
            $config = wa('shop')->getConfig();
            foreach ($images as $_image) {
                $result[] = [
                    "id"    => $_image["id"],
                    "title" => $_image["description"],
                    "url"   => shopImage::getUrl($_image, $config->getImageSize('big')),
                    "thumb" => shopImage::getUrl($_image, $config->getImageSize('thumb'))
                ];
            }
        }

        return $result;
    }

    protected function getFilters($reviews_count)
    {
        $_sort_filter = [
            [
                "id" => "rate_asc",
                "sort" => "rate",
                "order" => "ASC",
                "name" => _w("Rating, ascending")
            ],
            [
                "id" => "rate_desc",
                "sort" => "rate",
                "order" => "DESC",
                "name" => _w("Rating, descending")
            ],
            [
                "id" => "datetime_asc",
                "sort" => "datetime",
                "order" => "ASC",
                "name" => _w("Date, ascending")
            ],
            [
                "id" => "datetime_desc",
                "sort" => "datetime",
                "order" => "DESC",
                "name" => _w("Date, descending")
            ]
        ];

        $_image_filter = [
            [
                "id" => "all",
                "name" => _w("all")
            ],
            [
                "id" => "0",
                "name" => _w("without photos")
            ],
            [
                "id" => "1",
                "name" => _w("with photos")
            ]
        ];

        $_deleted = shopProductReviewsModel::STATUS_DELETED;
        $_published = shopProductReviewsModel::STATUS_PUBLISHED;
        $_moderation = shopProductReviewsModel::STATUS_MODERATION;

        $_status_filter = [
            [
                "id" => "all",
                "name" => _w("all")
            ],
            [
                "id" => $_deleted,
                "name" => _w("deleted ones")
            ],
            [
                "id" => $_published,
                "name" => _w("published")
            ],
            [
                "id" => $_moderation,
                "name" => _w("pending moderation")
            ]
        ];

        $_limit_filter = [
            [
                "id" => "10",
                "name" => "10"
            ]
        ];

        if ($reviews_count > 10) {
            $_limit_filter[] = [
                "id" => "20",
                "name" => "20"
            ];
        }

        if ($reviews_count > 20) {
            $_limit_filter[] = [
                "id" => "30",
                "name" => "30"
            ];
        }

        if ($reviews_count > 30) {
            $_limit_filter[] = [
                "id" => "50",
                "name" => "50"
            ];
        }

        return [
            "sort"         => $_sort_filter,
            "limit"        => $_limit_filter,
            "status"       => $_status_filter,
            "images_count" => $_image_filter
        ];
    }

    protected function getImagesCount($filters) {
        $result = null;

        if (isset($filters["images_count"])) {
            $count = $filters["images_count"];
            switch ($count) {
                case "0":
                case "1":
                    $result = $count;
                    break;
            }
        }

        return $result;
    }

    protected function getLimit() {
        $_limit_config = $this->getConfig()->getOption('reviews_per_page_total');
        $_limit_default = (!empty($_limit_config) ? $_limit_config : 30);
        $limit = waRequest::request('limit', $_limit_default, waRequest::TYPE_INT);
        $limit = self::getFormattedLimit($limit);

        return $limit;
    }

    protected function getFormattedLimit($limit) {
        $result = 10;

        if ($limit > 10 && $limit <= 20) { $result = 20; }
        else if ($limit > 20 && $limit <= 30) { $result = 30; }
        else if ($limit > 30) { $result = 50; }

        return $result;
    }
}
