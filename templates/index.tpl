{**
 * 2007-2017 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
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
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2017 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 *}
{extends file='page.tpl'}

    {block name='page_content_container'}
      <section id="content" class="page-home">
        {block name='page_content_top'}
          {* Brand hero, replacing the theme's demo slideshow. Banner is
             brand/NEW-Banner.png, served from assets/img/spz-banner.png.
             Links go to real categories only: no promotional claims until
             real offers exist. Category ids are the largest live ones. *}
          {assign var=spz_chips value=[31=>'Vibrators', 35=>'Stimulators', 23=>'Dildos', 38=>'Anal', 36=>'Lingerie', 15=>'Bondage', 25=>'Lubricants', 40=>'Masturbators']}
          <section class="spz-hero" aria-labelledby="spz-hero-title">
            <div class="spz-hero__glow" aria-hidden="true"></div>
            <h1 id="spz-hero-title" class="spz-visually-hidden">{$shop.name|escape:'html':'UTF-8'}</h1>
            <img class="spz-hero__banner" src="{$urls.theme_assets}img/spz-banner.png" width="601" height="191"
                 alt="{$shop.name|escape:'html':'UTF-8'} - Your secret. Our pleasure." fetchpriority="high" decoding="async">
            <div class="spz-hero__actions">
              <a class="btn btn-primary" href="{$link->getCategoryLink(31)}">Shop vibrators</a>
              <a class="spz-btn-ghost" href="{$link->getCategoryLink(36)}">Browse lingerie</a>
            </div>
            <nav class="spz-hero__chips" aria-label="Shop by category">
              {foreach $spz_chips as $id_cat => $label}
                <a class="spz-chip" href="{$link->getCategoryLink($id_cat)}">{$label|escape:'html':'UTF-8'}</a>
              {/foreach}
            </nav>
          </section>
        {/block}

        {block name='page_content'}
          {block name='hook_home'}
            {$HOOK_HOME nofilter}
          {/block}
        {/block}
      </section>
    {/block}
