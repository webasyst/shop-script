<?php

return array(
    'states' => array(
        'new' => array (
            'name' => _w('New'),
            'options' => array (
                'icon' => 'icon16 ss new',
                'style' => array(
                    'color' => '#009900',
                    'font-weight' => 'bold',
                )
            ),
            'available_actions' => array(
                'process',
                'pay',
                'ship',
                'complete',
                'comment',
                'split',
                'edit',
                'message',
                'delete',
            )
        ),
        'processing' => array(
            'name' => _w('Confirmed'),
            'options' => array(
                'icon' => 'icon16 ss confirmed',
                'style' => array(
                    'color' => '#008800',
                    'font-style' => 'italic',
                )
            ),
            'available_actions' => array(       
                'pay',
                'ship', 
                'complete',
                'comment',
                'split',                
                'edit',
                'message',
                'delete'
            )
        ),
        'paid' => array(
            'name' => _w('Paid'),
            'options' => array(
                'icon' => 'icon16 ss flag-yellow',
                'style' => array(
                    'color' => '#FF9900',
                    'font-weight' => 'bold',
                    'font-style' => 'italic',
                )
            ),
            'available_actions' => array(
                'ship',
                'complete',
                'refund',
                'comment',
                'message',
            )
        ),
        'shipped' => array(
            'name' => _w('Sent'),
            'options' => array(
                'icon' => 'icon16 ss sent',
                'style' => array(
                    'color' => '#0000FF',
                    'font-style' => 'italic',
                )
            ),
            'available_actions' => array(
                'complete',
                'comment',
                'delete',
                'message',
            )
        ),
        'completed' => array(
            'name' => _w('Completed'),
            'options' => array (
                'icon' => 'icon16 ss completed',
                'style' => array(
                    'color' => '#800080',
                )
            ),
            'available_actions' => array(
                'comment',
                'refund',
                'message',
            )
        ),
        'refunded' => array(
            'name' => _w('Refunded'),
            'options' => array(
                'icon' => 'icon16 ss refunded',
                'style' => array(
                    'color' => '#cc0000',
                )
            ),
            'available_actions' => array(
                'message',
            )
        ),
        'deleted' => array(
            'name' => _w('Deleted'),
            'options' => array(
                'icon' => 'icon16 ss trash',
                'style' => array(
                    'color' => '#aaaaaa'
                )
            ),
            'available_actions' => array(
                'restore',
                'message',
            )
        ),

    ),
    'actions' => array(
        'create' => array(
            'classname' => 'shopWorkflowCreateAction',
            'name' => _w('Create'),
            'options' => array(
                'log_record' => _w('Order was placed'),
            ),
            'state' => 'new'
        ),
        'process' => array(
            'classname' => 'shopWorkflowProcessAction',
            'name' => _w('Process'),
            'options' => array(
                'log_record' => _w('Order was confirmed and accepted for processing'),
                'button_class' => 'green'
            ),
            'state' => 'processing'
        ),
        'pay' => array(
            'classname' => 'shopWorkflowPayAction',
            'name' => _w('Paid'),
            'options' => array(
                'log_record' => _w('Order was paid'),
                'button_class' => 'yellow'
            ),
            'state' => 'paid'
        ),        
        'ship' => array(
            'classname' => 'shopWorkflowShipAction',
            'name' => _w('Sent'),
            'options' => array(
                'log_record' => _w('Order was shipped'),
                'button_class' => 'blue'
            ),
            'state' => 'shipped'
        ),
        'refund' => array(
            'classname' => 'shopWorkflowRefundAction',
            'name' => _w('Refund'),
            'options' => array(
                'log_record' => _w('Order was refunded'),
                'button_class' => 'red'
            ),            
            'state' => 'refunded'
        ),
/*
        'split' => array(
            'classname' => 'shopWorkflowSplitAction',
            'name' => _w('Split order'),
            
        ),
*/
        'edit' => array(
            'classname' => 'shopWorkflowEditAction',
            'name' => _w('Edit order'),
            'options' => array(
                'position' => 'top',
                'icon' => 'edit',
                'log_record' => _w('Order was edited'),
            )
        ),
        'delete' => array(
            'classname' => 'shopWorkflowDeleteAction',
            'name' => _w('Delete'),
            'options' => array(
//                'position' => 'top',
//                'icon' => 'delete',
                'log_record' => _w('Order was deleted'),
            ),
            'state' => 'deleted',
        ),
        'restore' => array(
            'classname' => 'shopWorkflowRestoreAction',
            'name' => _w('Restore'),
            'options' => array(
                'icon' => 'restore',
                'log_record' => _w('Order was re-opened'),
                'button_class' => 'green',
            ),
        ),
        'complete' => array(
            'classname' => 'shopWorkflowCompleteAction',
            'name' => _w('Mark as Completed'),
            'options' => array(
                'log_record' => _w('Order was marked as completed'),
                'button_class' => 'purple'
            ),            
            'state' => 'completed'
        ),

        'message' => array(
            'classname' => 'shopWorkflowMessageAction',
            'name' => _w('Contact customer'),
            'options' => array(
                'position' => 'top',
                'icon' => 'email',
                'log_record' => _w('Message was sent'),
            ),
        ),

        'comment' => array(
            'classname' => 'shopWorkflowCommentAction',
            'name' => _w('Add comment'),
            'options' => array(
                'position' => 'bottom',
                'icon' => 'add',
                'button_class' => 'inline-link',
                'log_record' => _w('A comment was added for the order'),
            ),
        ),
        'callback' => array(
            'classname' => 'shopWorkflowCallbackAction',
            'name' => _w('Callback'),
            'options' => array(
                'log_record' => _w('Callback'),
            ),

        )
    )
);
