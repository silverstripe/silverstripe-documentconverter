!function(){"use strict";var e={311:function(e){e.exports=jQuery}},o={};function n(t){var r=o[t];if(void 0!==r)return r.exports;var i=o[t]={exports:{}};return e[t](i,i.exports,n),i.exports}n(311).entwine("documentimport",(e=>{e("div.documentimport").entwine({onfileuploaddone(o,n){const t=n.jqXHR.responseText;if(!JSON.parse(t)[0].error){const o=e("#Form_EditForm_ID").val();e(".cms-tree").jstree("refresh",e(`#record-${o}`)),e(".cms-edit-form").removeClass("changed"),e(".cms-container").entwine(".ss").loadPanel(document.location.href,null,null,!0)}},onfileuploadsubmit(o,n){const t=this.closest("form"),r=this.closest(".documentimport");n.formData=e.extend(n.formData,{SecurityID:t.find(":input[name=SecurityID]").val(),ID:t.find(":input[name=ID]").val(),SplitHeader:r.find("[name=DocumentImportField-SplitHeader]").val(),KeepSource:r.find('[name="DocumentImportField-KeepSource"]').prop("checked")?1:0,ChosenFolderID:r.find("[name=DocumentImportField-ChosenFolderID]").val(),PublishPages:r.find('[name="DocumentImportField-PublishPages"]').prop("checked")?1:0,IncludeTOC:r.find('[name="DocumentImportField-IncludeTOC"]').prop("checked")?1:0})}})}))}();