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


{if $testislider.slides}
<div class="testimonial_outer">
  <div class="testimonial-inner">
   <div class="testimonial-outer container">
     <h1 class="main-title title">What Our Clients Say</h1>
      <div id="testimonial-slider" class="owl-carousel testimonial-carousel">
        {foreach from=$testislider.slides item=slide}
          <div class="item">
             <div class="testminial-data">

                  <div class="test_desc">
                     <div class="testimonial-desc">
                        {$slide.description nofilter}
                      </div>
                      <div class="testmonial-author">{$slide.title}</div>
                      
                  </div>


              </div>              
          </div>
        {/foreach}
      </div>
  </div>
  </div>
</div>
{/if}
