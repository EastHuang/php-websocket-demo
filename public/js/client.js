(function($){
	$.fn.extend({
		ui_init:function(options){
			var defaults = {width:780,height:600,background:"#EBEBEB",radius:"10px"};
			var opts = $.extend(options,defaults);
			var ml = -(opts.width/2);
			var mt = -(opts.height/2);
			$(this).css({
				position:"fixed",
				width:opts.width,
				height:opts.height,
				background:opts.background,
				top:"50%",
				left:"50%",
				"margin-left":ml,
				"margin-top":mt,
				"border-radius":opts.radius,
				"box-shadow":"1px 1px 1px 1px #000"
			});
		}
	});
})(jQuery)