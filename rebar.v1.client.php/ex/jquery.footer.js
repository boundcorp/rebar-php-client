// Footer Javascript Here //

function testimonials(tid) {
	
	// Text
	$('.homepage-second-tier .left .customers-box .tt').each(function() {
		$(this).removeClass('show');
		$(this).addClass('hide');
	});

		// Set Active
		$('.homepage-second-tier .left .customers-box .tt-' + tid).removeClass('hide');
		$('.homepage-second-tier .left .customers-box .tt-' + tid).addClass('show');

	// Photos
	$('.homepage-second-tier .left .customers-box .tp').each(function() {
		$(this).removeClass('active');
		$(this).addClass('non-active');
	});

		// Set Active
		$('.homepage-second-tier .left .customers-box .tp-' + tid).removeClass('non-active');
		$('.homepage-second-tier .left .customers-box .tp-' + tid).addClass('active');

	// Name
	$('.homepage-second-tier .left .customers-box .tn').each(function() {
		$(this).removeClass('show');
		$(this).addClass('hide');
	});

		// Set Active
		$('.homepage-second-tier .left .customers-box .tn-' + tid).removeClass('hide');
		$('.homepage-second-tier .left .customers-box .tn-' + tid).addClass('show');

	// Ticker
	if ( tid == "1" ) {
		$('.homepage-second-tier .left .customers-box .ticker').css({ marginLeft : "18px" });
	} else {
		$('.homepage-second-tier .left .customers-box .ticker').css({ marginLeft : 18 + ((tid -1) * 77) + "px" });
	}
	
}

// Front Page Slider

// Can also be used with $(document).ready()
$(window).load(function() {
	$('.flexslider').flexslider({
		animation: "slide"
	});

	$('.trans-slider').flexslider({
		animation: "slide",
		controlNav: false,
		controlsContainer: '.trans-nav'
	});

	beforeandafter();
});

// Before and After Slide

function beforeandafter() {
	$('.knob').bind('mousedown', function(event){
		$('body').css('cursor', 'pointer !important');
		$('.knobtip').fadeOut(250);
		$('body').attr('unselectable','on').css('UserSelect','none').css('MozUserSelect','none');
		x1 = event.clientX;
		var x3 = parseInt($('.knob').css('left'));
		$(document).bind('mousemove', function(event1) {
			x2 = event1.clientX;
			distX = x2 - x1;
			var newX = x3 + distX;
			if(newX >= 2 && newX <= 262) {
				$('.knob').css({'left': newX, 'cursor': 'pointer'});
				$('.banda-after').css({'width': newX+7});
				$('.knobtip').css({'left': newX-62});
			}
			else if(newX < 20) {
				$('.knob').css({'left':2});
				$('.knobtip').css({'left':-62});
				$('.banda-after').css({'width': 7});
			} 
			else if(newX > 242) {
				$('.knob').css({'left':262});
				$('.knobtip').css({'left': 200});
				$('.banda-after').css({'width': 269});
			}
		});
	});
	$(document).mouseup(function() {
		$(document).unbind("mousemove");
		$('.knobtip').fadeIn(250);
		$('body').attr('unselectable','off').css('UserSelect','').css('MozUserSelect','').css('cursor', 'default');
	});

}


// Why Tabbed area

function whyTab(tid) {

	// Nav First

	$('div.why-tab-container .tab-nav .tab-single-nav').each(function() {
		$(this).removeClass('active');
	});

	// Set active

	$('.tab-' + tid).addClass('active');

	// Tabs Next
	
	$('div.why-tab-container .tabs .tab').each(function() {
		$(this).removeClass('active');
		$(this).addClass('hidden');
	});

	// Set Active

	$('.single-tab-' + tid).removeClass('hidden');
	$('.single-tab-' + tid).addClass('active');

}

// Contact Map Changes

function changeMap(country) {

// Maps

$('.contact-map-area .map').each(function() {
	$(this).removeClass('show');
	$(this).addClass('hidden');
});

// Set Active
	$('.contact-map-area .' + country).removeClass('hidden');
	$('.contact-map-area .' + country).addClass('show');

// Nav

$('.contact-map-nav .location-item').each(function() {
	$(this).removeClass('active');
});

// Set Active
	$('.contact-map-nav .' + country).addClass('active');



}