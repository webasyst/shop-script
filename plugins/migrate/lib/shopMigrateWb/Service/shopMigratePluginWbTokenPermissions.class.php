<?php

class shopMigratePluginWbTokenPermissions
{
    const CONTENT = 'content';
    const PRICES = 'prices';
    const MARKETPLACE = 'marketplace';
    const ANALYTICS = 'analytics';
    const CHECK_CACHE_SECONDS = 300;
    const CHECK_RETRY_SECONDS = 30;

    private $token;
    private $settings;

    public function __construct($token, shopMigratePluginWbSettings $settings = null)
    {
        $this->token = $this->normalizeToken($token);
        $this->settings = $settings;
    }

    /**
     * Reads the official Wildberries permission bit mask from the JWT payload
     * and, when requested, verifies the declared access with read-only API calls.
     *
     * @param shopMigratePluginWbApiClient|null $api
     * @param bool $probe_access
     * @return array
     * @throws waException
     */
    public function inspect(shopMigratePluginWbApiClient $api = null, $probe_access = false)
    {
        $payload = $this->decodePayload();
        if (!empty($payload['exp']) && (int) $payload['exp'] <= time()) {
            throw new waException(_wp('The Wildberries API token has expired. Reissue the token and try again.'));
        }
        if (!array_key_exists('s', $payload) || !$this->isIntegerValue($payload['s'])) {
            throw new waException(_wp('Unable to read Wildberries API token permissions. Reissue the token and try again.'));
        }

        $mask = (int) $payload['s'];
        $definitions = $this->getDefinitions();
        $permissions = array();
        foreach ($definitions as $code => $definition) {
            $permissions[$code] = ($mask & (1 << $definition['bit'])) !== 0;
        }

        $pending = array();
        if ($probe_access) {
            if (!$api) {
                throw new waException(_wp('Unable to check Wildberries API token permissions.'));
            }
            $this->probeAccess($api, $permissions, $pending);
        }

        return $this->buildReport($permissions, $pending);
    }

    private function buildReport(array $permissions, array $pending = array())
    {
        $missing = array();
        $definitions = $this->getDefinitions();
        foreach ($definitions as $code => $definition) {
            if (isset($permissions[$code]) && $permissions[$code] === false) {
                $missing[] = array(
                    'code'     => $code,
                    'label'    => $definition['label'],
                    'required' => !empty($definition['required']),
                );
            }
        }

        return array(
            'permissions'          => $permissions,
            'missing'              => $missing,
            'pending'              => array_values($pending),
            // A deferred probe does not prove the token lacks Content access.
            // The actual collection request will still be authorized by WB.
            'can_collect_products' => $permissions[self::CONTENT] !== false,
        );
    }

    public function buildResponse(array $report)
    {
        $can_collect_products = !empty($report['can_collect_products']);
        $pending = (array) ifset($report['pending'], array());
        $access_complete = empty($report['missing']) && !$pending;
        $permission_intro = '';
        $permission_outro = '';
        $permission_warnings = array();
        foreach ($pending as $category) {
            $seconds = max(1, (int) ifset($category['retry_at'], 0) - time());
            if ((string) ifset($category['reason'], '') === 'rate_limit') {
                $permission_warnings[] = sprintf(
                    _wp('The request limit for %s has been reached. Retry this category in %d seconds.'),
                    $category['label'], $seconds
                );
            } elseif ((string) ifset($category['reason'], '') === 'checking') {
                $permission_warnings[] = sprintf(
                    _wp('The %s access check is already in progress. Try again in %d seconds.'),
                    $category['label'], $seconds
                );
            } else {
                $permission_warnings[] = sprintf(
                    _wp('Access to %s could not be checked temporarily. Retry this category in %d seconds.'),
                    $category['label'], $seconds
                );
            }
        }
        if ($access_complete) {
            $message = _wp('Wildberries API access confirmed.');
        } elseif (empty($report['missing'])) {
            $message = _wp('The Wildberries API access check is incomplete. Confirmed results have been saved.');
        } elseif ($can_collect_products) {
            $message = _wp('Connection successful, but some Wildberries API categories are unavailable.');
            $permission_intro = _wp('The Wildberries API token does not provide access to the following API categories:');
            $permission_outro = _wp('Data from these categories will not be collected. Reissue the token with the missing permissions to import all available data.');
        } else {
            $message = _wp('The Wildberries API token cannot be used to collect products.');
            $permission_intro = _wp('The Wildberries API token does not provide access to the following API categories:');
            $permission_outro = _wp('Content access is required to collect product data. Reissue the token with the required permissions.');
        }

        return array(
            'access_complete'      => $access_complete,
            'permission_check_complete' => !$pending,
            'can_collect_products' => $can_collect_products,
            'permissions'          => (array) ifset($report['permissions'], array()),
            'missing_permissions'  => array_values((array) ifset($report['missing'], array())),
            'pending_permissions'  => array_values($pending),
            'message'              => $message,
            'permission_title'     => $pending && empty($report['missing'])
                ? _wp('Wildberries API access check is incomplete')
                : _wp('Wildberries API access is incomplete'),
            'permission_intro'     => $permission_intro,
            'permission_outro'     => $permission_outro,
            'permission_warnings'  => $permission_warnings,
        );
    }

    private function getDefinitions()
    {
        return array(
            self::CONTENT => array(
                'bit'      => 1,
                'label'    => _wp('Content'),
                'required' => true,
            ),
            self::PRICES => array(
                'bit'      => 3,
                'label'    => _wp('Prices and discounts'),
                'required' => false,
            ),
            self::MARKETPLACE => array(
                'bit'      => 4,
                'label'    => _wp('Marketplace'),
                'required' => false,
            ),
            self::ANALYTICS => array(
                'bit'      => 2,
                'label'    => _wp('Analytics (WB warehouse stocks)'),
                'required' => false,
            ),
        );
    }

    private function probeAccess(shopMigratePluginWbApiClient $api, array &$permissions, array &$pending)
    {
        // Without Content access the importer cannot collect any products.
        // Do not let an optional probe error hide this already definitive result.
        if (empty($permissions[self::CONTENT])) {
            return;
        }

        $probes = array(
            self::CONTENT     => 'testContentPermissionAccess',
            self::PRICES      => 'testPricesPermissionAccess',
            self::MARKETPLACE => 'testMarketplacePermissionAccess',
            self::ANALYTICS   => 'testAnalyticsPermissionAccess',
        );
        $this->settings = $this->settings ?: new shopMigratePluginWbSettings();
        $fingerprint = hash('sha256', $this->token);
        $lock = new shopMigratePluginWbOperationLock();
        $locked = $lock->acquire('permission_check');
        try {
            $state = $this->settings->getPermissionCheckState($fingerprint);
            foreach ($probes as $code => $method) {
                // Missing JWT bits need no HTTP probe, including after a reload.
                if ($permissions[$code] === false) {
                    continue;
                }
                $entry = (array) ifset($state[$code], array());
                if (in_array(ifset($entry['status']), array('available', 'unavailable'), true)
                    && (int) ifset($entry['expires_at'], 0) > time()
                ) {
                    $permissions[$code] = $entry['status'] === 'available';
                    if ($code === self::CONTENT && !$permissions[$code]) {
                        break;
                    }
                    continue;
                }
                if ((int) ifset($entry['retry_at'], 0) > time()) {
                    $this->deferCheck($code, $entry, $permissions, $pending);
                    continue;
                }
                if (!$locked) {
                    // Another tab/request owns the probes. Return its completed
                    // results and do not issue duplicate requests to WB.
                    $this->deferCheck($code, array(
                        'reason' => 'checking', 'retry_at' => time() + self::CHECK_RETRY_SECONDS,
                    ), $permissions, $pending);
                    continue;
                }

                // Persist before the network call as well, so a killed PHP
                // worker cannot cause an immediate burst on the next click.
                $state[$code] = array(
                    'status' => 'pending',
                    'reason' => 'checking',
                    'retry_at' => time() + self::CHECK_RETRY_SECONDS,
                );
                $this->settings->savePermissionCheckState($fingerprint, $state);
                try {
                    $api->$method();
                    $state[$code] = array('status' => 'available', 'expires_at' => time() + self::CHECK_CACHE_SECONDS);
                } catch (Throwable $e) {
                    $status = (int) $e->getCode();
                    if ($status === 401) {
                        // A rejected token invalidates even earlier successful
                        // category checks. Do not present cached access as valid.
                        $this->settings->savePermissionCheckState($fingerprint, array());
                        throw $e;
                    }
                    if ($status === 403) {
                        $state[$code] = array('status' => 'unavailable', 'expires_at' => time() + self::CHECK_CACHE_SECONDS);
                    } else {
                        $delay = self::CHECK_RETRY_SECONDS;
                        if (method_exists($e, 'getRetryAfterMs') && $e->getRetryAfterMs() !== null) {
                            $delay = max(1, (int) ceil($e->getRetryAfterMs() / 1000));
                        }
                        $state[$code] = array(
                            'status' => 'pending',
                            'reason' => $status === 429 ? 'rate_limit' : 'temporary',
                            'retry_at' => time() + $delay,
                        );
                    }
                }
                // Commit each category independently; a later 429 must never
                // erase already confirmed results or hide missing JWT rights.
                $this->settings->savePermissionCheckState($fingerprint, $state);
                if ($state[$code]['status'] === 'pending') {
                    $this->deferCheck($code, $state[$code], $permissions, $pending);
                } else {
                    $permissions[$code] = $state[$code]['status'] === 'available';
                    if ($code === self::CONTENT && !$permissions[$code]) {
                        break;
                    }
                }
            }
        } finally {
            if ($locked) {
                $lock->release('permission_check');
            }
        }
    }

    private function deferCheck($code, array $entry, array &$permissions, array &$pending)
    {
        $definitions = $this->getDefinitions();
        $permissions[$code] = null;
        $pending[$code] = array(
            'code' => $code,
            'label' => $definitions[$code]['label'],
            'reason' => (string) ifset($entry['reason'], 'temporary'),
            'retry_at' => (int) $entry['retry_at'],
        );
    }

    private function decodePayload()
    {
        if ($this->token === '') {
            throw new waException(_wp('Wildberries API token is empty.'));
        }
        $parts = explode('.', $this->token);
        if (count($parts) !== 3 || $parts[1] === '') {
            throw new waException(_wp('Unable to read Wildberries API token permissions. Reissue the token and try again.'));
        }

        $encoded = strtr($parts[1], '-_', '+/');
        $padding = strlen($encoded) % 4;
        if ($padding) {
            $encoded .= str_repeat('=', 4 - $padding);
        }
        $json = base64_decode($encoded, true);
        $payload = $json === false ? null : json_decode($json, true);
        if (!is_array($payload)) {
            throw new waException(_wp('Unable to read Wildberries API token permissions. Reissue the token and try again.'));
        }
        return $payload;
    }


    private function isIntegerValue($value)
    {
        return is_int($value)
            || (is_string($value) && preg_match('/^[0-9]+$/', $value));
    }

    private function normalizeToken($token)
    {
        $token = trim((string) $token);
        if (stripos($token, 'Bearer ') === 0) {
            $token = trim(substr($token, 7));
        }
        return $token;
    }
}
