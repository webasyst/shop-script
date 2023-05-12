<?php

class shopHinter
{
    protected $hints;
    protected $locale;
    protected $options;

    public function __construct($locale = null, $options = array())
    {
        $this->locale = $locale ? $locale : wa()->getLocale();
        $this->locale = $this->locale ? $this->locale : 'en_US';
        $this->options = $options;
    }

    public function html($name)
    {
        $hint = $this->getHint($name);
        if (!$hint || !is_array($hint)) {
            return '';
        }
        return $this->render($hint, $name);
    }

    public static function hint($name)
    {
        static $hinter;
        $hinter = $hinter ? $hinter : new shopHinter();
        return $hinter->html($name);
    }

    protected function getHint($name)
    {
        $hints = self::getHints();
        return isset($hints[$name]) ? $hints[$name] : null;
    }

    protected function getHints()
    {
        if ($this->hints) {
            return $this->hints;
        }
        return $this->hints ? $this->hints : ($this->hints = self::loadHints());
    }

    protected function loadHints()
    {
        $this->hints = array();
        $locale = wa()->getLocale();
        $paths = array(
            wa()->getAppPath("lib/config/hints/{$locale}.php", 'shop'),
            wa()->getAppPath("lib/config/hints/en_US.php", 'shop'),
        );
        $paths = array_unique($paths);
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->hints = include($path);
                break;
            }
        }
        return $this->hints;
    }

    protected function render($hint, $name)
    {
        $hint['text'] = isset($hint['text']) ? $hint['text'] : '';
        $hint['link'] = isset($hint['link']) ? $hint['link'] : '';

        $html = 'lib/config/hints/template' . (wa()->whichUI() === '1.3' ? '-legacy' : '') . '.html';
        $template_path = isset($this->options['template']) ? $this->options['template'] :
            wa()->getAppPath($html, 'shop');

        return wa()->getView()->renderTemplate($template_path, array(
            'hint' => $hint,
            'locale' => $this->locale,
            'name' => $name
        ));
    }
}
