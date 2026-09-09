<?php
/**
* 2015-2019 PrestaShop
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2015-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class ttfooteraddress extends Module implements WidgetInterface
{
    private $templateFile;

    public function __construct()
    {
        $this->name = 'ttfooteraddress';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Silvertheme';
        $this->bootstrap = true;
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Silvertheme Footer Left Address Information');
        $this->description = $this->l('Displays Footer Address and information.');

        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);

        $this->templateFile = 'module:ttfooteraddress/views/templates/hook/ttfooteraddress.tpl';
    }

    public function install()
    {
        
        $text1 = array();
        foreach (Language::getLanguages(false) as $lang) {
            $text1[$lang['id_lang']] = 'Address: 44 Demo Store, West Chicago, IL 12345 Angelo';
             $text2[$lang['id_lang']] = 'demo@prestahsop.com';
              $text3[$lang['id_lang']] = 'Call Us: (123) 456-7890';
              $text4[$lang['id_lang']] = 'Contact';
        }
        Configuration::updateValue('ttfooteraddress_addr', $text1);
         Configuration::updateValue('ttfooteraddress_email', $text2);
          Configuration::updateValue('ttfooteraddress_phone', $text3);
          Configuration::updateValue('ttfooteraddress_title', $text4);

        return parent::install() &&
            $this->registerHook('displayFooter');
    }

    public function uninstall()
    {
       
        Configuration::deleteByName('ttfooteraddress_addr');
        Configuration::deleteByName('ttfooteraddress_email');
        Configuration::deleteByName('ttfooteraddress_phone');
        Configuration::deleteByName('ttfooteraddress_title');

        return parent::uninstall();
    }

    public function getContent()
    {
        return $this->postProcess().$this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitttfooteraddressModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
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
                        'type' => 'text',
                        'label' => $this->l('Footer Title'),
                        'name' => 'ttfooteraddress_title',
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Footer Store Address'),
                        'name' => 'ttfooteraddress_addr',
                        'lang' => true,
                    ),
                     array(
                        'type' => 'text',
                        'label' => $this->l('Footer Email'),
                        'name' => 'ttfooteraddress_email',
                        'lang' => true,
                    ),
                      array(
                        'type' => 'text',
                        'label' => $this->l('Footer Contact'),
                        'name' => 'ttfooteraddress_phone',
                        'lang' => true,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        if (!($languages = Language::getLanguages(true)))
            return false;

        

        foreach ($languages as $lang) {
            $data['ttfooteraddress_addr'][$lang['id_lang']] = Configuration::get('ttfooteraddress_addr', $lang['id_lang']);
            $data['ttfooteraddress_email'][$lang['id_lang']] = Configuration::get('ttfooteraddress_email', $lang['id_lang']);
            $data['ttfooteraddress_phone'][$lang['id_lang']] = Configuration::get('ttfooteraddress_phone', $lang['id_lang']);
            $data['ttfooteraddress_title'][$lang['id_lang']] = Configuration::get('ttfooteraddress_title', $lang['id_lang']);
        }

        return $data;
    }

    protected function postProcess()
    {
        if (((bool)Tools::isSubmit('submitttfooteraddressModule')) == true) {
            if (!($languages = Language::getLanguages(true))) {
                return false;
            }

            $text = array();
            foreach ($languages as $lang) {
                $text1[$lang['id_lang']] = Tools::getValue('ttfooteraddress_addr_'.$lang['id_lang']);
                $text2[$lang['id_lang']] = Tools::getValue('ttfooteraddress_email_'.$lang['id_lang']);
                $text3[$lang['id_lang']] = Tools::getValue('ttfooteraddress_phone_'.$lang['id_lang']);
                $text4[$lang['id_lang']] = Tools::getValue('ttfooteraddress_title_'.$lang['id_lang']);
            }

            Configuration::updateValue('ttfooteraddress_addr', $text1);
            Configuration::updateValue('ttfooteraddress_email', $text2);
            Configuration::updateValue('ttfooteraddress_phone', $text3);
            Configuration::updateValue('ttfooteraddress_title', $text4);

            
            $this->_clearCache($this->templateFile);
        }
        return '';
    }

    public function renderWidget($hookName, array $configuration)
    {
        if (!$this->isCached($this->templateFile, $this->getCacheId(''))) {
            $variables = $this->getWidgetVariables($hookName, $configuration);

            if (empty($variables)) {
                return false;
            }

            $this->smarty->assign($variables);
        }

        return $this->fetch($this->templateFile, $this->getCacheId(''));
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        $id_lang = $this->context->cart->id_lang;
        $imgname = Configuration::get('ttfooteraddress_img');
        return array(
            'ttfooteraddress_addr' => Configuration::get('ttfooteraddress_addr', $id_lang),
            'ttfooteraddress_email' => Configuration::get('ttfooteraddress_email', $id_lang),
            'ttfooteraddress_phone' => Configuration::get('ttfooteraddress_phone', $id_lang),
            'ttfooteraddress_title' => Configuration::get('ttfooteraddress_title', $id_lang),
        );
    }
}
