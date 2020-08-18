<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ExpressPay extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'expresspay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'ExpressPay';
        $this->controllers = array('validation', 'redirect');
        $this->is_eu_compatible = 0;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('ExpressPay');
        $this->description = $this->l('Accept payments via ExpressPay (mobile money, Debit/Credit Card)');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
            return false;
        }

        if (!$this->createPaidOrderState() || !$this->createPendingOrderState()) {
            return false;
        }

        return true;
    }

    protected function createPaidOrderState() {
        $newState = new OrderState();
        
        $newState->send_email = true;
        $newState->module_name = $this->name;
        $newState->invoice = true;
        $newState->color = '#32CD32';
        $newState->unremovable = false;
        $newState->logable = true;
        $newState->delivery = false;
        $newState->hidden = false;
        $newState->shipped = false;
        $newState->paid = true;
        $newState->delete = false;

        $languages = Language::getLanguages(true);
        foreach ($languages as $lang) {
            $newState->name[(int)$lang['id_lang']] = 'Payment successful';
            $newState->template = $this->name;
        }

        if ($newState->add()) {
            Configuration::updateValue('PS_OS_EXPRESSPAY_PAID', $newState->id);
        }
        else {
            return false;
        }
        return true;
    }

    protected function createPendingOrderState() {
        $newState = new OrderState();
        $newState->send_email = false;
        $newState->module_name = $this->name;
        $newState->invoice = false;
        $newState->color = '#eeeeee';
        $newState->unremovable = false;
        $newState->logable = false;
        $newState->delivery = false;
        $newState->hidden = false;
        $newState->shipped = false;
        $newState->paid = false;
        $newState->delete = false;

        $languages = Language::getLanguages(true);
        foreach ($languages as $lang) {
            $newState->name[(int)$lang['id_lang']] = 'Payment pending';
            $newState->template = $this->name;
        }

        if ($newState->add()) {
            Configuration::updateValue('PS_OS_EXPRESSPAY_PENDING', $newState->id);
        }
        else {
            return false;
        }
        return true;
    }

    public function getContent()
    {
        if (Tools::isSubmit('submit_expresspay')) {
            $this->postProcess();
        }

        $this->context->smarty->assign(array('module_dir' => $this->_path));

        return $this->renderConfigForm();
    }

    protected function renderConfigForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit_expresspay';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => self::getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->l('Environment'),
                        'name' => 'expresspay_environment',
                        'desc' => $this->l('Choose between sandbox for development, or live for production'),
                        'options' => array(
                            'query' => [['id_option' => 'sandbox', 'name' => 'Sandbox'], ['id_option' => 'live', 'name' => 'Live']],
                            'id' => 'id_option',
                            'name' => 'name',
                        ),
                    ),

                    array(
                        'type' => 'text',
                        'label' => $this->l('Merchant ID'),
                        'name' => 'expresspay_merchant_id',
                        'desc' => $this->l('Your Merchant ID assigned by expressPay'),
                    ),

                    array(
                        'type' => 'text',
                        'label' => $this->l('API Key'),
                        'name' => 'expresspay_api_key',
                        'desc' => $this->l('Your API Key assigned by expressPay'),
                    ),
                ),

                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    public function getConfigFormValues()
    {
        return array(
            'expresspay_environment' => Configuration::get('expresspay_environment', true),
            'expresspay_merchant_id' => Configuration::get('expresspay_merchant_id', true),
            'expresspay_api_key' => Configuration::get('expresspay_api_key', true),
        );
    }

    protected function postProcess()
    {
        $form_values = self::getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    public function hookModuleRoutes()
    {
        return [
            'module-expresspay-webhook' => [
                'rule' => 'expresspay/webhook',
                'keywords' => [],
                'controller' => 'webhook',
                'params' => [
                    'fc' => 'module',
                    'module' => 'expresspay',
                ],
            ],
            'module-expresspay-return' => [
                'rule' => 'expresspay/return',
                'keywords' => [],
                'controller' => 'return',
                'params' => [
                    'fc' => 'module',
                    'module' => 'expresspay',
                ],
            ],
        ];
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = [
            $this->getOfflinePaymentOption(),
        ];

        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (!in_array($currency_order->iso_code, ['GHS'])) {
            return false;
        }

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getOfflinePaymentOption()
    {
        $offlineOption = new PaymentOption();
        $offlineOption
            ->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true))
            ->setAdditionalInformation('<p>' . $this->description . '</p>')
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/logo.png'));

        return $offlineOption;
    }

    protected function generateForm()
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = sprintf("%02d", $i);
        }

        $years = [];
        for ($i = 0; $i <= 10; $i++) {
            $years[] = date('Y', strtotime('+'.$i.' years'));
        }

        $this->context->smarty->assign([
            'action' => $this->context->link->getModuleLink($this->name, 'validation', array(), true),
            'months' => $months,
            'years' => $years,
        ]);

        return $this->context->smarty->fetch('module:expresspay/views/templates/front/payment_form.tpl');
    }
}
