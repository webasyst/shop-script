<?php


/**  @var shopConfig $this */
if (!empty($this) && ($this instanceof waAppConfig)) {
    $app_config = $this;
} else {
    $app_config = wa('shop')->getConfig();
}
/** @var shopConfig $app_config */

$file = $app_config->getAppsPath('shop', 'lib/config/data/workflow.php');
if (file_exists($file)) {
    $original_config = include($file);
} else {
    $original_config = array();
}

$compare = function ($a, $b, &$compare_function) {
    if (gettype($a) !== gettype($b)) {
        return false;
    } else {
        switch (gettype($a)) {
            case "array":
                foreach ($a as $key => $value) {
                    if (is_array($value)) {
                        if (!isset($b[$key]) || !is_array($b[$key])) {
                            return false;
                        } else {
                            if (!$compare_function($value, $b[$key], $compare_function)) {
                                return false;
                            }
                        }
                    } elseif (!array_key_exists($key, $b) || $b[$key] !== $value) {
                        return false;
                    }
                }
                return true;
                break;
            default:
                return $a === $b;
        }
    }
};


$config = shopWorkflow::getConfig();
$changed = false;

$states = array(
    'auth',
);

$compare_state_fields = array(
    'available_actions',
);

$repair_state_fields = array(
    'name',
    'options',
);


$state_collisions = array();
foreach ($states as $state) {
    if (isset($original_config['states'][$state])) {
        if (isset($config['states'][$state])) {
            foreach ($compare_state_fields as $field) {
                $original_value = ifset($original_config, 'states', $state, $field, null);
                $value = ifset($config, 'states', $state, $field, null);

                $same = $compare($value, $original_value, $compare);

                if (!$same) {
                    $state_collisions[$state] = $state;
                    break;
                }
            }

            if (!isset($state_collisions[$state])) {
                foreach ($repair_state_fields as $field) {
                    $original_value = ifset($original_config, 'states', $state, $field, null);
                    $value = ifset($config, 'states', $state, $field, null);
                    $same = $compare($value, $original_value, $compare);

                    if (!$same) {
                        unset($config['states'][$state]);
                        break;
                    }
                }
            }
        }
    }
}


foreach ($state_collisions as $state => &$custom_state) {
    $suffix = 0;
    do {
        $custom_state = $state.(++$suffix);
    } while (isset($config['state'][$custom_state]));
    unset($custom_state);
}

foreach ($state_collisions as $state => $custom_state) {
    $config['states'][$custom_state] = $config['states'][$state];
    unset($config['states'][$state]);
    foreach ($config['actions'] as &$action) {
        if (isset($action['state']) && ($action['state'] === $state)) {
            $action['state'] = $custom_state;
            $changed = true;
        }
        unset($action);
    }
}


# Add new states
foreach ($states as $state) {
    if (isset($original_config['states'][$state])) {
        if (!isset($config['states'][$state])) {
            #add new state into list;
            $offsets = array_keys($original_config['states']);
            $offset = array_search($state, $offsets, true);
            if ($offset) {
                $previous_state = $offsets[$offset - 1];
                $offsets = array_keys($config['states']);
                $offset = max(0, array_search($previous_state, $offsets, true) + 1);
            } else {
                $offset = 0;
            }

            $config['states'] = array_slice($config['states'], 0, $offset, true)
                + array(
                    $state => $original_config['states'][$state],
                )
                + array_slice($config['states'], $offset, null, true);

            //$config['states'][$state] = $original_config['states'][$state];
            $changed = true;
        }
    }
}


$actions = array(
    'auth',
    'cancel',
    'capture',
);

//check collision
$compare_action_fields = array(
    'internal',
    'classname',
);

$repair_action_fields = array(
    'state',
    'name',
    'options',
);
$action_collisions = array();
foreach ($actions as $action) {
    if (isset($original_config['actions'][$action])) {
        $original_action = $original_config['actions'][$action];
        if (isset($config['actions'][$action])) {
            foreach ($compare_action_fields as $field) {
                $original_value = ifset($original_action, $field, null);
                $value = ifset($config, 'actions', $action, $field, null);
                $same = $compare($value, $original_value, $compare);

                if (!$same) {
                    $action_collisions[$action] = $action;
                    break;
                }
            }
            if (!isset($action_collisions[$action])) {
                foreach ($repair_action_fields as $field) {
                    $original_value = ifset($original_action, $field, null);
                    $value = ifset($config, 'actions', $action, $field, null);
                    $same = $compare($value, $original_value, $compare);

                    if (!$same) {
                        unset($config['actions'][$action]);
                        break;
                    }
                }
            }
        }
    }
}

foreach ($action_collisions as $action => &$custom_action) {
    $suffix = 0;
    do {
        $custom_action = $action.(++$suffix);
    } while (isset($config['actions'][$custom_action]));
    unset($custom_action);
}

foreach ($action_collisions as $action => $custom_action) {
    $config['actions'][$custom_action] = $config['actions'][$action];
    unset($config['actions'][$action]);
    foreach ($config['states'] as &$state) {
        if (isset($state['available_actions'])) {
            $used = array_search($action, $state['available_actions'], true);
            if ($used !== false) {
                unset($state['available_actions'][$used]);
                $state['available_actions'][] = $custom_action;
                $changed = true;
            }
        }
        unset($state);
    }
}


foreach ($actions as $action) {
    if (isset($original_config['actions'][$action])) {
        if (!isset($config['actions'][$action])) {
            #add new action into list;
            $config['actions'][$action] = $original_config['actions'][$action];
            $changed = true;

            foreach ($original_config['states'] as $state_id => $original_state) {
                if (isset($original_state['available_actions'])
                    && in_array($action, $original_state['available_actions'], true)
                    && isset($config['states'][$state_id])
                ) {

                    #add action as available into state
                    $state = &$config['states'][$state_id];
                    if (empty($state['available_actions'])) {
                        $state['available_actions'] = array();
                    }
                    $state['available_actions'][] = $action;
                    $state['available_actions'] = array_values(array_unique($state['available_actions']));
                    unset($state);
                }
            }
        }
    }
}

if ($changed) {
    shopWorkflow::setConfig($config);
}
