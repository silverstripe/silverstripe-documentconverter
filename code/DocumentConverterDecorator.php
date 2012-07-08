<?php

class DocumentConverterDecorator extends DataObjectDecorator {
	
	function extraStatics() {

		return array(
			'has_one' => array(
				'ImportFromFile' => 'File'
			)
		);
	}

	function updateCMSFields(&$fields) {
		$fields->addFieldToTab('Root.Content.Import', new DocumentImportIFrameField('ImportFromFile', 'Import content from a word document (CAUTION: Overwrites existing content!)') );
	}
}