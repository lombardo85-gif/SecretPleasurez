{*
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
*  @copyright 2015-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
{if $ttleftbannerblock.slides}
	<div id="custom-leftbannerblock" class="custom-leftbannerblock">
			{foreach from=$ttleftbannerblock.slides item=slide name='ttleftbannerblock'}	
				{if $slide.active}
					<p class="left-banner">
						<a href="{$slide.url|escape:'html':'UTF-8'}" title="{$slide.title|escape}">
							<img src="{$slide.image_url}" alt="{$slide.title|escape}" width="auto" height="auto"/>
						</a>
					</p>
				{/if}
			{/foreach}
		
	</div>
{/if}