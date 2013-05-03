<?php

class shopAsiaimportWebasystsameTransport extends shopMigrateWebasystTransport
{
    protected $sql_options;

    /**
     * @var waModel
     */
    protected $source;
    protected $source_path;
    protected $dbkey;

    protected function initOptions()
    {
        parent::initOptions();
        $this->addOption('path', array(
            'title'                  => _wp('Path to folder'),
            'value'                  => wa()->getConfig()->getRootPath(),
            'description'            => _wp('Path to folder of the WebAsyst (old version) installation'),
            'control_type' => waHtmlControl::INPUT,
        ));
    }

    public function validate($result, &$errors)
    {
        try {
            $this->getSourceModel();
            $this->addOption('path', array('readonly' => true));
        } catch (waException $ex) {
            $result = false;
            if (!($field = $ex->getCode())) {
                $field = 'global';
            }
            $errors['path'] = $ex->getMessage();
            $this->addOption('path', array('readonly' => false));
        }
        return parent::validate($result, $errors);
    }

    private function getSourceModel()
    {
        if (!$this->source) {
            $this->source_path = $this->getOption('path');
            if (substr($this->source_path, -1) != '/') {
                $this->source_path .= '/';
            }
            if (!file_exists($this->source_path)) {
                throw new waException(sprintf(_wp('Invalid PATH %s'), $this->source_path));
            }
            if (!file_exists($this->source_path.'kernel/wbs.xml')) {
                throw new waException(sprintf(_wp('Invalid PATH %s'), $this->source_path));
            }

            /**
             *
             * @var SimpleXMLElement
             */
            $wbs = simplexml_load_file($this->source_path.'kernel/wbs.xml');
            $this->dbkey = (string) $wbs->FRONTEND['dbkey'];
            $dkey_path = $this->source_path.'dblist/'.$this->dbkey.'.xml';

            if (empty($this->dbkey) || !file_exists($dkey_path)) {
                throw new waException(sprintf(_wp('Invalid PATH %s'), $this->source_path));
            }
            /**
             *
             * @var SimpleXMLElement
             */
            $dblist = simplexml_load_file($dkey_path);

            $version = (string) $dblist->VERSIONS['SC'];

            $host_name = (string) $dblist->DBSETTINGS['SQLSERVER'];
            $host = $wbs->xPath('/WBS/SQLSERVERS/SQLSERVER[@NAME="'.htmlentities($host_name, ENT_QUOTES, 'utf-8').'"]');
            if (!count($host)) {
                throw new waException(_wp('Invalid SQL server name'));
            }
            $host = $host[0];
            $port = (string) $host['PORT'];
            $this->sql_options = array(
                'host'     => (string) $host['HOST'].($port ? ':'.$port : ''),
                'user'     => (string) $dblist->DBSETTINGS['DB_USER'],
                'password' => (string) $dblist->DBSETTINGS['DB_PASSWORD'],
                'database' => (string) $dblist->DBSETTINGS['DB_NAME'],
            );

            $this->source = new waModel($this->sql_options);
        }
        return $this->source;
    }

    public function init()
    {

    }

    protected function query($sql, $one = true)
    {
        $q = $this->getSourceModel()->query($sql);
        if ($one) {
            return $q->fetch();
        } else {
            return $q->fetchAll();
        }
    }

    protected function moveFile($path, $target, $public = true)
    {
        if ($public) {
            $base_path = $this->source_path."published/publicdata/{$this->dbkey}/attachments/SC/";
        } else {
            $base_path = $this->source_path."data/{$this->dbkey}/attachments/SC/";
        }
        waFiles::copy($base_path.$path, $target);
    }
}
