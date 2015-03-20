<?php

/**
 * Class shopCsvWriter
 * @property-read string $encoding
 * @property-read string $delimiter
 * @property-read string $enclosure
 */
class shopCsvWriter implements Serializable
{
    /**
     *
     * resource a file pointer
     * @var resource
     */
    private $fp = null;

    protected $data_mapping = array();
    private $delimiter = ';';
    private $encoding;
    private $method;
    private $accept_arrays = false;

    private $offset = 0;
    private $file;

    /**
     * @param string $file CSV file path
     * @param string $delimiter
     * @param string $encoding
     * @param string $method mb or iconv encoding method (default is iconv)
     */
    public function __construct($file, $delimiter = ';', $encoding = 'utf-8', $method = 'iconv', $accept_arrays = false)
    {
        $this->file = ifempty($file);
        $this->delimiter = ifempty($delimiter, ';');
        if ($this->delimiter == 'tab') {
            $this->delimiter = "\t";
        }
        $this->encoding = ifempty($encoding, 'utf-8');
        $this->method = in_array($method, array('mb', 'icon'), true) ? $method : 'iconv';
        $this->accept_arrays = $accept_arrays;
        if ($this->file()) {
            waFiles::create($this->file);
        }

        $this->restore();
    }

    public function __destruct()
    {
        if ($this->fp) {
            fclose($this->fp);
        }
    }

    public function __get($name)
    {
        $value = null;
        switch ($name) {
            case 'delimiter':
                $value = $this->delimiter;
                break;
            case 'encoding':
                $value = $this->encoding;
                break;
            case 'enclosure':
                $value = '"';
                break;
        }
        return $value;
    }

    public function serialize()
    {
        return serialize(
            array(
                'file'          => $this->file,
                'delimiter'     => $this->delimiter,
                'encoding'      => $this->encoding,
                'method'        => $this->method,
                'data_mapping'  => $this->data_mapping,
                'offset'        => $this->offset,
                'accept_arrays' => $this->accept_arrays,
            )
        );
    }

    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->file = ifset($data['file']);
        $this->delimiter = ifempty($data['delimiter'], ';');
        $this->encoding = ifempty($data['encoding'], 'utf-8');
        $this->method = ifempty($data['method'], 'iconv');
        $this->data_mapping = ifset($data['data_mapping']);
        $this->offset = ifset($data['offset'], 0);
        $this->accept_arrays = ifset($data['accept_arrays'], false);

        $this->restore();
    }

    public function file()
    {
        return $this->file;
    }

    /**
     *
     * @param array $map
     */
    public function setMap($map)
    {
        $this->data_mapping = $map;
        if ($this->data_mapping) {
            $this->write($this->data_mapping, true);
        }
    }

    public function write($data, $raw = false)
    {
        fputcsv($this->fp, $raw ? $data : $this->applyDataMapping($data), $this->delimiter);
        $this->offset = ftell($this->fp);
    }

    private function restore()
    {
        setlocale(LC_CTYPE, 'ru_RU.UTF-8', 'en_US.UTF-8');
        if ($this->file) {

            $fsize = file_exists($this->file) ? filesize($this->file) : false;
            $this->fp = @fopen($this->file, 'a');
            if (!$this->fp) {
                throw new waException("error while open CSV file");
            }
            fseek($this->fp, 0, SEEK_END);

            if (strtolower($this->encoding) != 'utf-8') {
                switch ($this->method) {
                    case 'iconv':
                    default:
                        if (!@stream_filter_prepend($this->fp, 'convert.iconv.UTF-8/'.$this->encoding.'//TRANSLIT//IGNORE')) {
                            throw new waException("error while register file filter");
                        }
                        break;
                }

            }

            if (!$this->offset) {
                if ($fsize) {
                    fseek($this->fp, 0);
                    ftruncate($this->fp, 0);
                    fseek($this->fp, 0);
                }
                if ($this->data_mapping) {
                    $this->write($this->data_mapping, true);
                }
            } else {
                fseek($this->fp, 0);
                ftruncate($this->fp, $this->offset);
                fseek($this->fp, 0, SEEK_END);
            }

        } else {
            throw new waException("CSV file not found");
        }
    }

    /**
     * @param $data
     * @return array
     */
    private function applyDataMapping($data)
    {
        $enclosure = '"';
        $pattern = sprintf("/(?:%s|%s|%s|\s)/", preg_quote($this->delimiter, '/'), preg_quote(',', '/'), preg_quote($enclosure, '/'));
        $mapped = array();
        if (empty($this->data_mapping)) {
            $mapped = $data;
        } else {
            foreach ($this->data_mapping as $key => $column) {
                $value = null;
                if (strpos($key, ':')) {
                    $key = explode(':', $key);
                }
                if (is_array($key)) {
                    $value = $data;
                    while (($key_chunk = array_shift($key)) !== null) {
                        $value = ifset($value[$key_chunk]);
                        if ($value === null) {
                            break;
                        }
                    }
                } else {
                    $value = ifset($data[$key]);
                }
                if (is_array($value)) {
                    if ($this->accept_arrays) {
                        foreach ($value as & $item) {
                            if (preg_match($pattern, $item)) {
                                $item = $enclosure.str_replace($enclosure, $enclosure.$enclosure, $item).$enclosure;
                            }
                        }
                        unset($item);
                        if ($value) {
                            $value = "{".implode(',', $value)."}";
                        } else {
                            $value = '';
                        }
                    } else {
                        $value = reset($value);
                    }
                }
                $mapped[] = str_replace("\r\n", "\r", $value);
            }
        }

        return $mapped;
    }
}
