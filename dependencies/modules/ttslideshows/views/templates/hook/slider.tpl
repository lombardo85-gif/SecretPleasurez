
{if $homeslider.slides}
<div class="homeslider-container slideshow_container">
  
	<div class="trend-slideshow">
		<div class="flexslider ma-nivoslider">
			<!-- <div class="sliderloading"><img src="loading.png" alt="" /></div>  -->
			<div id="trend-slideshow-home" class="slides">
				
				{$count=0}
				{foreach from=$homeslider.slides key=key item=slide}
					<a href="{$slide.url}" title="{$slide.title}" ><img style="display:none" src="{$slide.image_url}"  data-thumb="{$slide.image_url}"  alt="" title="#htmlcaption{$slide.id_slide}"  /> </a>
			   {/foreach}
			</div>
			{if $homeslider.show_caption != 0}
				{foreach from=$homeslider.slides key=key item=slide}
					<div id="htmlcaption{$slide.id_slide}" class="trend-slideshow-caption nivo-html-caption nivo-caption">					
							<div class="timethai" style=" 
								position:absolute;
								top:0;
								left:0;
								z-index:8;
								background-color: rgba(49, 56, 72, 0.298);
								height:5px;
								-webkit-animation: myfirst {$homeslider.speed}ms ease-in-out;
								-moz-animation: myfirst {$homeslider.speed}ms ease-in-out;
								-ms-animation: myfirst {$homeslider.speed}ms ease-in-out;
								animation: myfirst {$homeslider.speed}ms ease-in-out;
							
							">
							</div>
							{if $slide.description}
							<div class="banner7-des"><div class="container">{$slide.description nofilter}</div> </div>
							{/if}
					</div>
				 {/foreach}
			 {/if}
		</div>
	</div>
  
</div>

{/if}
 