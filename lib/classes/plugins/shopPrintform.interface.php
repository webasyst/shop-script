<?php

interface shopPrintformInterface
{

    /**
     * @return string
     */
    public function getTemplatePath();

    /**
     * @return string
     */
    public function getTemplate();

    /**
     * @return bool
     */
    public function isTemplateChanged();

    /**
     * @return mixed
     */
    public function resetTemplate();

    /**
     * @param string $html
     * @return bool
     */
    public function saveTemplate($html);

    /**
     * @param mixed $data
     * @return string
     */
    public function renderPrintform($data);

    /**
     * @param $data
     * @param waView $view
     * @return mixed
     */
    public function preparePrintform($data, waView $view);
}
