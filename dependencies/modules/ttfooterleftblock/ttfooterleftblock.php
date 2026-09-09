<?php
/**
* 2015-2018 PrestaShop
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
*  @copyright 2015-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class ttfooterleftblock extends Module implements WidgetInterface
{
    private $templateFile;

    public function __construct()
    {
        $this->name = 'ttfooterleftblock';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Silvertheme';
        $this->bootstrap = true;
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Silvertheme Footer Left Information');
        $this->description = $this->l('Displays Footer Left logo and information.');

        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);

        $this->templateFile = 'module:ttfooterleftblock/views/templates/hook/ttfooterleftblock.tpl';
    }

    public function install()
    {
        if (file_exists(dirname(__FILE__).'/views/img/footer-logo.png'))
            Configuration::updateValue('ttfooterleftblock_img', 'footer-logo.png');

        $text1 = array();
        foreach (Language::getLanguages(false) as $lang) {
            $text1[$lang['id_lang']] = 'If you are going to use a passage of Lorem Ipsum, you need to be sure there isnt anything embarrassing hidden in the middle';
            $text2[$lang['id_lang']] = 'Contact';
        }
        Configuration::updateValue('ttfooterleftblock_desc', $text1);
         Configuration::updateValue('ttfooterleftblock_title', $text2);

        return parent::install() &&
            $this->registerHook('displayFooter');
    }

    public function uninstall()
    {
        Configuration::deleteByName('ttfooterleftblock_img');
        Configuration::deleteByName('ttfooterleftblock_desc');
        Configuration::deleteByName('ttfooterleftblock_title');

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
        $helper->submit_action = 'submitttfooterleftblockModule';
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
                        'type' => 'file',
                        'label' => $this->l('Footerleft Logo Image'),
                        'name' => 'ttfooterleftblock_img',
                        'thumb' => '../modules/'.$this->name.'/views/img/'.Configuration::get('ttfooterleftblock_img'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Footerleft Title of Store'),
                        'name' => 'ttfooterleftblock_title',
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Footerleft Description of Store'),
                        'name' => 'ttfooterleftblock_desc',
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

        $data = array(
            'ttfooterleftblock_img' => Tools::getValue('ttfooterleftblock_img', Configuration::get('ttfooterleftblock_img')),
        );

        foreach ($languages as $lang) {
            $data['ttfooterleftblock_desc'][$lang['id_lang']] = Configuration::get('ttfooterleftblock_desc', $lang['id_lang']);
            $data['ttfooterleftblock_title'][$lang['id_lang']] = Configuration::get('ttfooterleftblock_title', $lang['id_lang']);
        }

        return $data;
    }

    protected function postProcess()
    {
        if (((bool)Tools::isSubmit('submitttfooterleftblockModule')) == true) {
            if (!($languages = Language::getLanguages(true))) {
                return false;
            }

            $text = array();
            foreach ($languages as $lang) {
                $text1[$lang['id_lang']] = Tools::getValue('ttfooterleftblock_desc_'.$lang['id_lang']);
                $text2[$lang['id_lang']] = Tools::getValue('ttfooterleftblock_title_'.$lang['id_lang']);
            }

            Configuration::updateValue('ttfooterleftblock_desc', $text1);
            Configuration::updateValue('ttfooterleftblock_title', $text2);

            if (isset($_FILES['ttfooterleftblock_img']) && isset($_FILES['ttfooterleftblock_img']['tmp_name']) && !empty($_FILES['ttfooterleftblock_img']['tmp_name']))
            {
                if ($error = ImageManager::validateUpload($_FILES['ttfooterleftblock_img'], 4000000)) {
                    return $error;
                } else {
                    $ext = Tools::substr($_FILES['ttfooterleftblock_img']['name'], strrpos($_FILES['ttfooterleftblock_img']['name'], '.') + 1);
                    $file_name = 'footer-logo'.'.'.$ext;
                    if (!move_uploaded_file($_FILES['ttfooterleftblock_img']['tmp_name'], dirname(__FILE__).'/views/img/'.$file_name)) {
                        return $this->displayError($this->l('An error occurred while attempting to upload the file.'));
                    } else {
                        if (Configuration::hasContext('ttfooterleftblock_img', null, Shop::getContext()) && Configuration::get('ttfooterleftblock_img') != $file_name)
                            @unlink(dirname(__FILE__).'/views/img/'.Configuration::get('ttfooterleftblock_img'));
                        Configuration::updateValue('ttfooterleftblock_img', $file_name);
                        $this->_clearCache($this->templateFile);
                        return $this->displayConfirmation($this->l('The settings have been updated.'));
                    }
                }
            }
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
        $imgname = Configuration::get('ttfooterleftblock_img');
        return array(
            'ttfooterleftblock_img' => $this->context->link->protocol_content.Tools::getMediaServer($imgname).$this->_path.'/views/img/'.$imgname,
            'ttfooterleftblock_title' => Configuration::get('ttfooterleftblock_title', $id_lang),
            'ttfooterleftblock_desc' => Configuration::get('ttfooterleftblock_desc', $id_lang),
        );
    }
}
