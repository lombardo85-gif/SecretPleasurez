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
{*
 * Brand directory, grouped by first letter with an A-Z index. Hundreds of
 * brands have no logo, so the list is text-first (see miniatures/brand.tpl).
 * Brands arrive sorted by name; anything not starting with A-Z is filed
 * under "0-9".
 *}
{extends file=$layout}

{block name='content'}
  <section id="main" class="spz-brands-page">

    {block name='brand_header'}
      <h1 class="spz-brands-page__title">{l s='Brands' d='Shop.Theme.Catalog'}</h1>
    {/block}

    {block name='brand_miniature'}
      {assign var=spz_letters value=[]}
      {foreach from=$brands item=brand}
        {assign var=spz_letter value=$brand.name|truncate:1:'':true|upper}
        {if ($spz_letter|regex_replace:'/^[A-Z]$/':'') !== ''}{assign var=spz_letter value='0-9'}{/if}
        {$spz_letters[$spz_letter] = true}
      {/foreach}

      {if $spz_letters|count > 1}
        <nav class="spz-brands-page__index" aria-label="{l s='Brands' d='Shop.Theme.Catalog'}: A-Z">
          {foreach from=$spz_letters key=letter item=unused}
            <a class="spz-chip" href="#brands-{$letter}">{$letter}</a>
          {/foreach}
        </nav>
      {/if}

      {assign var=spz_open value=''}
      {foreach from=$brands item=brand}
        {assign var=spz_letter value=$brand.name|truncate:1:'':true|upper}
        {if ($spz_letter|regex_replace:'/^[A-Z]$/':'') !== ''}{assign var=spz_letter value='0-9'}{/if}
        {if $spz_letter !== $spz_open}
          {if $spz_open !== ''}</ul></section>{/if}
          <section class="spz-brand-group" id="brands-{$spz_letter}" aria-labelledby="brands-{$spz_letter}-title">
            <h2 class="spz-brand-group__letter" id="brands-{$spz_letter}-title">{$spz_letter}</h2>
            <ul class="spz-brand-grid">
          {assign var=spz_open value=$spz_letter}
        {/if}
        {include file='catalog/_partials/miniatures/brand.tpl' brand=$brand}
      {/foreach}
      {if $spz_open !== ''}</ul></section>{/if}
    {/block}

  </section>

{/block}
