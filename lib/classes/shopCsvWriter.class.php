<?php
class shopCsvWriter implements Serializable
{
    /**
     *
     * resource a file pointer
     * @var resouce
     */
    private $fp = null;

    protected $data_mapping = array();
    private $delimeter = ';';
    private $encoding;

    private $offset = 0;
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
            'offset'       => $this->offset,
        ));
    }

    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->file = ifset($data['file']);
        $this->delimeter = ifempty($data['delimeter'], ';');
        $this->encoding = ifempty($data['encoding'], 'utf-8');
        $this->data_mapping = ifset($data['data_mapping']);
        $this->offset = ifset($data['offset'], 0);

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
        fputcsv($this->fp, $raw ? $data : $this->applyDataMapping($data), $this->delimeter);
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
                if (!@stream_filter_prepend($this->fp, 'convert.iconv.UTF-8/'.$this->encoding.'//IGNORE')) {
                    throw new waException("error while register file filter");
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
     *
     * @param array $line
     */
    private function applyDataMapping($data)
    {
        $enclosure = '"';
        $pattern = sprintf("/(?:%s|%s|%s|\s)/", preg_quote($this->delimeter, '/'), preg_quote(',', '/'), preg_quote($enclosure, '/'));
        $maped = array();
        if (empty($this->data_mapping)) {
            $maped = $data;
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
                }
                $maped[] = $value;
            }
        }
        return $maped;
    }
}
