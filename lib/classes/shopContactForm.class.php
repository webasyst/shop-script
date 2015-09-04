<?php

class shopContactForm extends waContactForm
{
    protected $antispam;
    protected $antispam_captcha;

    public function __construct($fields = array(), $options = array())
    {
        parent::__construct($fields, $options);
        $this->antispam = wa('shop')->getSetting('checkout_antispam');
        if ($this->antispam) {
            $this->antispam_captcha = wa('shop')->getSetting('checkout_antispam_captcha');
        }
    }

    public function html($field_id = null, $with_errors = true, $placeholders = false)
    {
        $html = parent::html($field_id, $with_errors, $placeholders);
        if (!$field_id && $this->antispam) {
            if ($this->antispam_captcha) {
                $html .= '<div class="wa-field"><div class="wa-value">';
                $html .= wa('shop')->getCaptcha()->getHtml(ifset($this->errors['captcha']));
                if (isset($this->errors['captcha'])) {
                    $html .= '<em class="wa-error-msg">'.$this->errors['captcha'].'</em>';
                }
                $html .= '</div></div>';
            } else {
                $code = waString::uuid();
                wa()->getStorage()->set('shop/checkout_code', $code);
                $html .= '<input type="hidden" name="checkout_code" value="">';
                $html .= <<<HTML
<script type="text/javascript">$('input[name="checkout_code"]').val("{$code}");</script>
HTML;
            }
            $html .= '<input type="text" style="display: none" name="address" value=" ">';
        }
        return $html;
    }

    public function isValidAntispam()
    {
        if ($this->antispam) {
            if (waRequest::method() == 'post') {
                $is_spam = false;
                if ($this->antispam_captcha) {
                    if (!wa('shop')->getCaptcha()->isValid(null, $error)) {
                        $this->errors['captcha'] = $error ? $error : _ws('Invalid captcha');
                    }
                } else {
                    $checkout_code = waRequest::post('checkout_code');
                    if (!$checkout_code || ($checkout_code !== wa()->getStorage()->get('shop/checkout_code'))) {
                        $is_spam = true;
                    }
                }
                $check_address = waRequest::post('address');
                if ($check_address !== ' ') {
                    $is_spam = true;
                }
                if ($is_spam) {
                    $this->errors['spam'] = _w('Something went wrong while processing your data. Please contact store team directly regarding your order. A notification about this error has been sent to the store admin.');
                }
            }
            return empty($this->errors['spam']) && empty($this->errors['captcha']);
        }
        return true;
    }

    public static function loadConfig($file, $options = array())
    {
        $config = self::readConfig($file);
        $form = new self($config['fields'], $options);
        $form->setValue($config['values']);
        return $form;
    }
}