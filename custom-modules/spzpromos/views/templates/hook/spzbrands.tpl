{*
 * Shop by brand: the brands with the most live products, as text links.
 * Counts are read live, so they only ever describe what is on sale now.
 *}
<section class="spz-brands container" aria-labelledby="spz-brands-title">
  <div class="spz-brands__head">
    <h2 class="spz-brands__title" id="spz-brands-title">Shop by brand</h2>
    <a class="spz-brands__all" href="{$spz_brands_url}">All brands</a>
  </div>
  <ul class="spz-brands__list">
    {foreach $spz_brands as $brand}
      <li>
        <a class="spz-chip spz-brands__chip" href="{$brand.url}">
          {$brand.name}
          <span class="spz-brands__count">{$brand.products}<span class="spz-visually-hidden"> products</span></span>
        </a>
      </li>
    {/foreach}
  </ul>
</section>
