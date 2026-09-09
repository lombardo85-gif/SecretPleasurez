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

class ttheadercall extends Module implements WidgetInterface
{
    private $templateFile;

    public function __construct()
    {
        $this->name = 'ttheadercall';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Silvertheme';
        $this->bootstrap = true;
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Silvertheme Header Call Block Information');
        $this->description = $this->l('Displays Footer Address and information.');

        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);

        $this->templateFile = 'module:ttheadercall/views/templates/hook/ttheadercall.tpl';
    }

    public function install()
    {
        
        $text1 = array();
        foreach (Language::getLanguages(false) as $lang) {
            $text1[$lang['id_lang']] = '44 Shirley Ave, West Chicago, IL 60185 Angelo';
             
              $text2[$lang['id_lang']] = '(949) 569-4371 / (630) 446-8851';
              
        }
        Configuration::updateValue('ttheadercall_text', $text1);
        Configuration::updateValue('ttheadercall_phone', $text2);

        return parent::install() &&
            $this->registerHook('displayNav1');
    }

    public function uninstall()
    {
       
        Configuration::deleteByName('ttheadercall_text');
        
        Configuration::deleteByName('ttheadercall_phone');
        

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
        $helper->submit_action = 'submitttheadercallModule';
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
                        'label' => $this->l('Header Text'),
                        'name' => 'ttheadercall_text',
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Header Phone'),
                        'name' => 'ttheadercall_phone',
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
            $data['ttheadercall_text'][$lang['id_lang']] = Configuration::get('ttheadercall_text', $lang['id_lang']);
            $data['ttheadercall_phone'][$lang['id_lang']] = Configuration::get('ttheadercall_phone', $lang['id_lang']);
        }

        return $data;
    }

    protected function postProcess()
    {
        if (((bool)Tools::isSubmit('submitttheadercallModule')) == true) {
            if (!($languages = Language::getLanguages(true))) {
                return false;
            }

            $text = array();
            foreach ($languages as $lang) {
                $text1[$lang['id_lang']] = Tools::getValue('ttheadercall_text_'.$lang['id_lang']);
               
                $text2[$lang['id_lang']] = Tools::getValue('ttheadercall_phone_'.$lang['id_lang']);
                
            }

            Configuration::updateValue('ttheadercall_text', $text1);
            Configuration::updateValue('ttheadercall_phone', $text2);
            

            
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
        $imgname = Configuration::get('ttheadercall_img');
        return array(
            'ttheadercall_text' => Configuration::get('ttheadercall_text', $id_lang),
            'ttheadercall_phone' => Configuration::get('ttheadercall_phone', $id_lang),
        );
    }
}
