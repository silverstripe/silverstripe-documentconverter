jQuery.entwine('documentimport', function($) {
	$('div.documentimport').entwine({
		onfileuploaddone: function() {
			// Force the page reloading with the new content.
			$('.cms-container').entwine('.ss').loadPanel(document.location.href, null, {reload: Math.random()});
		}
	});
});

