<?php

interface shopImagesRegenerateInterface
{
    /**
     * @return array
     */
    public function regenerate();

    /**
     * @param string $chunk
     */
    public function setChunk($chunk);

    /**
     * @return int
     */
    public function getImageTotalCount();

    /**
     * @return string
     */
    public function getReport();
}