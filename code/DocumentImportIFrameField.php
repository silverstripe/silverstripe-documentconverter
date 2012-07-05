<?php
class DocumentImportIFrameField extends FileIFrameField {

	public static $importer_class = 'DocumentImportIFrameField_Importer';

	protected $unusedChildren = array();

	public function save($data, $form) {

		$splitHeader = isset($data['SplitHeader']) ? (int) $data['SplitHeader'] : 1;
		$this->importFrom($_FILES['Upload']['tmp_name'], $splitHeader);

		// Respond by making the tree expand and the page reload
		$ID = $this->form->getRecord()->ID;
		$title = $this->form->getRecord()->TreeTitle();

		if(!SapphireTest::is_running_test()) {
			echo "<script type='text/javascript'>
				window.parent.tabstrip_showTab.call(window.parent.$('tab-Root_Content_set'));
				window.parent.tabstrip_showTab.call(window.parent.$('tab-Root_Content_set_Main'));
				window.parent.$('sitetree').setNodeTitle($ID, '$title');
				window.parent.$('sitetree').getTreeNodeByIdx($ID).ajaxExpansion();
				window.parent.$('Form_EditForm').getPageFromServer($ID);
				</script>";
			die();
		}
	}

	public function EditFileForm() {
		$filefield = new FileField('Upload', '');
		// var_dump($this); die;
		$form = new Form (
			$this,
			'EditFileForm',
			new FieldSet(
				new HeaderField('FileSelectHeader', 'Select the word document to import'),
				new NumericField('SplitHeader', 'Split on header level', 1, 1),
				$filefield,
				new HeaderField('FileWarningHeader', 'Warning: import will remove all content and subpages of this page', 4)
			),
			new FieldSet(
				new FormAction('save', 'Import content from doc')
			)
		);

		$form->disableSecurityToken();
		return $form;
	}

	protected function getBodyText($doc, $node) {
		// Build a new doc
		$htmldoc = new DOMDocument(); 
		// Create the html element
		$html = $htmldoc->createElement('html'); $htmldoc->appendChild($html);
		// Append the body node
		$html->appendChild($htmldoc->importNode($node, true));

		// Get the text as html, remove the entry and exit root tags and return
		$text = $htmldoc->saveHTML();
		$text = preg_replace('/^.*<body>/', '', $text);
		$text = preg_replace('/<\/body>.*$/', '', $text);
		
		return $text;
	}

	protected function writeContent($subtitle, $subdoc, $subnode, $sort) {
		$record = $this->form->getRecord();

		if($subtitle) {
			$page = DataObject::get_one('Page', sprintf('"Title" = \'%s\' AND "ParentID" = %d', $subtitle, $record->ID));
			if(!$page) $page = new Page(array('ParentID' => $record->ID, 'Title' => $subtitle));

			unset($this->unusedChildren[$page->ID]);

			file_put_contents(ASSETS_PATH . '/index-' . ($sort + 1) . '.html', $this->getBodyText($subdoc, $subnode));

			$page->Sort = (++$sort);
			$page->Content = $this->getBodyText($subdoc, $subnode);
			$page->write();
		} else {
			$record->Content = $this->getBodyText($subdoc, $subnode);
			$record->write();
		}
	}

	/**
	 * Imports a document at a certain path onto the current page and writes it.
	 * CAUTION: Overwrites any existing content on the page!
	 */
	public function importFrom($path, $splitHeader = 1) {
		$sourcePage = $this->form->getRecord();
		$importerClass = self::$importer_class;
		$importer = new $importerClass($path);
		$content = $importer->import();
		// print_r($content); die;
		// delete the File record, as it was just temporary to store a zip file of the import
		$fileID = $sourcePage->{$this->name . 'ID'};
		if($fileID) {
			$sourcePage->{$this->name}()->delete();
		}

		$tidy = new Tidy();
		$tidy->parseString($content, array('output-xhtml' => true), 'utf8');
		$tidy->cleanRepair();

		$doc = new DOMDocument();
		$doc->strictErrorChecking = false;
		$doc->loadHTML('' . $tidy);

		$xpath = new DOMXPath($doc);

		// Fix img links to be relative to assets
		$imgs = $xpath->query('//img');
		for($i = 0; $i < $imgs->length; $i++) {
			$img = $imgs->item($i);
			$img->setAttribute('src', 'assets/'.$img->getAttribute('src'));
		}

		$remove_rules = array(
			'//h1[.//font[not(@face)]]' => 'p', // Change any headers that contain font tags (other than font face tags) into p elements
			'//font' // Remove any font tags
		);

		foreach($remove_rules as $rule => $parenttag) {
			if(is_numeric($rule)) {
				$rule = $parenttag;
				$parenttag = null;
			}

			$nodes = array();
			foreach($xpath->query($rule) as $node) $nodes[] = $node;

			foreach($nodes as $node) {
				$parent = $node->parentNode;

				if($parenttag) {
					$parent = $doc->createElement($parenttag);
					$node->nextSibling ? $node->parentNode->insertBefore($parent, $node->nextSibling) : $node->parentNode->appendChild($parent);
				}

				while($node->firstChild) $parent->appendChild($node->firstChild);
				$node->parentNode->removeChild($node);
			}
		}

		// Strip styles, classes
		$els = $doc->getElementsByTagName('*');
		for ($i = 0; $i < $els->length; $i++) {
			$el = $els->item($i);
			$el->removeAttribute('class');
			$el->removeAttribute('style');
		}

		$els = $doc->getElementsByTagName('*');

		// Remove a bunch of unwanted elements
		$clean = array(
			'//p[not(descendant-or-self::text() | descendant-or-self::img)]', // Empty paragraphs
			'//*[self::h1 | self::h2 | self::h3 | self::h4 | self::h5 | self::h6][not(descendant-or-self::text() | descendant-or-self::img)]', // Empty headers
			'//a[not(@href)]', // Anchors
			'//br' // BR tags
		);

		foreach($clean as $query) {
			// First get all the nodes. Need to build array, as they'll disappear from the nodelist while we're deleteing them, causing the indexing
			// to screw up.
			$nodes = array();
			foreach($xpath->query($query) as $node) $nodes[] = $node;

			// Then remove them all
			foreach ($nodes as $node) { if ($node->parentNode) $node->parentNode->removeChild($node); }
		}

		// Now split the document into portions by H1

		$body = $doc->getElementsByTagName('body')->item(0);

		$this->unusedChildren = array();
		foreach($sourcePage->Children() as $child) {
			$this->unusedChildren[$child->ID] = $child;
		}

		$subtitle = null;
		$subdoc = new DOMDocument();
		$subnode = $subdoc->createElement('body');
		$node = $body->firstChild;
		$sort = 0;

		while($node) {
			if($node instanceof DOMElement && $node->tagName == 'h' . $splitHeader) {
				if($subnode->hasChildNodes()) {
					$this->writeContent($subtitle, $subdoc, $subnode, $sort);
				}

				$subdoc = new DOMDocument();
				$subnode = $subdoc->createElement('body');
				$subtitle = trim(preg_replace('/\n|\r/', '', Convert::html2raw($node->textContent)));
			} else {
				$subnode->appendChild($subdoc->importNode($node, true));
			}

			$node = $node->nextSibling;
		}
		
		if($subnode->hasChildNodes()) {
			$this->writeContent($subtitle, $subdoc, $subnode, $sort);
		}

		foreach($this->unusedChildren as $child) {
			$origStage = Versioned::current_stage();

			Versioned::reading_stage('Stage');
			$clone = clone $child;
			$clone->delete();

			Versioned::reading_stage('Live');
			$clone = clone $child;
			$clone->delete();

			Versioned::reading_stage($origStage);
		}

		$sourcePage->write();
	}

}
class DocumentImportIFrameField_Importer {

	protected $path;

	public function __construct($path) {
		$this->path = $path;
	}

	public function import() {
		$ch = curl_init();

		curl_setopt_array($ch, array(
			CURLOPT_URL => 'http://docvert.silverstripe.com/',
			CURLOPT_PORT => 8888,
			CURLOPT_USERPWD => sprintf('%s:%s', DOCVERT_USERNAME, DOCVERT_PASSWORD),
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => array('file' => '@' . $this->path),
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 20,
		));

		$outname = tempnam(ASSETS_PATH, 'convert');
		$outzip = $outname . '.zip';

		$out = fopen($outzip, 'w');
		curl_setopt($ch, CURLOPT_FILE, $out);
		curl_exec($ch);
		curl_close($ch);
		fclose($out);
		// var_dump($ch); die;
		chmod($outzip, 0777);
		// extract the converted document into assets
		$zip = new ZipArchive();
		// var_dump($zip->open($outzip)); die;
		if($zip->open($outzip)) {
			$zip->extractTo(ASSETS_PATH);
		}

		// remove temporary files
		unlink($outname);
		unlink($outzip);

		$content = file_get_contents(ASSETS_PATH . '/index.html');

		unlink(ASSETS_PATH . '/index.html');

		return $content;
	}

}
