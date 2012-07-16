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
		$fields->addFieldToTab('Root.Import', new DocumentImportField('ImportFromFile', 'Import content from a word document (CAUTION: Overwrites existing content!)') );
	}
}
