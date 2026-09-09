{*
* 2007-2016 PrestaShop
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
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<section id="leftsidebar" class="leftsidebar">
  <h1 class="title">{l s='New products' mod='otleftnewproducts'}</h1>
  <div class="sidebar-products products clearfix">
		<ul id="newproducts-grid" class="products-grid product-item">
	    {foreach from=$products item="product"}
	      <li class="item">
	      {include file="catalog/_partials/miniatures/sidebar-product.tpl" product=$product}
	      </li>
	    {/foreach}
    </ul>
  </div>
  <div class="clearfix">
    <a href="{$allNewProductsLink}" class="btn btn-primary allproducts">{l s='All Newproducts' mod='otleftnewproducts'}</a>
  </div>
</section>

