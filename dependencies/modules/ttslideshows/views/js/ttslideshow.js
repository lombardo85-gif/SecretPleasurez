$(document).ready(function() {
		$(document).off('mouseenter').on('mouseenter', '.trend-slideshow', function(e){
		$('.trend-slideshow .timethai').addClass('trend_hover');
		});

		 $(document).off('mouseleave').on('mouseleave', '.trend-slideshow', function(e){
		   $('.trend-slideshow .timethai').removeClass('trend_hover');
		 });
		if(TTSLIDESHOW_NAV==1){
			var slide_navigation = true;
		}else{
			var slide_navigation = false;
		};

		if(TTSLIDESHOW_EFFECT==0){
			var slide_effect = 'random';
		}else if(TTSLIDESHOW_EFFECT==1){
			var slide_effect = 'random';
		}else if(TTSLIDESHOW_EFFECT==2){
			var slide_effect = 'sliceDown';
		}else if(TTSLIDESHOW_EFFECT==3){
			var slide_effect = 'sliceDownLeft';
		}else if(TTSLIDESHOW_EFFECT==4){
			var slide_effect = 'sliceUp';
		}else if(TTSLIDESHOW_EFFECT==5){
			var slide_effect = 'sliceUpLeft';
		}else if(TTSLIDESHOW_EFFECT==6){
			var slide_effect = 'sliceUpDown';
		}else if(TTSLIDESHOW_EFFECT==7){
			var slide_effect = 'sliceUpDownLeft';
		}else if(TTSLIDESHOW_EFFECT==8){
			var slide_effect = 'fold';
		}else if(TTSLIDESHOW_EFFECT==9){
			var slide_effect = 'fade';
		}else if(TTSLIDESHOW_EFFECT==10){
			var slide_effect = 'slideInLeft';
		}else if(TTSLIDESHOW_EFFECT==11){
			var slide_effect = 'slideInRight';
		}else if(TTSLIDESHOW_EFFECT==12){
			var slide_effect = 'boxRandom';
		}else if(TTSLIDESHOW_EFFECT==13){
			var slide_effect = 'boxRain';
		}else if(TTSLIDESHOW_EFFECT==14){
			var slide_effect = 'boxRainReverse';
		}else if(TTSLIDESHOW_EFFECT==15){
			var slide_effect = 'boxRainGrow';
		}else if(TTSLIDESHOW_EFFECT==16){
			var slide_effect = 'boxRainGrowReverse';
		}else {
			var slide_effect = 'fade';
		};


		if(TTSLIDESHOW_PAGI==1){
			var slide_pagination = true;
		}else{
			var slide_pagination = false;
		};
        $('#trend-slideshow-home').nivoSlider({
			effect: slide_effect,
			slices: 15,
			boxCols: 8,
			boxRows: 4,
			animSpeed: 600,
			pauseTime: TTSLIDESHOW_SPEED,
			startSlide: 0,
			directionNav: slide_navigation,
			controlNav: slide_pagination,
			controlNavThumbs: false,
			pauseOnHover: true,
			manualAdvance: false,
			prevText: '<div class="prev-arrow"></div>',
			nextText: '<div class="next-arrow"></div>',    
                        beforeChange: function(){ 
                            $('.bannerSlideshow1').removeClass("trend_in"); 
                            $('.bannerSlideshow2').removeClass("trend_in"); 
                            $('.bannerSlideshow3').removeClass("trend_in"); 
                        }, 
                        afterChange: function(){ 
                             $('.bannerSlideshow1').addClass("trend_in"); 
                            $('.bannerSlideshow2').addClass("trend_in"); 
                            $('.bannerSlideshow3').addClass("trend_in"); 
                        }
 		});
});


$(document).ready(function() {
	//Function to animate slider captions 
	function doAnimations( elems ) {
		//Cache the animationend event in a variable
		var animEndEv = 'webkitAnimationEnd animationend';
		
		elems.each(function () {
			var $this = $(this),
				$animationType = $this.data('animation');
			$this.addClass($animationType).one(animEndEv, function () {
				$this.removeClass($animationType);
			});
		});
	}
	//Variables on page load 
	var $myCarousel = $('.ma-nivoslider'),
	$firstAnimatingElems = $myCarousel.find('.nivo-caption').find("[data-animation ^= 'animated']");
	//Animate captions in first slide on page load 
	doAnimations($firstAnimatingElems);

});
