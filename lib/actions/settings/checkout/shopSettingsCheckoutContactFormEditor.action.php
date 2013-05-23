<?php

/**
 * Helper for shopSettingsCheckoutContactFormAction.
 * Represents advanced field settings, for one of several field types.
 */
class shopSettingsCheckoutContactFormEditorAction extends waViewAction
{
    public function execute()
    {
        $f = waRequest::param('f');
        $fid = waRequest::param('fid', waRequest::post('fid'));
        $prefix = waRequest::param('prefix', waRequest::post('prefix', 'options'));
        $full_parent = waRequest::param('parent', waRequest::post('parent', null));

        $parent = explode('.', $full_parent);
        $parent = $parent[0];

        $new_field = false;
        if ($f && $f instanceof waContactField) {
            $ftype = $f->getType();
            if ($ftype == 'Select') {
                if ($f instanceof waContactBranchField) {
                    $ftype = 'branch';
                } else if ($f instanceof waContactRadioSelectField) {
                    $ftype = 'radio';
                }
            }
        } else {
            $ftype = strtolower(waRequest::param('ftype', waRequest::post('ftype', 'string')));
            $f = self::getField($fid, $ftype);
            $new_field = true;
        }
        $ftype = strtolower($ftype);

        $this->view->assign('f', $f);
        $this->view->assign('fid', $fid);
        $this->view->assign('ftype', $ftype);
        $this->view->assign('prefix', $prefix);
        $this->view->assign('parent', $parent);
        $this->view->assign('uniqid', 'fe_'.uniqid());
        $this->view->assign('new_field', $new_field);
    }

    public static function getField($fid, $ftype)
    {

        $f = shopCheckoutContactinfo::createFromOpts(array(
            '_type' => $ftype
        ));
        if (!$f) {
            throw new waException('Unknown field type: '.$ftype.' ('.$class.')');
        }
        return $f;
    }
}

