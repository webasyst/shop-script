<?php


/**
 * Class shopCsvReader
 * @example
 */
class shopCsvReader implements SeekableIterator, Serializable, Countable
{
    /**
     *
     * resource a file pointer
     * @var resource
     */
    private $fp = null;
    private $fsize = null;
    private $header = null;

    protected $data_mapping = array();
    private $delimiter = ';';
    private $encoding;
    private $header_count = 0;
    private $count = null;

    private $offset_map = array();
    private $current = null;
    private $mapped = false;
    private $key = 0;
    private $file;
    private $files = array();

    private $columns = array();

    private $params = array(
        'ignore_empty_cells' => true,
        'trim_cells'         => true,
    );

    const MAP_CONTROL = 'Csvmap';
    const TABLE_CONTROL = 'Csvtable';

    public function __construct($file, $delimiter = ';', $encoding = 'utf-8')
    {
        self::registerControl($this);
        $this->file = ifempty($file);
        $this->delimiter = ifempty($delimiter, ';');
        if ($this->delimiter == 'tab') {
            $this->delimiter = "\t";
        }
        $this->encoding = ifempty($encoding, 'utf-8');
        $this->restore();
    }

    private static function registerControl($self)
    {
        waHtmlControl::registerControl(self::MAP_CONTROL, array($self, 'getCsvmapControl'));
        waHtmlControl::registerControl(self::TABLE_CONTROL, array($self, 'getCsvtableControl'));
    }

    private static function id2name($id)
    {
        $name = '';
        ++$id;
        while ($id > 0) {
            $modulo = ($id - 1) % 26;
            $name = chr(65 + $modulo).$name;
            $id = (int)floor(($id - $modulo) / 26);
        }

        return $name;
    }

    private function open()
    {
        if ($this->file && file_exists($this->file)) {
            $this->files();
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
                    if (function_exists('zip_open') && ($zip = zip_open($this->file)) && is_resource($zip) && ($zip_entry = zip_read($zip))) {
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
                            $this->file = $file;
                            $this->files();
                        } else {
                            zip_entry_close($zip_entry);
                            zip_close($zip);
                            throw new waException("Error while read zip file");
                        }

                        $this->open();
                    } else {
                        throw new waException("Error while read zip file");
                    }
                    break;
                default:
                    if (is_array($this->encoding) || ($this->encoding == 'auto')) {
                        $this->fp = fopen($this->file, "rb");
                        if (!$this->fp) {
                            throw new waException("error while open CSV file");
                        }
                        $chunk = fread($this->fp, 4096);
                        if (is_array($this->encoding)) {
                            $this->encoding = mb_detect_encoding($chunk, $this->encoding);
                        } else {
                            $this->encoding = mb_detect_encoding($chunk);
                        }
                        if (strtolower($this->encoding) == 'utf-8') {
                            fseek($this->fp, 0);
                        }
                    }

                    if (strtolower($this->encoding) != 'utf-8') {
                        if ($this->fp) {
                            fclose($this->fp);
                            unset($this->fp);
                        }
                        if ($file = waFiles::convert($this->file, $this->encoding)) {
                            $this->encoding = 'utf-8';
                            $this->file = $file;
                            $this->files();
                            $this->fp = fopen($this->file, "rb");
                        } else {
                            throw new waException("Error while convert file encoding");
                        }
                    } elseif (!$this->fp) {
                        $this->fp = fopen($this->file, "rb");
                    }
                    $this->fsize = filesize($this->file);
                    break;
            }
        }
    }

    private function restore()
    {
        setlocale(LC_CTYPE, 'ru_RU.UTF-8', 'en_US.UTF-8');
        @ini_set('auto_detect_line_endings', true);
        if ($this->file && file_exists($this->file) && !is_dir($this->file)) {
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
        $this->delete(false);
    }

    public function serialize()
    {
        return serialize(
            array(
                'file'         => $this->file,
                'files'        => $this->files,
                'delimiter'    => $this->delimiter,
                'encoding'     => $this->encoding,
                'data_mapping' => $this->data_mapping,
                'key'          => $this->key,
                'offset_map'   => $this->optimizeOffsetMap(),
                'columns'      => $this->columns,
                'count'        => $this->count,
            )
        );
    }

    private function optimizeOffsetMap()
    {
        $map = array();
        foreach ($this->offset_map as $position => $value) {
            if (!($position % 64) && $position) {
                $map[$position] = $value;
            }
        }
        return $map;
    }

    public function unserialize($serialized)
    {
        $data = @unserialize($serialized);
        $this->file = ifset($data['file']);
        $this->files = ifset($data['files'], array());
        $this->delimiter = ifempty($data['delimiter'], ';');
        $this->encoding = ifempty($data['encoding'], 'utf-8');
        $this->data_mapping = ifset($data['data_mapping']);
        $this->offset_map = ifset($data['offset_map']);
        $this->columns = ifset($data['columns'], array());
        $this->count = ifset($data['count'], null);

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

    public function delete($original = false)
    {
        if ($this->fp) {
            fclose($this->fp);
            $this->fp = null;
        }
        $files = $this->files;
        $original_files = array();
        if (!$original) {
            $original_files[] = array_shift($files);
            $original_files[] = array_pop($files);
        }

        $files = array_unique($files);
        foreach ($files as $file) {
            waFiles::delete($file);
        }

        $this->files = $original_files;

    }

    private function files()
    {
        if (!in_array($this->file, $this->files)) {
            $this->files[] = $this->file;
        }
    }

    /**
     * @param int $position row number
     */
    public function seek($position)
    {

        //XXX invalid on empty strings
        if ($position != $this->key) {

            if (isset($this->offset_map[$position - 1])) {
                fseek($this->fp, $this->offset_map[$position - 1]);
                $this->key = $position - 1;
                $this->next();
            } else {
                $positions = $this->offset_map ? array_keys($this->offset_map) : array();
                $key = max($positions);
                if (($key >= $position) && $this->offset_map) {
                    $callback = create_function('$a', sprintf(' return ($a < %d);', $position));
                    $positions = array_filter($positions, $callback);
                    $key = max($positions);
                }

                if ($key) {
                    $this->seek($key + 1);
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
            $this->current = false;
            $empty = true;
            $line = null;

            if ($line = fgetcsv($this->fp, 0, $this->delimiter)) {
                if ($this->params['trim_cells']) {
                    $line = array_map('trim', $line);
                }
                $this->current = $line;
            }

            if ($line && is_array($this->current) && (count($this->current) > 0)) { //skip empty lines
                ++$this->key;


                foreach ($this->current as $cell) {
                    if ($cell !== '') {
                        $empty = false;
                        break;
                    }
                }
            }
            if ($empty) {
                $this->current = false;
            }
        } while ($empty && !empty($this->fp) && !feof($this->fp));


        if (!$this->header && $this->current) {
            $this->header();
        }

        if ($this->key > max(array_keys($this->offset_map))) {
            $this->count = ($this->valid() ? '~'.floor($this->key * $this->size() / $this->offset()) : $this->key);
        } elseif ($empty) {
            $this->offset();
            $this->count = $this->key;
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
        // $this->next();
    }

    public function header()
    {
        if (!$this->header && $this->current) {
            $count = count($this->current);
            if ($this->header_count < $count) {
                $this->header_count = $count;
            }
            $columns = $this->current;
            $names = array();
            foreach ($columns as $col => $name) {
                $name = $this->utf8_bad_replace($name);
                if (isset($names[$name])) {
                    $key = $names[$name];
                    $prev = explode(':', $names[$name]);
                    if (($col - end($prev)) == 1) {
                        unset($columns[$col]);
                        unset($this->header[$key]);
                        $key .= ':'.$col;
                        $this->header[$key] = $name;
                        $names[$name] = $key;
                    } else {
                        $names[$name] = $col;
                        $this->header[$col] = $name;
                    }
                } else {
                    $names[$name] = $col;
                    $this->header[$col] = $name;
                }
            }
            ksort($this->header, SORT_NUMERIC);
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
     * @param string|callback[] $columns extra columns HTML content
     * @return string|callback[]
     */
    public function columns($columns = array())
    {
        return $this->columns = $columns;
    }

    /**
     *
     * @param array $line
     * @return array
     */
    private function applyDataMapping($line)
    {
        $data = array();
        foreach ($this->data_mapping as $field => $column) {
            $insert = null;
            if (is_array($column)) {
                $insert = array();
                foreach ($column as $id) {
                    $cell = ifset($line[$id]);
                    if (($cell !== '') || !$this->params['ignore_empty_cells']) {
                        $insert[] = $cell;
                    }
                }
                if (!count($insert)) {
                    $insert = null;
                }
            } elseif ($column >= 0) {
                $cell = ifset($line[$column]);
                if (($cell !== '') || !$this->params['ignore_empty_cells']) {
                    $insert = $cell;
                }
            }
            if ($insert !== null) {
                self::insert($data, $field, $insert);
            }
        }
        $this->mapped = true;
        return $data;
    }

    private $empty = null;

    /**
     * Get mapped data for empty line
     * @return array
     */
    public function getEmpty()
    {
        if ($this->empty === null) {
            foreach ($this->data_mapping as $field => $column) {
                self::insert($this->empty, $field, '');
            }
        }
        return $this->empty;
    }

    private static function insert(&$data, $field, $value)
    {
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
        $target = $value;
        unset($target);
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

    private function utf8_bad_replace($str)
    {
        return @iconv('UTF-8', 'UTF-8//IGNORE', $str);
    }


    /**
     * @return string HTML of table row
     */
    public function getTableRow()
    {
        $row = '';
        if ($this->current) {
            $key = $this->key();
            $row .= '<tr class="js-row-'.$key.'"><td class="heading">'.$key.'</td>';
            foreach ($this->columns as $column) {
                if (is_callable($column)) {
                    $column = call_user_func($column, $this->current(), $key);
                }
                if (preg_match('@^<td.+</td>$@', $column)) {
                    $row .= $column;
                } else {
                    $row .= '<td>'.$column.'</td>';
                }
            }

            for ($id = 0; $id < $this->header_count; $id++) {
                if (isset($this->current[$id])) {
                    if (mb_strlen($this->current[$id]) > 24) {
                        $row .= '<td title="'.htmlentities($this->current[$id], ENT_QUOTES, 'utf-8').'">'.htmlentities(
                                mb_substr($this->current[$id], 0, 20),
                                ENT_NOQUOTES,
                                'utf-8'
                            ).'…</td>';
                    } else {
                        if ($this->current[$id] === '') {
                            $row .= '<td>&nbsp;</td>';
                        } else {
                            $row .= '<td>'.htmlentities($this->current[$id], ENT_NOQUOTES, 'utf-8').'</td>';
                        }
                    }
                } else {
                    $row .= '<td>&nbsp;</td>';
                }
            }
            $row .= '</tr>';
        }
        return $row;
    }

    public function getCsvtableControl($name, $params = array())
    {
        ifset($params['value'], array());
        $html = '';
        if (!empty($params['validate'])) {
            $html .= $this->validateSourceFields($params['validate']);
        }
        array_unshift(
            $params['options'],
            array(
                'value' => -1,
                'title' => sprintf('-- %s --', _w('No associated column')),
                'style' => 'font-style:italic;'
            )
        );


        foreach ($params['options'] as &$option) {
            if (!isset($option['group'])) {
                $option['group'] = '';

            } elseif (is_array($option['group'])) {
                $option['group'] = $option['group']['title'];

            }
        }
        unset($option);

        $default = array(
            'value'               => -1,
            'title'               => '',
            'description'         => '',
            'title_wrapper'       => false,
            'description_wrapper' => false,
            'translate'           => false,
            'control_wrapper'     => '<td><div class="s-csv-assigned-to"><span class="ignored">&times;</span><span class="active">&darr;</span></div>%2$s%3$s</td>',
        );

        if (!empty($params['title'])) {
            $title = '<caption>'.htmlentities($params['title'], ENT_NOQUOTES, waHtmlControl::$default_charset)."</caption>";
        } else {
            $title = '';
        }
        $html .= <<<HTML
<table class="s-csv">
    {$title}
  <thead>
HTML;
        waHtmlControl::addNamespace($params, $name);
        $params = array_merge($params, $default);


        if (!empty($params['options']['autocomplete'])) {
            if (!empty($params['autocomplete_handler'])) {
                $params['description'] = <<<HTML
    <input type="search" class="js-autocomplete-csv" placeholder="Type text to search" value="" style="display: none;">
    <a href="#/{$params['autocomplete_handler']}" class="js-action" style="display: none;"><i class="icon10 close"></i></a>
HTML;

            } else {
                $params['description'] = <<<HTML
<input type="search" class="js-autocomplete-csv" placeholder="Type text to search" value="">
HTML;
            }

            $params['description_wrapper'] = '%s';
        }

        $rows = array(
            '<td>&nbsp;</td>',
            '<td>1</td>',
        );
        if ($column_count = count(ifset($params['columns'], array()))) {
            foreach ($rows as &$row) {
                $row .= str_repeat('<td>&nbsp;</td>', $column_count);
            }

            unset($row);
        }
        $headers = array();
        if ($this->header) {
            foreach ($this->header as $id => $column) {
                $ids = array_map('intval', explode(':', $id));
                $primary = reset($ids);
                foreach ($ids as $_id) {
                    $headers[$_id] = array(
                        'name'    => $id,
                        'title'   => $column,
                        'primary' => ($primary == $_id),
                    );
                }

            }
        }

        ksort($headers, SORT_NUMERIC);

        $map = array();
        $controls = array(
            '<td>&nbsp;</td>',
        );
        if ($column_count) {
            foreach ($controls as &$row) {
                $row .= str_repeat('<td>&nbsp;</td>', $column_count);
            }

            unset($row);
        }

        foreach ($headers as $id => &$header) {
            $rows[0] .= '<td title="'.($id + 1).'">'.self::id2name($id)."</td>";
            $header['title'] = trim($header['title']);
            if (!empty($header['title'])) {
                $header['title'] = htmlentities($header['title'], ENT_NOQUOTES, waHtmlControl::$default_charset);
            } else {
                $header['title'] = '&nbsp;';
            }
            $rows[1] .= '<td class="s-csv-header">'.$header['title'].'</td>';
            if ($header['primary']) {
                $params_target = $params;
                self::findSimilar($params_target, $header['title'], array('similar' => false));
                $header['value'] = $params_target['value'];
                if ($header['value'] >= 0) {
                    $map[$header['value']] = $header['name'];
                }

                if (!empty($params_target['options']['autocomplete'])) {
                    //TODO
                }
                $controls[0] .= waHtmlControl::getControl(waHtmlControl::SELECT, $header['name'], $params_target);
            } else {
                $controls[0] .= "<td>&nbsp;</td>";
            }
        }

        $html .= '<tr>'.implode('</tr><tr>', $rows).'</tr>';
        $html .= '<tr class="s-csv-controls">'.implode('</tr><tr>', $controls).'</tr>';
        $html .= '</thead>';

        $body = '<tbody>';
        $n = 0;
        if ($limit = max(0, intval(ifset($params['preview'])))) {
            $this->setMap($map);
            $this->columns(ifset($params['columns'], array()));
            while ((++$n < $limit) && $this->next()) {
                $body .= $this->getTableRow();
            }
        }
        $body .= '</tbody>';
        $count = count($headers) + $column_count;
        $link = '&nbsp;';
        $count_str = sprintf(_w('Previewing %s of %s rows'), "<span class=\"js-csv-current-count\">{$n}</span>", "<span class=\"js-csv-total-count\">{$this->count}</span>");
        if (intval(preg_replace('@\D+@', '', $this->count)) > $n) {
            if (!empty($params['row_handler'])) {
                $translate = _w('Load more...');
                $translate_ = htmlentities($translate, ENT_QUOTES, 'utf-8');
                if (!empty($params['row_handler_string'])) {
                    $count_str .= <<<HTML
<br/>
<a href="#/{$params['row_handler']}" class="js-action js-csv-more inline-link" title="{$translate_}">
    <b><i>{$translate}</i></b>
</a>
HTML;
                } else {
                    $link = <<<HTML
<a href="#/{$params['row_handler']}" class="js-action js-csv-more" title="{$translate_}">
    <i class="icon16 plus"></i>
</a>
HTML;
                }
            }
        }
        $footer = '';
        $foot = <<<HTML
<tfoot>
{$footer}
    <tr>
        <td>{$link}</td>
        <td colspan="{$count}">
            {$count_str}
        </td>
    </tr>
</tfoot>
HTML;

        $html .= $body;

        $html .= $foot.'</table>';
        $html .= '<script type="text/javascript">';
        $html .= <<<JS

var csv_control_table = $("table.s-csv"),csv_control_table_width = [];
csv_control_table.find("> tbody").css("max-height",Math.round(Math.max(0.6*$(window).height(),400))+"px");

function s_csv_setsize(update){
    if(update || !csv_control_table_width.length ){
        csv_control_table.find("> tbody > tr, > thead > tr:not(.s-csv-controls)").each(function(index,tr){
            $(tr).children().each(function(index, el) {
                csv_control_table_width[index] =Math.max(index>3?75:5,csv_control_table_width[index]||0,  parseInt($(el).outerWidth(true)||0));
            });
        });
    }

    csv_control_table.find("> thead > tr, > tfoot > tr, > tbody > tr").each(function(index,tr){
        var offset = 0, cols=0,width=0;
        $(tr).children().each(function(i, td) {
           var csv_control_td = $(td);
            cols = Math.max(1,csv_control_td.attr("colspan")||1);
            width = csv_control_table_width.slice(i+offset,i+offset+cols).reduce(function(pv, cv) { return pv + cv; }, 0);
            offset += cols-1;

            csv_control_td.attr("width",width+"px");
            csv_control_td.find(":input").width(Math.max(25,width-16));
        });
    });
}
setTimeout(s_csv_setsize,5);

JS;
        $html .= '</script>';

        self::snapshot($this);

        return $html;
    }

    /**
     * @param string|shopCsvReader $reader
     * @param array $params
     * @return null|shopCsvReader
     */
    public static function snapshot($reader, &$params = array())
    {
        if (is_object($reader) && (get_class($reader) == __CLASS__)) {
            $snapshot = array(
                'params' => $params,
                'reader' => serialize($reader),
            );
            waUtils::varExportToFile($snapshot, $reader->file.'.snapshot');
            unset($snapshot);
        } else {
            if ($reader && is_string($reader) && file_exists($reader)) {
                if (file_exists($reader.'.snapshot')) {
                    $data = include($reader.'.snapshot');
                    $params = ifset($data['params'], array());
                    $reader = unserialize(ifset($data['reader'], ''));
                } else {
                    $reader = new self($reader);
                }
            } else {
                $reader = null;
            }
        }
        return $reader;
    }

    public function getCsvmapControl($name, $params = array())
    {
        $targets = ifset($params['options'], array());
        ifset($params['value'], array());
        $html = '';
        if (!empty($params['validate'])) {
            $html .= $this->validateSourceFields($params['validate']);
        }
        $title = _w('CSV columns');
        $description = ifempty($params['description'], _w('Properties'));
        $html .= <<<HTML
<table class="zebra">
  <thead>
    <tr class="white heading small">
        <th>{$title}</th>
        <th>&nbsp;</th>
        <th>{$description}</th>
    </tr>
  </thead>
HTML;

        $default = array(
            'value'               => -1,
            'title'               => '',
            'description'         => '',
            'title_wrapper'       => '%s',
            'description_wrapper' => '<span class="hint">(%s)</span>',
            'control_wrapper'     => '
<tr>
    <td>%2$s</td>
    <td>→</td>
    <td>%1$s%3$s</td>
</tr>',
            'translate'           => false,
            'options'             => array(
                -1 => array(
                    'value' => -1,
                    'title' => sprintf('-- %s --', _w('No associated column')),
                    'style' => 'font-style:italic;'
                )
            ),
        );

        waHtmlControl::addNamespace($params, $name);
        $params = array_merge($params, $default);

        if ($this->header) {
            foreach ($this->header as $id => $column) {
                $numbers = array_map('intval', explode(':', $id));
                foreach ($numbers as &$n) {
                    ++$n;
                }
                unset($n);

                $params['options'][] = array(
                    'value'       => $id,
                    'title'       => $column,
                    'description' => sprintf(_w('CSV columns numbers %s'), implode(', ', $numbers)),
                );
            }
        }

        $group = null;

        while ($target = array_shift($targets)) {
            if (!isset($target['group'])) {
                $target['group'] = array(
                    'title' => '',
                );
            } elseif (!is_array($target['group']) && $target['group']) {
                $target['group'] = array(
                    'title' => $target['group'],
                );
            }

            $params_target = array_merge(
                $params,
                array(
                    'title'       => $target['title'],
                    'description' => ifset($target['description']),
                    'control'     => ifset($target['control']),
                )
            );

            if (($target['group'] && !$group) || ($target['group']['title'] !== $group)) {
                if ($group) {
                    $html .= '</tbody>';
                }
                $group = $target['group']['title'];
                $group_name = htmlentities(ifset($target['group']['title'], ' '), ENT_NOQUOTES, waHtmlControl::$default_charset);
                $class = '';
                if (!empty($target['group']['class'])) {
                    $class = sprintf(' class="%s"', htmlentities($target['group']['class'], ENT_QUOTES, waHtmlControl::$default_charset));
                }
                $html .= <<<HTML
<tbody {$class}><tr><th colspan="3">{$group_name}</th></tr>
HTML;
            }

            if (empty($params_target['control'])) {
                self::findSimilar($params_target, null, array('similar' => false));
            }

            $control = waHtmlControl::getControl(waHtmlControl::SELECT, $target['value'], $params_target);
            if (!empty($params_target['control'])) {
                $_type = ifempty($params_target['control']['control'], waHtmlControl::INPUT);
                $_control = waHtmlControl::getControl($_type, $target['value'], ifempty($params_target['control']['params'], array()));
                $control = preg_replace('@</td></tr>$@', $_control, $control).'</td></tr>';
            }
            $html .= $control;
        }

        if ($group) {
            $html .= '</tbody>';
        }
        $html .= '</table>';;
        return $html;
    }

    private static function findSimilar(&$params, $target = null, $options = array())
    {
        if ($target === null) {
            $target = empty($params['title']) ? ifset($params['description']) : $params['title'];
        }
        $params['value'] = ifset($params['value'], -1);
        $selected = null;
        if ($target && $params['value'] < 0) {
            $max = $p = 0;
            //init data structure
            foreach ($params['options'] as $id => & $column) {
                if (!is_array($column)) {
                    $column = array(
                        'title' => $column,
                        'value' => $id,
                    );
                }
                $column['like'] = 0;
            }

            if (!empty($options['similar'])) {
                foreach ($params['options'] as & $column) {
                    similar_text($column['title'], $target, $column['like']);
                    if ($column['like'] >= 90) {
                        $max = $column['like'];
                        $selected =& $column;
                    } else {
                        $column['like'] = 0;
                    }
                    unset($column);
                }
            }

            if ($max < 90) {
                unset($selected);
                $max = 0;
                $to = mb_strtolower($target);
                foreach ($params['options'] as & $column) {
                    if ($column['like'] < 90) {
                        $from = mb_strtolower($column['title']);
                        if ($from && $to && ((strpos($from, $to) === 0) || (strpos($to, $from) === 0))) {
                            $l_from = mb_strlen($from);
                            $l_to = mb_strlen($to);
                            $column['like'] = 100 * min($l_from, $l_to) / max($l_from, $l_to, 1);
                            if ($column['like'] > $max) {
                                $selected =& $column;
                                $max = $column['like'];
                            }
                        }
                    }
                    unset($column);
                }
            }
            if (!empty($params['sort'])) {
                uasort($params['options'], array(__CLASS__, 'sortSimilar'));
            }

            if (!empty($selected)) {
                $selected['style'] = 'font-weight:bold;text-decoration:underline;';
                $params['value'] = $selected['value'];
                unset($selected);
            } elseif ((func_num_args() < 2) && !empty($params['title']) && !empty($params['description'])) {
                self::findSimilar($params, $params['description'], $options);
            }
        }
        return $params['value'];
    }

    public function count()
    {
        return $this->count;
    }

    private static function sortSimilar($a, $b)
    {
        return min(1, max(-1, $b['like'] - $a['like']));
    }
}
