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

/**
* @since   1.7.0
*/


if (!defined('_PS_VERSION_')) {
	exit;
}

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

include_once(_PS_MODULE_DIR_.'ttsubbanner/TtSubBan.php');

class TtSubBanner extends Module implements WidgetInterface
{
	protected $_html = '';
	private $templateFile;

	public function __construct()
	{
		$this->name = 'ttsubbanner';
		$this->tab = 'front_office_features';
		$this->version = '5.0.0';
		$this->author = 'Silvertheme';
		$this->need_instance = 0;
		$this->secure_key = Tools::encrypt($this->name);
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Silvertheme Sub Banners Block');
		$this->description = $this->l('Adds a block to display banners on homepage.');
		$this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);

		$this->templateFile = 'module:ttsubbanner/views/templates/hook/ttsubbanner.tpl';
	}

	/**
     * @see Module::install()
     */
	public function install()
	{
		/* Adds Module */
		if (parent::install() &&
			$this->registerHook('displayHome') &&
			$this->registerHook('actionShopDataDuplication')
		) {
			$shops = Shop::getContextListShopID();
			$shop_groups_list = array();

			/* Setup each shop */
			foreach ($shops as $shop_id) {
				$shop_group_id = (int)Shop::getGroupFromShop($shop_id, true);

				if (!in_array($shop_group_id, $shop_groups_list)) {
					$shop_groups_list[] = $shop_group_id;
				}
			}

			/* Creates tables */
			$res = $this->createTables();

			/* Adds samples */
			if ($res) {
				$this->installSamples();
			}

			// Disable on mobiles and tablets
			// $this->disableDevice(Context::DEVICE_MOBILE);

			return (bool)$res;
		}

		return false;
	}

	protected function installSamples()
	{
		$languages = Language::getLanguages(false);
		for ($i = 1; $i <= 2; ++$i) {
			$slide = new TtSubBan();
			$slide->position = $i;
			$slide->active = 1;
			foreach ($languages as $language) {
				$slide->title[$language['id_lang']] = 'Sample '.$i;
				$slide->url[$language['id_lang']] = '#';
				$slide->image[$language['id_lang']] = 'banner-'.$i.'.jpg';
			}
			$slide->add();
		}
	}

	/**
     * @see Module::uninstall()
     */
	public function uninstall()
	{
		/* Deletes Module */
		if (parent::uninstall()) {
			/* Deletes tables */
			$res = $this->deleteTables();

			return (bool)$res;
		}

		return false;
	}

	/**
     * Creates tables
     */
	protected function createTables()
	{
		/* Banners */
		$res = (bool)Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'ttsubbanner` (
				`id_homeslider_slides` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`id_shop` int(10) unsigned NOT NULL,
				PRIMARY KEY (`id_homeslider_slides`, `id_shop`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=UTF8;
		');

		/* Banners configuration */
		$res &= Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'ttsubbanner_slides` (
			  `id_homeslider_slides` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `position` int(10) unsigned NOT NULL DEFAULT \'0\',
			  `active` tinyint(1) unsigned NOT NULL DEFAULT \'0\',
			  PRIMARY KEY (`id_homeslider_slides`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=UTF8;
		');

		/* Banners lang configuration */
		$res &= Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'ttsubbanner_slides_lang` (
			  `id_homeslider_slides` int(10) unsigned NOT NULL,
			  `id_lang` int(10) unsigned NOT NULL,
			  `title` varchar(255) NOT NULL,
			  `url` varchar(255) NOT NULL,
			  `image` varchar(255) NOT NULL,
			  PRIMARY KEY (`id_homeslider_slides`,`id_lang`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=UTF8;
		');

		return $res;
	}

	/**
     * deletes tables
     */
	protected function deleteTables()
	{
		$slides = $this->getSlides();
		foreach ($slides as $slide) {
			$to_del = new TtSubBan($slide['id_slide']);
			$to_del->delete();
		}

		return Db::getInstance()->execute('
			DROP TABLE IF EXISTS `'._DB_PREFIX_.'ttsubbanner`, `'._DB_PREFIX_.'ttsubbanner_slides`, `'._DB_PREFIX_.'ttsubbanner_slides_lang`;
		');
	}

	public function getContent()
	{
		$this->_html .= $this->headerHTML();

		/* Validate & process */
		if (Tools::isSubmit('submitSlide') || Tools::isSubmit('delete_id_slide') ||
			Tools::isSubmit('changeStatus')
		) {
			if ($this->_postValidation()) {
				$this->_postProcess();
				$this->_html .= $this->renderList();
			} else {
				$this->_html .= $this->renderAddForm();
			}

			$this->clearCache();
		} elseif (Tools::isSubmit('addSlide') || (Tools::isSubmit('id_slide') && $this->slideExists((int)Tools::getValue('id_slide')))) {
			if (Tools::isSubmit('addSlide')) {
				$mode = 'add';
			} else {
				$mode = 'edit';
			}

			if ($mode == 'add') {
				if (Shop::getContext() != Shop::CONTEXT_GROUP && Shop::getContext() != Shop::CONTEXT_ALL) {
					$this->_html .= $this->renderAddForm();
				} else {
					$this->_html .= $this->getShopContextError(null, $mode);
				}
			} else {
				$associated_shop_ids = TtSubBan::getAssociatedIdsShop((int)Tools::getValue('id_slide'));
				$context_shop_id = (int)Shop::getContextShopID();

				if ($associated_shop_ids === false) {
					$this->_html .= $this->getShopAssociationError((int)Tools::getValue('id_slide'));
				} elseif (Shop::getContext() != Shop::CONTEXT_GROUP && Shop::getContext() != Shop::CONTEXT_ALL && in_array($context_shop_id, $associated_shop_ids)) {
					if (count($associated_shop_ids) > 1) {
						$this->_html = $this->getSharedSlideWarning();
					}
					$this->_html .= $this->renderAddForm();
				} else {
					$shops_name_list = array();
					foreach ($associated_shop_ids as $shop_id) {
						$associated_shop = new Shop((int)$shop_id);
						$shops_name_list[] = $associated_shop->name;
					}
					$this->_html .= $this->getShopContextError($shops_name_list, $mode);
				}
			}
		} else {
			
			
			if (Shop::getContext() != Shop::CONTEXT_GROUP && Shop::getContext() != Shop::CONTEXT_ALL) {
				$this->_html .= $this->renderList();
			}
		}

		return $this->_html;
	}

	protected function _postValidation()
	{
		$errors = array();

		/* Validation for Slider configuration */
		if (Tools::isSubmit('changeStatus')) {
			if (!Validate::isInt(Tools::getValue('id_slide'))) {
				$errors[] = $this->l('Invalid banner');
			}
		} elseif (Tools::isSubmit('submitSlide')) {
			/* Checks state (active) */
			if (!Validate::isInt(Tools::getValue('active_slide')) || (Tools::getValue('active_slide') != 0 && Tools::getValue('active_slide') != 1)) {
				$errors[] = $this->l('Invalid banner state.');
			}
			/* Checks position */
			if (!Validate::isInt(Tools::getValue('position')) || (Tools::getValue('position') < 0)) {
				$errors[] = $this->l('Invalid banner position.');
			}
			/* If edit : checks id_slide */
			if (Tools::isSubmit('id_slide')) {
				if (!Validate::isInt(Tools::getValue('id_slide')) && !$this->slideExists(Tools::getValue('id_slide'))) {
					$errors[] = $this->l('Invalid banner ID');
				}
			}
			/* Checks title/url/image */
			$languages = Language::getLanguages(false);
			foreach ($languages as $language) {
				if (Tools::strlen(Tools::getValue('title_' . $language['id_lang'])) > 255) {
					$errors[] = $this->l('The title is too long.');
				}
				if (Tools::strlen(Tools::getValue('url_' . $language['id_lang'])) > 255) {
					$errors[] = $this->l('The URL is too long.');
				}
				if (Tools::strlen(Tools::getValue('url_' . $language['id_lang'])) > 0 && !Validate::isUrl(Tools::getValue('url_' . $language['id_lang']))) {
					$errors[] = $this->l('The URL format is not correct.');
				}
				if (Tools::getValue('image_' . $language['id_lang']) != null && !Validate::isFileName(Tools::getValue('image_' . $language['id_lang']))) {
					$errors[] = $this->l('Invalid filename.');
				}
				if (Tools::getValue('image_old_' . $language['id_lang']) != null && !Validate::isFileName(Tools::getValue('image_old_' . $language['id_lang']))) {
					$errors[] = $this->l('Invalid filename.');
				}
			}

			/* Checks title/url for default lang */
			$id_lang_default = (int)Configuration::get('PS_LANG_DEFAULT');
			if (Tools::strlen(Tools::getValue('title_'.$id_lang_default)) == 0) {
				$errors[] = $this->l('The title is not set.');
			}
			if (Tools::strlen(Tools::getValue('url_' . $id_lang_default)) == 0) {
				$errors[] = $this->l('The URL is not set.');
			}
			if (!Tools::isSubmit('has_picture') && (!isset($_FILES['image_' . $id_lang_default]) || empty($_FILES['image_' . $id_lang_default]['tmp_name']))) {
				$errors[] = $this->l('The image is not set.');
			}
			if (Tools::getValue('image_old_'.$id_lang_default) && !Validate::isFileName(Tools::getValue('image_old_'.$id_lang_default))) {
				$errors[] = $this->l('The image is not set.');
			}
		} elseif (Tools::isSubmit('delete_id_slide') && (!Validate::isInt(Tools::getValue('delete_id_slide')) || !$this->slideExists((int)Tools::getValue('delete_id_slide')))) {
			$errors[] = $this->l('Invalid banner ID');
		}

		/* Display errors if needed */
		if (count($errors)) {
			$this->_html .= $this->displayError(implode('<br />', $errors));

			return false;
		}

		/* Returns if validation is ok */

		return true;
	}

	protected function _postProcess()
	{
		$errors = array();

		/* Process Slide status */
		if (Tools::isSubmit('changeStatus') && Tools::isSubmit('id_slide')) {
			$slide = new TtSubBan((int)Tools::getValue('id_slide'));
			if ($slide->active == 0) {
				$slide->active = 1;
			} else {
				$slide->active = 0;
			}
			$res = $slide->update();
			$this->clearCache();
			$this->_html .= ($res ? $this->displayConfirmation($this->l('Configuration updated')) : $this->displayError($this->l('The configuration could not be updated.')));
		} elseif (Tools::isSubmit('submitSlide')) {
			/* Sets ID if needed */
			if (Tools::getValue('id_slide')) {
				$slide = new TtSubBan((int)Tools::getValue('id_slide'));
				if (!Validate::isLoadedObject($slide)) {
					$this->_html .= $this->displayError($this->l('Invalid banner ID'));
					return false;
				}
			} else {
				$slide = new TtSubBan();
			}
			/* Sets position */
			$slide->position = (int)Tools::getValue('position');
			/* Sets active */
			$slide->active = (int)Tools::getValue('active_slide');

			/* Sets each langue fields */
			$languages = Language::getLanguages(false);

			foreach ($languages as $language) {
				$slide->title[$language['id_lang']] = Tools::getValue('title_'.$language['id_lang']);
				$slide->url[$language['id_lang']] = Tools::getValue('url_'.$language['id_lang']);
				
				/* Uploads image and sets slide */
				$type = '';
                $imagesize = 0;

                if (
                    isset($_FILES['image_' . $language['id_lang']]) &&
                    !empty($_FILES['image_' . $language['id_lang']]['tmp_name'])
                ) {
                    $type = Tools::strtolower(Tools::substr(strrchr($_FILES['image_' . $language['id_lang']]['name'], '.'), 1));
                    $imagesize = @getimagesize($_FILES['image_' . $language['id_lang']]['tmp_name']);
                }

                if (
                    !empty($type) &&
                    !empty($imagesize) &&
                    in_array(
                        Tools::strtolower(Tools::substr(strrchr($imagesize['mime'], '/'), 1)),
                        [
                            'jpg',
                            'gif',
                            'jpeg',
                            'png',
                        ]
                    ) &&
                    in_array($type, ['jpg', 'gif', 'jpeg', 'png'])
                ) {
					$temp_name = tempnam(_PS_TMP_IMG_DIR_, 'PS');
					$salt = sha1(microtime());
					if ($error = ImageManager::validateUpload($_FILES['image_'.$language['id_lang']])) {
						$errors[] = $error;
					} elseif (!$temp_name || !move_uploaded_file($_FILES['image_'.$language['id_lang']]['tmp_name'], $temp_name)) {
						return false;
					} elseif (!ImageManager::resize($temp_name, dirname(__FILE__).'/views/img/'.$salt.'_'.$_FILES['image_'.$language['id_lang']]['name'], null, null, $type)) {
						$errors[] = $this->displayError($this->l('An error occurred during the image upload process.'));
					}
					if (isset($temp_name)) {
						@unlink($temp_name);
					}
					$slide->image[$language['id_lang']] = $salt.'_'.$_FILES['image_'.$language['id_lang']]['name'];
				} elseif (Tools::getValue('image_old_'.$language['id_lang']) != '') {
					$slide->image[$language['id_lang']] = Tools::getValue('image_old_' . $language['id_lang']);
				}
			}

			/* Processes if no errors  */
			if (!$errors) {
				/* Adds */
				if (!Tools::getValue('id_slide')) {
					if (!$slide->add()) {
						$errors[] = $this->displayError($this->l('The banner could not be added.'));
					}
				} elseif (!$slide->update()) {
					$errors[] = $this->displayError($this->l('The banner could not be updated.'));
				}
				$this->clearCache();
			}
		} elseif (Tools::isSubmit('delete_id_slide')) {
			$slide = new TtSubBan((int)Tools::getValue('delete_id_slide'));
			$res = $slide->delete();
			$this->clearCache();
			if (!$res) {
				$this->_html .= $this->displayError('Could not delete.');
			} else {
				Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true) . '&conf=1&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name);
			}
		}

		/* Display errors if needed */
		if (count($errors)) {
			$this->_html .= $this->displayError(implode('<br />', $errors));
		} elseif (Tools::isSubmit('submitSlide') && Tools::getValue('id_slide')) {
			Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true) . '&conf=4&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name);
		} elseif (Tools::isSubmit('submitSlide')) {
			Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true) . '&conf=3&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name);
		}
	}

	public function renderWidget($hookName = null, array $configuration = [])
	{
		if (!$this->isCached($this->templateFile, $this->getCacheId())) {
			$this->smarty->assign($this->getWidgetVariables($hookName, $configuration));
		}

		return $this->fetch($this->templateFile, $this->getCacheId());
	}

	public function getWidgetVariables($hookName = null, array $configuration = [])
	{
		$slides = $this->getSlides(true);
		if (is_array($slides)) {
			foreach ($slides as &$slide) {
				$slide['sizes'] = @getimagesize((dirname(__FILE__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $slide['image']));
				if (isset($slide['sizes'][3]) && $slide['sizes'][3]) {
					$slide['size'] = $slide['sizes'][3];
				}
			}
		}

		return [
			'ttsubbanner' => [
				'slides' => $slides
			],
		];
	}

	public function clearCache()
	{
		$this->_clearCache($this->templateFile);
	}

	public function hookActionShopDataDuplication($params)
	{
		Db::getInstance()->execute('
			INSERT IGNORE INTO '._DB_PREFIX_.'ttsubbanner (id_homeslider_slides, id_shop)
			SELECT id_homeslider_slides, '.(int)$params['new_id_shop'].'
			FROM '._DB_PREFIX_.'ttsubbanner
			WHERE id_shop = '.(int)$params['old_id_shop']
		);
		$this->clearCache();
	}
	
	public function headerHTML()
	{
		if (Tools::getValue('controller') != 'AdminModules' && Tools::getValue('configure') != $this->name) {
			return;
		}

		$this->context->controller->addJqueryUI('ui.sortable');
        /* Style & js for fieldset 'slides configuration' */
        $this->smarty->assign(array(
            'ttfinalnewurl' => $this->context->shop->physical_uri,
            'tttemporarynewurl' => $this->context->shop->virtual_uri,
            'ttnewname' => $this->name,
            'ttnewkey' => $this->secure_key
        ));


		return $this->display(__FILE__, 'bannersorting.tpl');
		//return $html;
	}

	public function getNextPosition()
	{
		$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
			SELECT MAX(hss.`position`) AS `next_position`
			FROM `'._DB_PREFIX_.'ttsubbanner_slides` hss, `'._DB_PREFIX_.'ttsubbanner` hs
			WHERE hss.`id_homeslider_slides` = hs.`id_homeslider_slides` AND hs.`id_shop` = '.(int)$this->context->shop->id
		);

		return (++$row['next_position']);
	}

	public function getSlides($active = null)
	{
		$this->context = Context::getContext();
		$id_shop = $this->context->shop->id;
		$id_lang = $this->context->language->id;

		$slides = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT hs.`id_homeslider_slides` as id_slide, hss.`position`, hss.`active`, hssl.`title`,
			hssl.`url`, hssl.`image`
			FROM '._DB_PREFIX_.'ttsubbanner hs
			LEFT JOIN '._DB_PREFIX_.'ttsubbanner_slides hss ON (hs.id_homeslider_slides = hss.id_homeslider_slides)
			LEFT JOIN '._DB_PREFIX_.'ttsubbanner_slides_lang hssl ON (hss.id_homeslider_slides = hssl.id_homeslider_slides)
			WHERE id_shop = '.(int)$id_shop.'
			AND hssl.id_lang = '.(int)$id_lang.
			($active ? ' AND hss.`active` = 1' : ' ').'
			ORDER BY hss.position'
		);

		foreach ($slides as &$slide) {
			$slide['image_url'] = $this->context->link->getMediaLink(_MODULE_DIR_.'ttsubbanner/views/img/'.$slide['image']);
		}

		return $slides;
	}

	public function getAllImagesBySlidesId($id_slides, $active = null, $id_shop = null)
	{
		$this->context = Context::getContext();
		$images = array();

		if (!isset($id_shop))
			$id_shop = $this->context->shop->id;

		$results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT hssl.`image`, hssl.`id_lang`
			FROM '._DB_PREFIX_.'ttsubbanner hs
			LEFT JOIN '._DB_PREFIX_.'ttsubbanner_slides hss ON (hs.id_homeslider_slides = hss.id_homeslider_slides)
			LEFT JOIN '._DB_PREFIX_.'ttsubbanner_slides_lang hssl ON (hss.id_homeslider_slides = hssl.id_homeslider_slides)
			WHERE hs.`id_homeslider_slides` = '.(int)$id_slides.' AND hs.`id_shop` = '.(int)$id_shop.
			($active ? ' AND hss.`active` = 1' : ' ')
		);

		foreach ($results as $result)
			$images[$result['id_lang']] = $result['image'];

		return $images;
	}

	public function displayStatus($id_slide, $active)
	{
		$title = ((int)$active == 0 ? $this->l('Disabled') : $this->l('Enabled'));
		$icon = ((int)$active == 0 ? 'icon-remove' : 'icon-check');
		$class = ((int)$active == 0 ? 'btn-danger' : 'btn-success');
		$html = '<a class="btn '.$class.'" href="'.AdminController::$currentIndex.
			'&configure='.$this->name.
				'&token='.Tools::getAdminTokenLite('AdminModules').
				'&changeStatus&id_slide='.(int)$id_slide.'" title="'.$title.'"><i class="'.$icon.'"></i> '.$title.'</a>';

		return $html;
	}

	public function slideExists($id_slide)
	{
		$req = 'SELECT hs.`id_homeslider_slides` as id_slide
				FROM `'._DB_PREFIX_.'ttsubbanner` hs
				WHERE hs.`id_homeslider_slides` = '.(int)$id_slide;
		$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($req);

		return ($row);
	}

	public function renderList()
	{
		$slides = $this->getSlides();
		foreach ($slides as $key => $slide) {
			$slides[$key]['status'] = $this->displayStatus($slide['id_slide'], $slide['active']);
			$associated_shop_ids = TtSubBan::getAssociatedIdsShop((int)$slide['id_slide']);
			if ($associated_shop_ids && count($associated_shop_ids) > 1) {
				$slides[$key]['is_shared'] = true;
			} else {
				$slides[$key]['is_shared'] = false;
			}
		}

		$this->context->smarty->assign(
			array(
				'link' => $this->context->link,
				'slides' => $slides,
				'image_baseurl' => $this->_path.'views/img/'
			)
		);

		return $this->display(__FILE__, 'list.tpl');
	}

	public function renderAddForm()
	{
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Banner information'),
					'icon' => 'icon-cogs'
				),
				'input' => array(
					array(
						'type' => 'file_lang',
						'label' => $this->l('Image'),
						'name' => 'image',
						'required' => true,
						'lang' => true,
						'desc' => $this->l('Recommended width: 255 pixels'),
					),
					array(
						'type' => 'text',
						'label' => $this->l('Title'),
						'name' => 'title',
						'lang' => true,
					),
					array(
						'type' => 'text',
						'label' => $this->l('Target URL'),
						'name' => 'url',
						'required' => true,
						'lang' => true,
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Enabled'),
						'name' => 'active_slide',
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('Disabled')
							)
						),
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
				)
			),
		);

		if (Tools::isSubmit('id_slide') && $this->slideExists((int)Tools::getValue('id_slide'))) {
			$slide = new TtSubBan((int)Tools::getValue('id_slide'));
			$fields_form['form']['input'][] = array('type' => 'hidden', 'name' => 'id_slide');
			$fields_form['form']['images'] = $slide->image;

			$has_picture = true;

			foreach (Language::getLanguages(false) as $lang) {
				if (!isset($slide->image[$lang['id_lang']])) {
					$has_picture &= false;
				}
			}

			if ($has_picture) {
				$fields_form['form']['input'][] = array('type' => 'hidden', 'name' => 'has_picture');
			}
		}

		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();
		$helper->module = $this;
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitSlide';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$language = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->tpl_vars = array(
			'base_url' => $this->context->shop->getBaseURL(),
			'language' => array(
				'id_lang' => $language->id,
				'iso_code' => $language->iso_code
			),
			'fields_value' => $this->getAddFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id,
			'image_baseurl' => $this->_path.'views/img/'
		);

		$helper->override_folder = '/';

		$languages = Language::getLanguages(false);

		if (count($languages) > 1) {
			return $this->getMultiLanguageInfoMsg() . $helper->generateForm(array($fields_form));
		} else {
			return $helper->generateForm(array($fields_form));
		}
	}

	public function getAddFieldsValues()
	{
		$fields = array();

		if (Tools::isSubmit('id_slide') && $this->slideExists((int)Tools::getValue('id_slide'))) {
			$slide = new TtSubBan((int)Tools::getValue('id_slide'));
			$fields['id_slide'] = (int)Tools::getValue('id_slide', $slide->id);
		} else {
			$slide = new TtSubBan();
		}

		$fields['active_slide'] = Tools::getValue('active_slide', $slide->active);
		$fields['has_picture'] = true;

		$languages = Language::getLanguages(false);

		foreach ($languages as $lang) {
			$fields['image'][$lang['id_lang']] = Tools::getValue('image_'.(int)$lang['id_lang']);
			$fields['title'][$lang['id_lang']] = Tools::getValue('title_'.(int)$lang['id_lang'], isset($slide->title[$lang['id_lang']]) ? $slide->title[$lang['id_lang']] : '');
			$fields['url'][$lang['id_lang']] = Tools::getValue('url_'.(int)$lang['id_lang'], isset($slide->url[$lang['id_lang']]) ? $slide->url[$lang['id_lang']] : '');
		}

		return $fields;
	}

	protected function getMultiLanguageInfoMsg()
	{
		return '<p class="alert alert-warning">'.
					$this->l('Since multiple languages are activated on your shop, please mind to upload your image for each one of them').
				'</p>';
	}

	protected function getWarningMultishopHtml()
	{
		if (Shop::getContext() == Shop::CONTEXT_GROUP || Shop::getContext() == Shop::CONTEXT_ALL) {
			return '<p class="alert alert-warning">' .
			$this->l('You cannot manage banners items from a "All Shops" or a "Group Shop" context, select directly the shop you want to edit') .
			'</p>';
		} else {
			return '';
		}
	}

	protected function getShopContextError($shop_contextualized_name, $mode)
	{
		if (is_array($shop_contextualized_name)) {
			$shop_contextualized_name = implode('<br/>', $shop_contextualized_name);
		}

		if ($mode == 'edit') {
			return '<p class="alert alert-danger">' .
			sprintf($this->l('You can only edit this banner from the shop(s) context: %s'), $shop_contextualized_name) .
			'</p>';
		} else {
			return '<p class="alert alert-danger">' .
			sprintf($this->l('You cannot add banners from a "All Shops" or a "Group Shop" context')) .
			'</p>';
		}
	}

	protected function getShopAssociationError($id_slide)
	{
		return '<p class="alert alert-danger">'.
						sprintf($this->l('Unable to get banner shop association information (id_slide: %d)'), (int)$id_slide).
				'</p>';
	}


	protected function getCurrentShopInfoMsg()
	{
		$shop_info = null;

		if (Shop::isFeatureActive()) {
			if (Shop::getContext() == Shop::CONTEXT_SHOP) {
				$shop_info = sprintf($this->l('The modifications will be applied to shop: %s'), $this->context->shop->name);
			} else if (Shop::getContext() == Shop::CONTEXT_GROUP) {
				$shop_info = sprintf($this->l('The modifications will be applied to this group: %s'), Shop::getContextShopGroup()->name);
			} else {
				$shop_info = $this->l('The modifications will be applied to all shops and shop groups');
			}

			return '<div class="alert alert-info">'.
						$shop_info.
					'</div>';
		} else {
			return '';
		}
	}

	protected function getSharedSlideWarning()
	{
		return '<p class="alert alert-warning">'.
					$this->l('This banner is shared with other shops! All shops associated to this banner will apply modifications made here').
				'</p>';
	}
}