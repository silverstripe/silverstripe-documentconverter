<?php

class DocumentConverterDecorator extends DataExtension {
	
	function extraStatics($class = null, $extension = null) {
		return array(
			'has_one' => array(
				'ImportedFromFile' => 'File'
			)
		);
	}

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
		$fields->addFieldToTab('Root.Import', new DocumentImportField());
	}
}
