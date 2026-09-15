<?php

/**
 * Serializes destructive and state-changing Wildberries migration batches.
 *
 * File locks cover PHP workers on one host. MySQL advisory locks extend the
 * same mutex across application hosts that share a database.
 */
class shopMigratePluginWbOperationLock
{
    private $model;
    private $mysql_locks = array();
    private $file_locks = array();

    public function __construct(waModel $model = null)
    {
        $this->model = $model ?: new waModel();
    }

    public function acquire($lock_key = 'global')
    {
        $lock_key = $this->normalizeKey($lock_key);
        if (isset($this->file_locks[$lock_key])) {
            return true;
        }

        $directory = wa()->getTempPath('plugins/migrate/wb-locks', 'shop');
        if (!is_dir($directory) && !waFiles::create($directory, true)) {
            return false;
        }

        $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$lock_key.'.lock';
        $handle = @fopen($path, 'c+');
        if (!$handle || !@flock($handle, LOCK_EX | LOCK_NB)) {
            if ($handle) {
                fclose($handle);
            }
            return false;
        }
        $this->file_locks[$lock_key] = $handle;

        try {
            $result = $this->model->query(
                'SELECT GET_LOCK(s:name, 0)',
                array('name' => 'shop_migrate_wb_'.$lock_key)
            )->fetchField();
            if ($result === null || $result === false || (int) $result !== 1) {
                $this->releaseFileLock($lock_key);
                return false;
            }
            $this->mysql_locks[$lock_key] = true;
            return true;
        } catch (Throwable $e) {
            // The local filesystem lock remains authoritative when the DBMS
            // does not implement MySQL-compatible advisory locks.
            return true;
        }
    }

    public function release($lock_key = 'global')
    {
        $lock_key = $this->normalizeKey($lock_key);
        if (!empty($this->mysql_locks[$lock_key])) {
            try {
                $this->model->query(
                    'SELECT RELEASE_LOCK(s:name)',
                    array('name' => 'shop_migrate_wb_'.$lock_key)
                )->fetchField();
            } catch (Throwable $e) {
            }
            unset($this->mysql_locks[$lock_key]);
        }

        $this->releaseFileLock($lock_key);
    }

    private function releaseFileLock($lock_key)
    {
        if (isset($this->file_locks[$lock_key])) {
            $handle = $this->file_locks[$lock_key];
            @flock($handle, LOCK_UN);
            fclose($handle);
            unset($this->file_locks[$lock_key]);
        }
    }

    public function __destruct()
    {
        foreach (array_keys($this->file_locks) as $lock_key) {
            $this->release($lock_key);
        }
    }

    private function normalizeKey($lock_key)
    {
        $lock_key = preg_replace('/[^a-z0-9_-]+/i', '_', trim((string) $lock_key));
        return $lock_key !== '' ? $lock_key : 'global';
    }
}
