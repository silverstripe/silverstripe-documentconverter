<?php

class DocumentConverterDecorator extends DataExtension {
	
	function extraStatics() {
		return array(
			'has_one' => array(
				'ImportFromFile' => 'File'
			)
		);
	}

	function updateCMSFields(&$fields) {
		$fields->addFieldToTab('Root.Import', new DocumentImportField('ImportFromFile', 'Import content from a word document (CAUTION: Overwrites existing content!)') );
	}
}
