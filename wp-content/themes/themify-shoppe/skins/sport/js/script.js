(function($){
	'use strict';
	window.addEventListener('load', function(){
		$( ".form-submit" ).wrapInner( "<span class='form-submit-wrapper'></span>" );
		$( ".shop_table.cart .actions input[type=submit], .shop_table.cart .actions .input-text" ).wrap( "<span class='form-submit-wrapper'></span>" );
	}, {once:true, passive:true});

})(jQuery);