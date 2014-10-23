<?php

class shopInvoiceruPlugin extends shopPlugin
{

    private $templatepaths = array();
    
    private function getTemplatePaths()
    {
        if (!$this->templatepaths) {
            $this->templatepaths = array(
                'changed' => wa()->getDataPath('plugins/'.$this->id.'/template.html'),
                'original' => $this->path . '/templates/actions/printform/PrintformDisplay.html'
            );
        }
        return $this->templatepaths;
    }
    
    public function getTemplatePath()
    {
        foreach ($this->getTemplatePaths() as $filepath) {
            if (file_exists($filepath)) {
                return $filepath;
            }
        }
        return '';
    }
    
    public function getTemplate()
    {
        foreach ($this->getTemplatePaths() as $filepath) {
            if (file_exists($filepath)) {
                return file_get_contents($filepath);
            }
        }
        return '';
    }
    
    public function isTemplateChanged()
    {
        $paths = $this->getTemplatePaths();
        return file_exists($paths['changed']);
    }
    
    public function resetTemplate()
    {
        $paths = $this->getTemplatePaths();
        $dir = dirname($paths['changed']);
        waFiles::delete($paths['changed']);
        waFiles::delete($dir.'/css/printform.css');
        waFiles::delete($dir.'/js/printform.js');
    }
    
    public function saveTemplate($data)
    {
        $paths = $this->getTemplatePaths();
        file_put_contents($paths['changed'], $data);
        waFiles::create(dirname($paths['changed']).'/css/');
        waFiles::create(dirname($paths['changed']).'/js/');
        file_put_contents(dirname($paths['changed']).'/css/printform.css', 
            file_get_contents(dirname($paths['original']).'/css/printform.css')
        );
        file_put_contents(dirname($paths['changed']).'/js/printform.js', 
            file_get_contents(dirname($paths['original']).'/js/printform.js')
        );
    }
    
}
