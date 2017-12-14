<?php

class DocumentConverterDecorator extends DataExtension {

	public static $has_one = array(
		'ImportedFromFile' => 'File'
	);

	function updateCMSFields(FieldList $fields) {
		/*
		// Currently the ToggleCompositeField plays badly with TreeDropdownField formatting.
		// Could be switched back in the future, if this is fixed.
		$fields->addFieldToTab('Root.Main', 
			ToggleCompositeField::create('Import', 'Import', array(
				new DocumentImportField()
			))->setHeadingLevel(4)
		);
		 */
		$fields->findOrMakeTab(
			'Root.Import',
			_t('DocumentConverterDecorator.ImportTab', 'Import')
		);
		$fields->addFieldToTab('Root.Import', new DocumentImportField());
	}
}
