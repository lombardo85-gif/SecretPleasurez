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
<section id="featured-products">
  <div class="featured-products container">
    <div class="titles">
      <div class="title_inner">
        <h1 class="main-title">{l s='Featured Products' mod='otfeaturedproducts'}</h1>
      </div>
    </div>

      <div class="homepage-products products">
        {if $sliderstatus == 1}
        <ul id="featuredproducts-carousel" class="owl-carousel product-item grid products-slider">
        {else}
          <ul id="featuredproducts-grid" class="products-grid grid product-item">
          {/if} 
            {foreach from=$products item="product"}
              <li class="item">
                {include file="catalog/_partials/miniatures/product-slider.tpl" product=$product}
              </li>
            {/foreach}
         </ul>
     </div>
  </div>
</section>

