<?php

/**
 *
 *
 */
class shopCsvReader implements SeekableIterator, Serializable
{
    /**
     *
     * resource a file pointer
     * @var resouce
     */
    private $fp = null;
    private $fsize = null;
    private $header = null;

    protected $data_mapping = array();
    private $delimeter = ';';
    private $encoding;
    private $compression;
    private $header_count = 0;

    private $offset_map = array();
    private $current = null;
    private $mapped = false;
    private $key = 0;
    private $file;

    public function __construct($file, $delimeter = ';', $encoding = 'utf-8')
    {
        $this->file = ifempty($file);
        $this->delimeter = ifempty($delimeter, ';');
        if ($this->delimeter == 'tab') {
            $this->delimeter = "\t";
        }
        $this->encoding = ifempty($encoding, 'utf-8');
        $this->restore();
        waHtmlControl::registerControl('Csvmap', array($this, 'getCsvmapControl'));
    }

    private function open()
    {
        if ($this->file && file_exists($this->file)) {
            $extension = pathinfo($this->file, PATHINFO_EXTENSION);
            $file = basename($this->file, '.'.$extension);
            if (pathinfo($file, PATHINFO_EXTENSION) != 'csv') {
                $file .= '.csv';
            }
            $path = pathinfo($this->file, PATHINFO_DIRNAME).'/';
            $file = $path.$file;
            switch ($extension) {
                case 'gz':
                    if (extension_loaded('zlib')) {
                        if (function_exists('gzopen')) {
                            if (($src = gzopen($this->file, 'rb')) && ($dst = fopen($file, 'wb'))) {
                                stream_copy_to_stream($src, $dst);
                                gzclose($src);
                                fclose($dst);
                                $this->file = $file;
                            } else {
                                throw new waException("Error while read gz file");
                            }
                            $this->open();
                            break;
                        } elseif (in_array('compress.zlib', stream_get_wrappers())) {
                            $this->fp = fopen('compress.zlib://'.$this->file, 'rb');
                        } else {
                            throw new waException("Unsupported file extension");
                        }
                    } else {
                        throw new waException("Unsupported file extension");
                    }
                    $this->fsize = filesize($this->file);
                    break;
                case 'zip':
                    if (function_exists('zip_open') && ($zip = zip_open($this->file)) && ($zip_entry = zip_read($zip))) {
                        //dummy read first file;
                        $file = $path.waLocale::transliterate(basename(zip_entry_name($zip_entry)));
                        $zip_fs = zip_entry_filesize($zip_entry);

                        if ($z = fopen($file, "w")) {
                            $size = 0;
                            while ($zz = zip_entry_read($zip_entry, max(0, min(4096, $zip_fs - $size)))) {
                                fwrite($z, $zz);
                                $size += 1024;
                            }
                            fclose($z);
                            zip_entry_close($zip_entry);
                            zip_close($zip);
                            @unlink($this->file);
                            $this->file = $file;
                        } else {
                            zip_entry_close($zip_entry);
                            zip_close($zip);
                            throw new waException("Error while read zip file");
                        }

                        $this->open();
                        break;
                    } else {
                        throw new waException("Error while read zip file");
                    }
                    $this->file = $file;

                    $this->fsize = filesize($this->file);
                    $this->fp = fopen($this->file, "rb");
                    break;
                default:
                    $this->fp = fopen($this->file, "rb");
                    $this->fsize = filesize($this->file);

                    if (strtolower($this->encoding) != 'utf-8') {
                        if (!@stream_filter_prepend($this->fp, 'convert.iconv.'.$this->encoding.'/UTF-8//IGNORE')) {
                            throw new waException("error while register file filter");
                        }
                        $file = preg_replace('/\.csv$/', '.utf-8.csv', $file);
                        if ($this->fp && ($dst = fopen($file, 'wb'))) {
                            stream_copy_to_stream($this->fp, $dst);
                            fclose($this->fp);
                            fclose($dst);
                            $this->encoding = 'utf-8';
                            $this->file = $file;
                            $this->open();
                        } else {
                            throw new waException("Error while convert file encoding");
                        }

                    }
                    break;
            }
        }
    }

    private function restore()
    {
        setlocale(LC_CTYPE, 'ru_RU.UTF-8', 'en_US.UTF-8');
        if ($this->file && file_exists($this->file)) {
            $this->open();

            if (!$this->fp) {
                throw new waException("error while open CSV file");
            }
        } else {
            throw new waException("CSV file not found");
        }
        $this->next();
    }

    public function __destruct()
    {
        if ($this->fp) {
            fclose($this->fp);
        }
    }

    public function serialize()
    {
        return serialize(array(
            'file'         => $this->file,
            'delimeter'    => $this->delimeter,
            'encoding'     => $this->encoding,
            'data_mapping' => $this->data_mapping,
            'key'          => $this->key,
            'offset_map'   => $this->offset_map,
        ));
    }

    public function unserialize($serialized)
    {
        $data = @unserialize($serialized);
        $this->file = ifset($data['file']);
        $this->delimeter = ifempty($data['delimeter'], ';');
        $this->encoding = ifempty($data['encoding'], 'utf-8');
        $this->data_mapping = ifset($data['data_mapping']);
        $this->offset_map = ifset($data['offset_map']);

        $this->restore();
        if ($key = ifset($data['key'])) {
            $this->seek($key);
        }
    }

    public function size()
    {
        return $this->fsize;
    }

    public function file()
    {
        return $this->file;
    }

    /**
     * @param int $position row number
     */
    public function seek($position)
    {
        if ($position != $this->key) {

            if (isset($this->offset_map[$position - 1])) {
                fseek($this->fp, $this->offset_map[$position - 1]);
                $this->key = $position - 1;
                $this->next();
            } else {
                if ($index = $this->offset_map ? max(array_keys($this->offset_map)) : 0) {
                    $this->seek($index);
                } else {
                    $this->rewind();
                }

                while ($position > $this->key && $this->next()) {
                    ;
                }
            }
        }
    }

    public function current()
    {

        if ($this->current) {
            foreach ($this->current as & $cell) {
                $cell = $this->utf8_bad_replace($cell);
            }
            unset($cell);
            $count = count($this->current);
            if ($this->header_count > $count) {
                $this->current = array_merge($this->current, array_fill(0, $this->header_count - $count, ''));
            } else {
                $this->header_count = $count;
            }
            if (!$this->header && $this->current) {
                $this->header();
            }
        }
        return $this->current && !$this->mapped ? $this->applyDataMapping($this->current) : $this->current;
    }

    public function next()
    {
        $this->mapped = false;
        if (!$this->fp || !$this->fsize) {
            return false;
        }

        $this->offset();
        do {
            $empty = true;
            if (strtolower($this->encoding) != 'utf-8') {
                if (!function_exists('str_getcsv')) {
                    throw new waException("PHP 5.3 required");
                }

                if ($line = fgets($this->fp)) { //skip empty lines
                    $line = iconv($this->encoding, 'UTF-8//IGNORE', $line);
                    $this->current = str_getcsv($line, $this->delimeter);
                }
            } else {
                $this->current = fgetcsv($this->fp, 0, $this->delimeter);
            }
            if (is_array($this->current) && (count($this->current) > 1)) {
                ++$this->key;
                foreach ($this->current as $cell) {
                    if ($cell !== '') {
                        $empty = false;
                        break;
                    }
                }
            }
        } while ($empty && !feof($this->fp));

        if (!$this->header && $this->current) {
            $this->header();
        }
        return $this->valid();
    }

    public function key()
    {
        return $this->key;
    }

    public function offset()
    {
        if (!isset($this->offset_map[$this->key])) {
            $this->offset_map[$this->key] = ftell($this->fp);
        }
        return $this->offset_map[$this->key];
    }

    public function valid()
    {
        return ($this->current !== false);
    }

    public function rewind()
    {
        rewind($this->fp);
        $this->key = 0;
    }

    public function header()
    {
        if (!$this->header && $this->current) {
            $columns = $this->current;
            $names = array();
            foreach ($columns as $col => $name) {
                if (isset($names[$name])) {
                    unset($columns[$col]);
                    $key = array_search($name, $this->header);
                    unset($this->header[$key]);
                    $this->header[$key.':'.$col] = $this->utf8_bad_replace($name);
                } else {
                    $names[$name] = true;
                    $this->header[$col] = $this->utf8_bad_replace($name);
                }
            }
        }
        return $this->header;
    }

    /**
     *
     * @param array $map
     */
    public function setMap($map)
    {
        $this->data_mapping = array();
        foreach ($map as $field => $columns) {
            if (strpos($columns, ':')) {
                $columns = explode(':', $columns);
            }
            $add = false;
            if (is_array($columns)) {
                $columns = array_unique(array_map('intval', $columns));
                $add = count($columns);
            } else {
                $columns = intval($columns);
                $add = ($columns >= 0) ? true : false;
            }
            if ($add) {
                $this->data_mapping[$field] = $columns;
            }
        }
    }

    /**
     *
     * @param array $line
     */
    private function applyDataMapping($line)
    {
        $data = array();

        foreach ($this->data_mapping as $field => $column) {

            if (is_array($column)) {
                $insert = array();
                foreach ($column as $id) {
                    if (ifset($line[$id]) !== '') {
                        $insert[] = ifset($line[$id]);
                    }
                }
                if (!count($insert)) {
                    $insert = null;
                }
            } elseif ($column >= 0) {
                $insert = null;
                if (ifset($line[$column]) !== '') {
                    $insert = ifset($line[$column]);
                    if (preg_match('/^\{(.+)\}$/', $insert, $matches)) {
                        $insert = preg_split("/\s*,\s*/", $matches[1]);
                        foreach ($insert as & $item) {
                            if (preg_match('/^"(.+)"$/', $item, $mathes)) {
                                $item = str_replace('""', '"', $mathes[1]);
                            }
                        }
                        unset($item);
                    }
                }
            }

            if ($insert !== null) {
                if (strpos($field, ':')) {
                    $fields = explode(':', $field);
                    $field = array_shift($fields);
                    if (!isset($data[$field])) {
                        $data[$field] = array();
                    }
                    $target =& $data[$field];
                    while (($field = array_shift($fields)) !== null) {
                        if (empty($target)) {
                            $target = array();
                        }
                        if (!isset($target[$field])) {
                            $target[$field] = array();
                        }
                        $target =& $target[$field];
                    }
                } else {
                    if (!isset($data[$field])) {
                        $data[$field] = array();
                    }
                    $target =& $data[$field];
                }
                $target = $insert;
                unset($target);
            }
        }
        $this->mapped = true;
        return $data;
    }

    private function validateSourceFields($params = array())
    {
        $counts = array();
        if ($this->header) {
            foreach ($this->header as $n => $name) {
                $count = count(explode(':', $n));
                $name = mb_strtolower($name, waHtmlControl::$default_charset);
                if (isset($counts[$name])) {
                    $counts[$name] += $count;
                } else {
                    $counts[$name] = $count;
                }
            }
        }
        $columns = array();
        foreach ($counts as $name => $count) {
            if (($count > 1) && !isset($params[$name])) {
                $columns[] = sprintf(_w("Column %s meets %d times"), $name, $count);
            }
        }
        return $columns ? _w('Warning').' '.implode("<br>\n", $columns) : '';
    }

    private function utf8_bad_replace($str, $replace = '?')
    {
        $UTF8_BAD = '([\x00-\x7F]'. # ASCII (including control chars)
        '|[\xC2-\xDF][\x80-\xBF]'. # non-overlong 2-byte
        '|\xE0[\xA0-\xBF][\x80-\xBF]'. # excluding overlongs
        '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'. # straight 3-byte
        '|\xED[\x80-\x9F][\x80-\xBF]'. # excluding surrogates
        '|\xF0[\x90-\xBF][\x80-\xBF]{2}'. # planes 1-3
        '|[\xF1-\xF3][\x80-\xBF]{3}'. # planes 4-15
        '|\xF4[\x80-\x8F][\x80-\xBF]{2}'. # plane 16
        '|(.{1}))'; # invalid byte
        ob_start();
        while (preg_match('/'.$UTF8_BAD.'/S', $str, $matches)) {
            if (!isset($matches[2])) {
                echo $matches[0];
            } else {
                echo $replace;
            }
            $str = substr($str, strlen($matches[0]));
        }
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }

    public function getCsvmapControl($name, $params = array())
    {
        $targets = ifset($params['options'], array());
        $value = ifset($params['value'], array());
        $html = '';

        $html .= '<thead><tr class="white heading small">';
        $html .= '<th>'._w('CSV columns').'</th>';
        $html .= '<th>&nbsp;</th>';
        $html .= '<th>'.ifempty($params['description'], _w('Properties')).'</th>';
        $html .= '</tr></thead>';

        waHtmlControl::addNamespace($params, $name);
        $params = array_merge($params, array(
            'value'               => - 1,
            'title'               => '',
            'description'         => '',
            'title_wrapper'       => '%s',
            'description_wrapper' => '<span class="hint">(%s)</span>',
            'control_wrapper'     => '<tr><td>%2$s</td><td>→</td><td>%1$s%3$s</td></tr>',
            'translate'           => false,
            'options'             => array(
                -1 => array(
                    'value' => - 1,
                    'title' => sprintf('-- %s --', _w('No associated column')),
                    'style' => 'font-style:italic;'
                )
            ),
        ));

        if ($this->header) {
            foreach ($this->header as $id => $column) {
                $params['options'][] = array(
                    'value'       => $id,
                    'title'       => $column,
                    'description' => sprintf(_w('CSV columns numbers %s'), implode(', ', explode(':', $id))),
                );
            }
        }

        //TODO use more correct code

        $group = null;
        foreach ($targets as $field => $target) {
            if (!isset($target['group'])) {
                $target['group'] = array(
                    'title' => '',
                );
            } elseif (!is_array($target['group']) && $target['group']) {
                $target['group'] = array(
                    'title' => $target['group'],
                );
            }
            if (($target['group'] && !$group) || ($target['group']['title'] !== $group)) {
                if ($group) {
                    $html .= '</tbody>';
                }
                $group = $target['group']['title'];
                $group_name = htmlentities(ifset($target['group']['title'], ' '), ENT_QUOTES, waHtmlControl::$default_charset);
                $class = '';
                if (!empty($target['group']['class'])) {
                    $class = sprintf(' class="%s"', htmlentities($target['group']['class'], ENT_QUOTES, waHtmlControl::$default_charset));
                }
                $html .= '<tbody'.$class.'><tr><th colspan="3">'.$group_name.'</th></tr>';
            }

            $params_target = array_merge($params, array(
                'title'       => $target['title'],
                'description' => ifset($target['description']),
            ));
            self::findSimilar($params_target);
            $html .= waHtmlControl::getControl(waHtmlControl::SELECT, $target['value'], $params_target);
        }
        if ($group) {
            $html .= '</tbody>';
        }

        return (empty($params['validate']) ? '' : $this->validateSourceFields($params['validate'])).'<table class="zebra">'.$this->utf8_bad_replace($html).'</table>';
    }

    private static function findSimilar(&$params, $target = null)
    {
        if ($target === null) {
            $target = empty($params['title']) ? ifset($params['description']) : $params['title'];
        }
        $params['value'] = ifset($params['value'], -1);
        $selected = null;
        if ($target && $params['value'] < 0) {
            $max = $p = 0;
            foreach ($params['options'] as $id => & $column) {
                if (!is_array($column)) {
                    $column = array(
                        'title' => $column,
                        'value' => $id,
                    );
                }
                $column['like'] = 0;
                similar_text($column['title'], $target, $column['like']);
                if ($column['like'] >= 90) {
                    $max = $column['like'];
                    $selected =& $column;
                } else {
                    $column['like'] = 0;
                }
            }
            unset($column);
            if ($max < 90) {
                unset($selected);
                $max = 0;
                foreach ($params['options'] as $id => & $column) {
                    if ($column['like'] < 90) {
                        $from = mb_strtolower($column['title']);
                        $to = mb_strtolower($target);
                        if ($from && $to && ((strpos($from, $to) === 0) || (strpos($to, $from) === 0))) {
                            $l_from = mb_strlen($from);
                            $l_to = mb_strlen($to);
                            $column['like'] = min($l_from, $l_to) / max($l_from, $l_to, 1);
                            if ($column['like'] > $max) {
                                $selected =& $column;
                                $max = $column['like'];
                            }
                        }
                    }
                }
                unset($column);
            }
            if (!empty($params['sort'])) {
                uasort($params['options'], array(__CLASS__, 'sortSimilar'));
            }

            if (!empty($selected)) {
                $selected['style'] = 'font-weight:bold;text-decoration:underline;';
                $params['value'] = $selected['value'];
            } elseif ((func_num_args() < 2) && !empty($params['title']) && !empty($params['description'])) {
                self::findSimilar($params, $params['description']);
            }
        }

        return $params['value'];
    }

    private static function sortSimilar($a, $b)
    {
        return min(1, max(-1, $b['like'] - $a['like']));
    }
}
