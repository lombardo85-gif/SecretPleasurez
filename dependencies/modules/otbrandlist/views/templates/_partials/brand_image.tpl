{if $sliderstatus == 1}
		<ul id="brand-carousel" class="owl-carousel product-item products-slider">
	{else}
		<ul id="brand-grid" class="products-grid product-item">
	{/if}
  {foreach from=$brands item=brand name=brand_list}
    {if $smarty.foreach.brand_list.iteration <= $text_list_nb}
     <li class="item">
        <a href="{$brand['link']}" title="{$brand['name']}">
         
		   <img src="{$brand['imageurl']}" title="{$brand['name']}" alt="" /> 
        </a>
      </li>
    {/if}
  {/foreach}
</ul>
