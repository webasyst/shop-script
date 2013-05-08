<?php 
// * * * * *	cd $HOME/top-trends.ru/docs/ && /opt/php/bin/php -c $HOME/etc/php.ini $HOME/top-trends.ru/docs/cli.php shop Asiaimport  >/dev/null 2>&1
class shopAsiaimportCli extends waCliController
{
    // Путь к файлам
    protected function path($file = 'asiaimport.php')
    {
        return wa()->getDataPath('plugins/asiaimport/'.$file, false, 'shop', true);
    }
    
    // Список изменяемых категорий
    protected function get_categories($categories, $from_shop = true, $implode = ',')
    {
        $categories = ( $from_shop ? array_keys($categories) : array_values($categories) );
        $categories = array_unique($categories);
        return ( empty($implode) ? $categories : implode($implode, $categories) );
    }
    
    // Перевод строки en->ru
    protected function translate($text)
    {
        $yt = new YandexTranslate();
        return $yt->translate('en', 'ru', $text);
    }
    
    // Шаг 1: Загрузка и подготовка прайса
    protected function step_1($url)
    {
        if ( $data = @file_get_contents($url) )
        {
            echo $this->path();
            $data = str_replace( array('</td> <td>', '<tr><td>', '</td> </tr>', " bgcolor='#EEF0EE'", "<a target='_blank' href='", '</a>'), array(';', '', PHP_EOL, '', '', ''), $data);
            $data = str_replace(array('/welcome/', "<tr><td> ", "'>", 'In stock', "<font color='#FF0000;[ Restocking ]</font>", '<tr ><td> ',' # '), array('', '', ';', '10', '0', '', '#'), $data);
            preg_match('~USD Price; Stock status; Size; Name(.*,?)<\/table><br\/>~is', $data, $matches);
            if ( @file_put_contents($this->path(), $matches[1]) )
            {
                return true;
            }
        }
    }
    
    // Шаг 2: Создание массива категорий и товаров
    protected function step_2($categories, $cfg_cats)
    {
        // 0 Product ID 1 Category ID 2 Product Code 3 USD Price 4 Stock status 5 Size 6 Url 7 Name
        $data = array();
        $f = @fopen($this->path(), 'r');
        if ($f)
        {
            while (!feof($f))
            {
                $str = fgetcsv($f, 1024, ';');
                if (count($str) >= 7)
                {
                    $category = trim($str[1]);
                    if (array_search($category, $categories) !== false)
                    {
                        // добавляем категорию
                        $str = array_map('trim', $str);
                        if (!isset($data[$category]))
                        {
                            $data[$category] = array();
                        }
                        // добавляем товар
                        $product = str_replace('#', '_', $str[7]);
                        $product = shopHelper::transliterate($product);
                        $product = str_replace('---', '-', $product);
                        if (!isset($data[$category][$product]))
                        {
                            $data[$category][$product] = array($str[7], array());
                        }
                        // Добавляем только варианты в наличии
                        $count = (int)$str[4];
                        if ($count>0)
                        {
                            $id = (int)$str[0];
                            // 2 Product Code 3 USD Price 4 Stock status 5 Size 6 Url
                            $data[$category][$product][1][$id] = array($str[2], (float)$str[3], $count, $str[5], $str[6]);
                        }
                    }
                }
            }
            fclose($f);
            
            // Сохраняем товары по категориям(для каждой категории свой файл)
            foreach ($data as $category_id => $category_data)
            {
                // Удаляем товары не в наличии
                foreach ($category_data as $product_id => $product_data)
                {
                    if (count($product_data[1]) == 0)
                    {
                        unset($data[$category_id][$product_id]);
                        unset($category_data[$category_id][$product_id]);
                    }
                }
                $category_id = $cfg_cats[$category_id];
                waUtils::varExportToFile($category_data, $this->path("category_{$category_id}.php"));
            }
            return true;
        }
    }
    
    // Шаг 3: Обрабатываем категории циклом и формируем\сохраняем списки товаров(добавление\изменение)
    protected function step_3($category)
    {
        $category_file = $this->path("category_{$category}.php");
        if (file_exists($category_file))
        {
            $import_products = include_once($category_file);
            // Модель товара
            $m = new shopProductModel;
            // Товары категории
            $category_products = $m->select('id, url, status, contact_id')->where("category_id = {$category}")->fetchAll('url', true);
           
            // Формируем и сохраняем список добавляемых и изменяемых товаров
            $add    = array();
            $update = array();
            if (!empty($category_products))
            {
                foreach ($import_products as $url => $product)
                {
                    if (isset($category_products[$url]))
                    {
                        $id = $category_products[$url]['id'];
                        $update[$id] = $product;
                        // Удаляем найденный товар, чтобы он не попал под удаление в дальнейшем
                        unset($category_products[$url]);
                    }
                    else
                    {
                        $add[$url] = $product;
                    }
                }
            }
            else
            {
                $add = $import_products;
            }
            unset($import_products);
            if (!empty($add))
            {
                waUtils::varExportToFile($add, $this->path("add_{$category}.php"));
            }
            if (!empty($update))
            {
                waUtils::varExportToFile($update, $this->path("update_{$category}.php"));
            }
            
            // Удаляем или отключаем отсутствующие товары (за исключением созданных пользователями)
            $delete = array();
            foreach ($category_products as $product)
            {
                // Отключаем товар на первый раз, возможно он появится завтра
                if (!empty($product['status']) && empty($product['contact_id']))
                {
                    $m->updateById($product['id'], array('status' => 0));
                }
                // Добавляем в список удаляемых
                elseif (empty($product['status']) && empty($product['contact_id']))
                {
                    $delete[] = $product['id'];
                }
            }
            if (!empty($delete))
            {
                $m->delete($delete);
            }
            
            // Удаляем использованный файл категории
            unlink($category_file);
            return true;
        }
    }
    
     // Шаг 4: Обновление товаров
    protected function step_4($category, $markup, $currency)
    {
        $category_file = $this->path("update_{$category}.php");
        if (file_exists($category_file))
        {
            $import_products = include_once($category_file);
            // Модель товара
            $m = new shopProductModel;
            // Модель артикула товара
            $m_sky = new shopProductSkusModel();
            foreach ($import_products as $id => $product)
            {
                $upd_prd = $m->getById($id);
                $upd_prd_skys = $m_sky->getData($upd_prd);
                $data = array(
                    'edit_datetime' => date('Y-m-d H:i:s'),
                    'status'        => 1,
                    /*
                    type_id
                    image_id
                    sku_id
                    ext
                    price
                    compare_price
                    min_price
                    max_price
                    'count' => $updated_product->countProductStocks()
                    category_id
                    badge
                    */
                    'sku_type' => 0,
                );
                //$m->updateById($id, $data);
            }
            
            // Удаляем использованный файл категории
            unlink($category_file);
            return true;
        }
    }
    
 /*  
        $prd = new shopProduct(1);
        $m_sky = new shopProductSkusModel;
        print_r($prd);
        $skys = $m_sky->getData($prd);
        print_r($skys);
 
    $model->insert($data); // в случае auto_increment метод вернёт id вставленной записи
    $model->updateByField('contact_id', $contact_id, array('published' => true));
    $model->deleteByField($field, $value);
    $m->countByField('url', $url)
 
    $sku_model = new shopProductSkusModel();
    $sku_model->getByField('sku', $name)
 
 if ( $this->step2( $this->get_categories($cfg['categories']) ) ) $step++;
    // Шаг 2: Удаление неактивных, оключение активных товаров
    protected function step2($categories)
    {
        $m = new shopProductModel;
        // Удаление неактивных
        $ids = $m->select('id')->where("status = 0 AND contact_id IS NULL AND category_id IN ({$categories})")->fetchAll('id', true);
        $m->delete(array_keys($ids));
        // Оключение активных товаров
        $m->query("UPDATE `{$m->getTableName()}` SET status=0 WHERE status=1 AND contact_id IS NULL AND category_id IN ({$categories})");
        return true;
    }
*/
    
    /*
    
    $yt = new YandexTranslate();
    //Перевод
    $yt->eolSymbol = '';
    $translatedText = $yt->translate('en', 'ru', $text);
    
    fgetcsv($prd_file, 500, ';')
    fputcsv($new_prd_file, $fields, ';')
    
    0 Product ID
    1 Category ID
    2 Product Code
    3 USD Price
    4 Stock status
    5 Size
    6 Url
    7 Name
    
    protected function importProducts(SimpleXMLElement $shop)
    {
        $category_products_model = new shopCategoryProductsModel();
        foreach ($shop->offers->children() as $o) {
            $data = array(
                'name' => (string)$o->name,
                'description' => trim((string)$o->description),
                'price' => (string)$o->price,
                'url' => preg_replace("/^.*?product_slug=([^&]+).*?$/ui", "$1", (string)$o->url)
            );
            $product = new shopProduct();
            if ($product->save($data) && (int)$o->categoryId) {
                $category_products_model->add($product->getId(), (int)$o->categoryId);
            }
        }
    }
    
       
        http://asia-fashion-wholesale.com/welcome/fashion-dresses/prod_47091.html
        <strong>Product Information</strong>
            
            <br>
            Size:Only One Size
            <br>
            Flexibility:Yes
            <br>
            Other accessories:None
            </td>
            
        http://asia-fashion-wholesale.com/welcome/bigPhotos.php?productId=32045
        http://asia-fashion-wholesale.com/welcome/images/uploads/
        <img src="images/uploads/(.*)">
        
    */
    
    public function execute()
    {
        $app = wa()->getApp();
        $settings = new waAppSettingsModel();
        
        // Настройки импорта
        if ($cfg = $settings->get(array($app, 'asiaimport'), 'selected'))
        {
            $cfg = json_decode($cfg, true);
            // Удаление пустых категорий
            $cfg['categories'] = array_filter($cfg['categories']);
        }
        
        // Шаги импорта
        if (!$step = $settings->get(array($app, 'asiaimport'), 'step'))
        {
            $step = 1;
        }
        if (!$substep = $settings->get(array($app, 'asiaimport'), 'substep'))
        {
            $substep = 0;
        }
        
        // Импорт данных
        switch( $step )
        {
            // Шаг 1: Загрузка и подготовка файла прайса
            case 1:
                if ( $this->step_1($cfg['url']) ) $step++;
                break;
            // Шаг 2: Создание массива категорий и товаров
            case 2:
                if ($this->step_2($this->get_categories($cfg['categories'], false, null), array_flip($cfg['categories'])))
                {
                    $step++;
                    $substep = 0;
                }
                // В случае проблем возвращаемся к шагу 1
                else
                {
                    $step = 1;
                }
                break;
            //Шаг 3: Обрабатываем категории циклом и формируем\сохраняем списки товаров(добавление\изменение)
            case 3:
                $categories = $this->get_categories($cfg['categories'], true, null);
                if (isset($categories[$substep]))
                {
                    $this->step_3($categories[$substep]);
                    $substep++;
                }
                else
                {
                    $step++;
                    $substep = 0;
                }
                break;
            // Шаг 4: Обновление товаров
            case 4:
                $categories = $this->get_categories($cfg['categories'], true, null);
                if (isset($categories[$substep]))
                {
                    $this->step_4($categories[$substep], $cfg['markup'], $cfg['currency']);
                    $substep++;
                    
                    /*
                    while (!file_exists($category_file))
                    {
                        $substep++;
                        if (!isset($categories[$substep]))
                        {
                            $step++;
                            break;
                        }
                        else
                        {
                            $category_file = $this->path("update_{$categories[$substep]}.php");
                        }
                    }
                    if (isset($categories[$substep]))
                    {
                        $this->step_4($categories[$substep]);
                        $substep++;
                    }
                    else
                    {
                        $step++;
                        $substep = 0;
                    }
                    */
                }
                else
                {
                    $step++;
                    $substep = 0;
                }
                break;
            // Шаг 5: Добавление товаров
            case 5:
                $step++;
                break;
            // Шаг 6: Загрузка изображений добавленых товаров
            case 6:
                $step++;
                break;
            default:
                $step    = 1;
                $substep = 0;
        }
        
        // Сохраняем шаги
        $settings->set(array($app, 'asiaimport'), 'step', $step);
        $settings->set(array($app, 'asiaimport'), 'substep', $substep);
    }
    
}