$.fn.ajaxedit = function() {

	$(this).blur(function(){
		alert('out');
	});

	$(this).change(function(){
	});
};

$('.adv-ajaxedit').ready(function () {
	$('.adv-ajaxedit').ajaxedit();
});
