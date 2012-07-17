jQuery.entwine('documentimport', function($) {
	$('div.documentimport').entwine({
		/**
		 * Trigger page reload after the document has been imported.
		 */
		onfileuploaddone: function() {
			$('.cms-container').entwine('.ss').loadPanel(document.location.href, null, {reload: Math.random()});
		},

		/**
		 * Inject additional parameters.
		 */
		onfileuploadsubmit: function (e, data) {
			var field = this.closest('.documentimport');
			data.formData = $.extend(data.formData, {
				// Re-add the original fields (this call unfortunately overrides the orignal formData option).
				SecurityID: data.form.find(':input[name=SecurityID]').val(),
				ID: data.form.find(':input[name=ID]').val(),
				// Add custom option fields.
				SplitHeader: field.find('[name=DocumentImportField-SplitHeader]').val(),
				KeepSource: field.find('[name="DocumentImportField-KeepSource"]').prop('checked') ? 1 : 0,
				ChosenFolderID: field.find('[name=DocumentImportField-ChosenFolderID]').val(),
				PublishPages: field.find('[name="DocumentImportField-PublishPages"]').prop('checked') ? 1 : 0,
				IncludeTOC: field.find('[name="DocumentImportField-IncludeTOC"]').prop('checked') ? 1 : 0
			})
		}
	});
});

