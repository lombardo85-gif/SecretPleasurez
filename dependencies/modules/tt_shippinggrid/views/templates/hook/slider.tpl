{*
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
*  @copyright  2007-2019 PrestaShop SA
*  @version  Release: $Revision$
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{if $shipgrid.slides}
<div id="shipping-text">
  <div class="shipping-text-inner">
    <div class="shipping-outer">
      <div class="shipping-inner">
       {assign var = "myvar" value = "1"}
      {foreach from=$shipgrid.slides item=slide}


        <div class="subtitle-part subtitle-part{$myvar}">
            <div class="subbanner-part-maininnner{$myvar}">
               <div class="subicon subicon{$myvar}"><img src="{$slide.image_url}" alt="{$slide.legend|escape}" /></div>
               <div class="shipping-desc">
                  <div class="subtitile subtitile1">{$slide.title}</div>
                  <div class="desc desc1">{$slide.description nofilter}</div>
               </div>
            </div>
         </div>

        <span hidden>{$myvar++}</span>
      {/foreach}
      </div>
    </div>
  </div>
</div>
{/if}


