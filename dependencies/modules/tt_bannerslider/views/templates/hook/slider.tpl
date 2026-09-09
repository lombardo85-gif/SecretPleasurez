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



{if $banslider.slides}
<div class="subbannerbottomcms-outer container">
   <div class="subbannerbottomcms-inner">
    {assign var = "myvar" value = "1"}
      {foreach from=$banslider.slides item=slide}


      {if $myvar == 1}
        <div class="subbannerbottom-common subbanner-top">
         <div class="subbannerbottom-inner left-inner">
            <div class="subbannerbottom top-left">
              <div class="title1">{$slide.title}</div>
            </div>
            {$slide.description nofilter}
            <div class="subbannerbottom bottom-down">
              <a href="{$slide.url}" class="title3">{$slide.legend}</a>
            </div>
         </div>
         <div class="subbannerbottom-inner right-inner">
            <a href="{$slide.url}">
                <img src="{$slide.image_url}" alt="{$slide.legend|escape}" />
            </a>
         </div>
        </div>
      {/if}


      {if $myvar == 2}
        <div class="subbannerbottom-common subbanner-bottom">
         <div class="subbannerbottom-inner left-inner">
            <a href="{$slide.url}">
                <img src="{$slide.image_url}" alt="{$slide.legend|escape}" />
            </a>
         </div>
         <div class="subbannerbottom-inner right-inner">
            <div class="subbannerbottom top-left">
              <div class="title1">{$slide.title}</div>
            </div>
            {$slide.description nofilter}
            <div class="subbannerbottom bottom-down">
              <a href="{$slide.url}" class="title3">{$slide.legend}</a>
            </div>
         </div>
        </div>
      {/if}

      <span hidden>{$myvar++}</span>

      {/foreach}
  </div>
</div>
{/if}


