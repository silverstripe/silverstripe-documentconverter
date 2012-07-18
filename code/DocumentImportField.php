<?php
/**
 * Provides a document import capability through the use of an external service.
 * Includes several options fields, which are bundled together with an UploadField
 * into a CompositeField.
 */
class DocumentImportField extends CompositeField {
	/**
	 * Reference to the inner upload field (DocumentImportInnerField).
	 */
	private $innerField = null;

	/**
	 * Augments a simple CompositeField with uploader and import options.
	 *
	 * @param $children FieldSet/array Any additional children.
	 */
	public function __construct($children = null) {
		if (is_string($children)) throw new InvalidArgumentException('DocumentImportField::__construct does not accept a name as its parameter, it defaults to "ImportedFromFile" instead. Use DocumentImportField::getInnerField()->setName() if you want to change it.');
		if ($children) throw new InvalidArgumentException('DocumentImportField::__construct provides its own fields and does not accept additional children.');

		// Add JS specific to this field.
		Requirements::javascript('documentconverter/javascript/DocumentImportField.js');

		$fields = new FieldList(array(
			new HeaderField('FileWarningHeader', 'Warning: import will remove all content and subpages of this page.', 4),
			$splitHeader = new DropdownField('DocumentImportField-SplitHeader', 'Split document into pages', array(0 => 'no', 1 => 'for each heading 1', 2 => 'for each heading 2')),
			$keepSource = new CheckboxField('DocumentImportField-KeepSource', 'Keep the original document. Adds a link to it on TOC, if enabled.'),
			$chosenFolderID = new TreeDropdownField('DocumentImportField-ChosenFolderID','Choose a folder to save this file', 'Folder'),
			$includeTOC = new CheckboxField('DocumentImportField-IncludeTOC', 'Replace this page with a Table of Contents.'),
			$publishPages = new CheckboxField('DocumentImportField-PublishPages', 'Publish modified pages (not recommended unless you are sure about the conversion outcome)'),
			$this->innerField = new DocumentImportInnerField('ImportedFromFile', 'Import content from a word document'),
		));

		// Prevent the warning popup that appears when navigating away from the page.
		$splitHeader->addExtraClass('no-change-track');
		$keepSource->addExtraClass('no-change-track');
		$chosenFolderID->addExtraClass('no-change-track');
		$includeTOC->addExtraClass('no-change-track');
		$publishPages->addExtraClass('no-change-track');

		return parent::__construct($fields);
	}

	public function getInnerField() {
		return $this->innerField;
	}
}
