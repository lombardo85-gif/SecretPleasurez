{*
* 2007-2017 PrestaShop
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
*  @copyright  2007-2017 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div class="block_newsletter">
<div class="container">
    <div class="col-md-10 col-xs-12 text-title">
      <div class="newstitle-inner">
        <div class="nwsletter-maintitle">{l s='Sign up for NewsLetter' d='Shop.Templatetrend'}</div>
      </div>
      <div class="newsletter_inner">
        <form action="{$urls.pages.index}#footer" method="post">
          <div class="row">
            <div class="col-xs-12">
            <div class="input-wrapper">
                <input
                  name="email"
                  type="text"
                  value="{$value}"
                  placeholder="{l s='Your email address' d='Shop.Forms.Labels'}"
                  aria-labelledby="block-newsletter-label"
                >
              </div>
              <input
                class="btn btn-primary float-xs-right hidden-xs-down"
                name="submitNewsletter"
                type="submit"
                value="{l s='ok' d='Shop.Theme.Actions'}"
              >
              <input
                class="btn btn-primary float-xs-right hidden-sm-up"
                name="submitNewsletter"
                type="submit"
                value="{l s='OK' d='Shop.Theme.Actions'}"
              >
              <input type="hidden" name="action" value="0">
              <div class="clearfix"></div>
            </div>
            <div class="col-xs-12">
                {if $conditions}
                  <p>{$conditions}</p>
                {/if}
                {if $msg}
                  <p class="alert {if $nw_error}alert-danger{else}alert-success{/if}">
                    {$msg}
                  </p>
                {/if}
            </div>
          </div>
        </form>
      </div>
    </div>
    <div class="col-md-2 col-xs-12 news-box">
      <div class="social-inner">
          <ul class="icon-wrapper">
            <li>
              <a href="#" class="fa-facebook">
                  <i class="fa fa-facebook"></i>
              </a>
            </li>
            <li>
              <a href="#" class="fa-twitter">
                <i class="fa fa-twitter"></i>
              </a>
            </li>
            <li>
              <a href="#" class="fa-linkedin">
                <i class="fa fa-linkedin"></i>
              </a>
            </li>
             <li>
              <a href="#" class="fa-instagram">
                <i class="fa fa-instagram"></i>
              </a>
            </li>
            <li>
              <a href="#" class="fa-google-plus">
                <i class="fa fa-google-plus"></i>
              </a>
            </li>
          </ul>
      </div>
    </div>
</div>
</div>