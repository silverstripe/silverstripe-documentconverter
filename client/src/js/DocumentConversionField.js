/* global jQuery */
jQuery.entwine('documentimport', ($) => {
  $('div.documentimport').entwine({
    /**
     * Trigger page reload after the document has been imported.
     */
    onfileuploaddone(e, data) {
      const responseText = data.jqXHR.responseText;
      const responseJSON = JSON.parse(responseText);

      const error = responseJSON[0].error;

      if (!error) {
        // Update the tree - LeftAndMain.Tree/updateNodesFromServer doesn't handle children being added or removed
        const pageID = $('#Form_EditForm_ID').val();
        $('.cms-tree').jstree('refresh', $(`#record-${pageID}`));

        // Then reload the page, suppressing change warnings. Ideally this should wait till tree refresh done.
        $('.cms-edit-form').removeClass('changed');
        $('.cms-container').entwine('.ss').loadPanel(document.location.href, null, null, true);
      }
    },

    /**
     * Inject additional parameters.
     */
    onfileuploadsubmit(e, data) {
      const form = this.closest('form');
      const field = this.closest('.documentimport');

      data.formData = $.extend(data.formData, {
        // Re-add the original fields (this call unfortunately overrides the orignal formData option).
        SecurityID: form.find(':input[name=SecurityID]').val(),
        ID: form.find(':input[name=ID]').val(),
        // Add custom option fields.
        SplitHeader: field.find('[name=DocumentImportField-SplitHeader]').val(),
        KeepSource: field.find('[name="DocumentImportField-KeepSource"]').prop('checked') ? 1 : 0,
        ChosenFolderID: field.find('[name=DocumentImportField-ChosenFolderID]').val(),
        PublishPages: field.find('[name="DocumentImportField-PublishPages"]').prop('checked') ? 1 : 0,
        IncludeTOC: field.find('[name="DocumentImportField-IncludeTOC"]').prop('checked') ? 1 : 0
      });
    }
  });
});

