/*
 * 	Easy Zoom 1.0 - jQuery plugin
 *	written by Alen Grakalic	
 *	http://cssglobe.com/post/9711/jquery-plugin-easy-image-zoom
 *
 *	Copyright (c) 2011 Alen Grakalic (http://cssglobe.com)
 *	Dual licensed under the MIT (MIT-LICENSE.txt)
 *	and GPL (GPL-LICENSE.txt) licenses.
 *
 *	Built for jQuery library
 *	http://jquery.com
 *
 */
 
 /*
 
 Required markup sample
 
 <a href="large.jpg"><img src="small.jpg" alt=""></a>
 
 */
 
(function($) {
		  
	$.fn.easyZoom = function(options){

		var defaults = {	
			id: 'easy_zoom',
			parent: 'body',
			append: true,
			preload: 'Loading...',
			error: 'There has been a problem with loading the image.'
		}; 
		
		var obj;
		var img = new Image();
		var loaded = false;
		var found = true;
		var timeout;
		var w1,w2,h1,h2,rw,rh;
		var over = false;
		
		var options = $.extend(defaults, options);  
		
		this.each(function(){ 
				
			obj = this;
			// works only for anchors
			var tagName = this.tagName.toLowerCase();
			if(tagName == 'a'){			   
				
				var href = $(this).attr('href');
				img.src = href + '?' + (new Date()).getTime() + ' =' + (new Date()).getTime();
				$(img).error(function(){ found = false; })												
				img.onload = function(){ 									
					loaded = true;	
					img.onload=function(){};
				};	
				
				$(this)
					.css('cursor','crosshair')
					.click(function(e){ e.preventDefault(); })
					.mouseover(function(e){ start(e); })
					.mouseout(function(){ hide(); })
					.mousemove(function(e){ move(e); })			
			};
			
		});
		
		function start(e){
			hide();			
			var zoom = $('<div id="'+ options.id +'">'+ options.preload +'</div>');
			if(options.append) { zoom.appendTo(options.parent) } else { zoom.prependTo(options.parent) };
			if(!found){
				error();
			} else {
				if(loaded){
					show(e);
				} else {
					loop(e);
				};				
			};			
		};
		
		function loop(e){
			if(loaded){
				show(e);
				clearTimeout(timeout);
			} else {
				timeout = setTimeout(function(){loop(e)},200);
			};
		};
		
		function show(e){
			over = true;
			$(img).css({'position':'absolute','top':'0','left':'0'});
			$('#'+ options.id).html('').append(img);			
			w1 = $('img', obj).width();
			h1 = $('img', obj).height();
			w2 = $('#'+ options.id).width();
			h2 = $('#'+ options.id).height();
			w3 = $(img).width();
			h3 = $(img).height();	
			w4 = $(img).width() - w2;
			h4 = $(img).height() - h2;	
			rw = w4/w1;
			rh = h4/h1;
			move(e);
		};
		
		function hide(){
			over = false;
			$('#'+ options.id).remove();
		};
		
		function error(){
			$('#'+ options.id).html(options.error);
		};
		
		function move(e){
			if(over){
				// target image movement
				var p = $('img',obj).offset();
				var pl = e.pageX - p.left;
				var pt = e.pageY - p.top;	
				var xl = pl*rw;
				var xt = pt*rh;
				xl = (xl>w4) ? w4 : xl;
				xt = (xt>h4) ? h4 : xt;

                if (w4 < 0) {
                    var t = -w4/2;
                    if (-xl > t) {
                        xl = -t;
                    }
                }
				$('#'+ options.id + ' img').css({'left':xl*(-1),'top':xt*(-1)});
			};
		};
	
	};

})(jQuery);
