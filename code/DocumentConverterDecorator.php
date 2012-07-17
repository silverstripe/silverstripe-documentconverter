<?php

class DocumentConverterDecorator extends DataExtension {
	
	function extraStatics($class = null, $extension = null) {
		return array(
			'has_one' => array(
				'ImportFromFile' => 'File'
			)
		);
	}

	function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab('Root.Main', 
			ToggleCompositeField::create('Import', 'Import', array(
				new DocumentImportField('ImportFromFile', 'Import content from a word document (CAUTION: Overwrites existing content!)')
			))->setHeadingLevel(4)
		);
	}
}
