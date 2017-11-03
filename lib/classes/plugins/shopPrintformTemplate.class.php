<?php

class shopPrintformTemplate
{
    private $original_path;
    private $changed_path;
    private $options = array();

    /**
     * @var waView
     */
    private $view;

    public function __construct($original_path, $changed_path, $options = array())
    {
        $this->original_path = $original_path;
        $this->changed_path = $changed_path;
        $this->options = $options;
        $this->view = wa()->getView();
    }

    public function getPath()
    {
        foreach (array($this->changed_path, $this->original_path) as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return '';
    }

    /**
     * @return string HTML code of template
     */
    public function getContent()
    {
        return ($path = $this->getPath()) ? file_get_contents($path) : '';
    }

    /**
     * @return bool
     */
    public function isChanged()
    {
        return file_exists($this->changed_path);
    }

    public function reset()
    {
        waFiles::delete(dirname($this->changed_path));
        return $this->getContent();
    }

    /**
     * @param $html
     * @return bool|int
     */
    public function save($html)
    {
        $exclude = array(
            '@PrintformDisplay\.html$@',
            '@\.js$@'
        );
        waFiles::copy(dirname($this->original_path), dirname($this->changed_path), $exclude);
        return file_put_contents($this->changed_path, $html);
    }

    /**
     * @return waView
     */
    public function getView()
    {
        return $this->view;
    }

    public function setView(waView $view)
    {
        $this->view = $view;
    }

    public function display()
    {
        return $this->view->fetch($this->getPath());
    }
}
