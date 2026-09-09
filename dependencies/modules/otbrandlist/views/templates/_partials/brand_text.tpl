{if $sliderstatus == 1}
		<ul id="bestsellers-carousel" class="owl-carousel product-item products-slider">
	{else}
		<ul id="bestsellers-grid" class="products-grid product-item">
{/if}
{assign var='brandCount' value=count($brands)}
  {foreach from=$brands item=brand name=brand_list}
    
      <li class="item">
        <a href="{$brand['link']}" title="{$brand['name']}">
          {$brand['name']}
        </a>
      </li>
   
  {/foreach}
</ul>
