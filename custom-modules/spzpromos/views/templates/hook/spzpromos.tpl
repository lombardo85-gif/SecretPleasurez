{*
 * Homepage offers carousel. Slides are a native scroll-snap strip, so they
 * swipe and scroll without JavaScript; views/js/spzpromos.js adds the
 * arrows, dots, autoplay and code copying.
 *}
<section class="spz-promos container" aria-roledescription="carousel" aria-label="Current offers" data-spz-promos>
  <div class="spz-promos__track" id="spz-promos-track" data-spz-track tabindex="0">
    {foreach $spz_slides as $slide}
      <article class="spz-slide spz-slide--{$slide.theme}" id="spz-promo-{$slide@iteration}"
               role="group" aria-roledescription="slide" aria-label="{$slide@iteration} of {$slide@total}">
        <div class="spz-slide__copy">
          {if $slide.eyebrow}<p class="spz-slide__eyebrow">{$slide.eyebrow}</p>{/if}
          <h2 class="spz-slide__title">{$slide.title}</h2>
          {if $slide.text}<p class="spz-slide__text">{$slide.text}</p>{/if}
          <div class="spz-slide__actions">
            <a class="btn btn-primary" href="{$slide.cta_url}">{$slide.cta_label}</a>
            {if $slide.code}
              <button type="button" class="spz-code" data-spz-code="{$slide.code}">
                <span class="spz-code__label">Code</span>
                <strong>{$slide.code}</strong>
                <span class="spz-code__hint" aria-live="polite">Copy</span>
              </button>
            {/if}
          </div>
          {if $slide.ends}<p class="spz-slide__ends">Offer ends {$slide.ends}</p>{/if}
        </div>

        {if $slide.products}
          <ul class="spz-slide__products">
            {foreach $slide.products as $product}
              <li class="spz-mini">
                <a class="spz-mini__link" href="{$product.url}">
                  <span class="spz-mini__well">
                    {if $product.cover}
                      <img src="{$product.cover.bySize.home_default.url}"
                           width="{$product.cover.bySize.home_default.width}" height="{$product.cover.bySize.home_default.height}"
                           alt="" loading="{if $slide@first}eager{else}lazy{/if}" decoding="async">
                    {/if}
                  </span>
                  {if $product.has_discount}
                    <span class="spz-mini__badge">{if $product.discount_type === 'percentage'}{$product.discount_percentage}{else}-{$product.discount_amount}{/if}</span>
                  {/if}
                  <span class="spz-mini__name">{$product.name}</span>
                  <span class="spz-mini__price">
                    {if $product.has_discount}<s><span class="spz-visually-hidden">Was </span>{$product.regular_price}</s>{/if}
                    <strong>{if $product.has_discount}<span class="spz-visually-hidden">Now </span>{/if}{$product.price}</strong>
                  </span>
                </a>
              </li>
            {/foreach}
          </ul>
        {/if}
      </article>
    {/foreach}
  </div>

  {if $spz_slides|count > 1}
    <div class="spz-promos__controls">
      <button type="button" class="spz-promos__btn" data-spz-prev aria-controls="spz-promos-track" aria-label="Previous offer">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <div class="spz-promos__dots">
        {foreach $spz_slides as $slide}
          <button type="button" class="spz-promos__dot" data-spz-dot aria-controls="spz-promo-{$slide@iteration}"
                  aria-label="Offer {$slide@iteration}: {$slide.title}"{if $slide@first} aria-current="true"{/if}>
            <span class="spz-promos__bar"></span>
          </button>
        {/foreach}
      </div>
      <button type="button" class="spz-promos__btn" data-spz-next aria-controls="spz-promos-track" aria-label="Next offer">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <button type="button" class="spz-promos__btn spz-promos__toggle" data-spz-toggle aria-controls="spz-promos-track" aria-label="Pause offers" aria-pressed="false">
        <svg class="spz-promos__icon-pause" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 6v12M15 6v12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
        <svg class="spz-promos__icon-play" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg>
      </button>
    </div>
  {/if}

  {if $spz_chips}
    <nav class="spz-promos__chips" aria-label="Shop by category">
      {foreach $spz_chips as $chip}
        <a class="spz-chip" href="{$chip.url}">{$chip.label}</a>
      {/foreach}
    </nav>
  {/if}
</section>
