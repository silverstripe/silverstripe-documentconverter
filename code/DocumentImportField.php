<?php
class DocumentImportField extends UploadField {

	public static $importer_class = 'DocumentImportIFrameField_Importer';

	/**
	 * Add javascript for handling the uploads.
	 */
	public function Field($properties = array()) {
		Requirements::javascript('documentconverter/javascript/DocumentImportField.js');
		return parent::Field($properties);
	}

	/**
	 * Process the document immediately upon upload.
	 */
	public function upload(SS_HTTPRequest $request) {
		if($this->isDisabled() || $this->isReadonly()) return $this->httpError(403);

		// Protect against CSRF on destructive action
		$token = $this->getForm()->getSecurityToken();
		if(!$token->checkRequest($request)) return $this->httpError(400);

		$name = $this->getName();
		$tmpfile = $request->postVar($name);
		
		// Check if the file has been uploaded into the temporary storage.
		if (!$tmpfile) {
			$return = array('error' => _t('UploadField.FIELDNOTSET', 'File information not found'));
		} else {
			$return = array(
				'name' => $tmpfile['name'],
				'size' => $tmpfile['size'],
				'type' => $tmpfile['type'],
				'error' => $tmpfile['error']
			);
		}

		// Invoke the conversion.
		if (!$return['error']) {
			$this->importFrom($tmpfile['tmp_name'], 0);
		}
		
		$response = new SS_HTTPResponse(Convert::raw2json(array($return)));
		$response->addHeader('Content-Type', 'text/plain');
		return $response;
	}

	protected function getBodyText($doc, $node) {
		// Build a new doc
		$htmldoc = new DOMDocument(); 
		// Create the html element
		$html = $htmldoc->createElement('html'); 
		$htmldoc->appendChild($html);
		// Append the body node
		$html->appendChild($htmldoc->importNode($node, true));

		// Get the text as html, remove the entry and exit root tags and return
		$text = $htmldoc->saveHTML();
		$text = preg_replace('/^.*<body>/', '', $text);
		$text = preg_replace('/<\/body>.*$/', '', $text);
		
		return $text;
	}

	protected function writeContent($subtitle, $subdoc, $subnode, $sort, $split = false) {
		$record = $this->form->getRecord();
		
		if($subtitle) {
			$page = DataObject::get_one('Page', sprintf('"Title" = \'%s\' AND "ParentID" = %d', $subtitle, $record->ID));
			if(!$page) $page = new Page(array('ParentID' => $record->ID, 'Title' => $subtitle));

			unset($this->unusedChildren[$page->ID]);
			file_put_contents(ASSETS_PATH . '/index-' . ($sort + 1) . '.html', $this->getBodyText($subdoc, $subnode));

			$page->Sort = (++$sort);
			$page->Content = $this->getBodyText($subdoc, $subnode);
			$page->write();
			if($this->PublishChildren) $page->doPublish();
		} else {
			if($split) {
				$record->Content = $this->getBodyText($subdoc, $subnode);
				$record->write();
			}
			
			if($this->PublishChildren) $record->doPublish();
		}
		
	}

	/**
	 * Imports a document at a certain path onto the current page and writes it.
	 * CAUTION: Overwrites any existing content on the page!
	 *
	 * @param string Path to the document to convert.
	 * @param int targetFolderID ID of the folder to upload the file to.
	 * @param int splitHeader Heading level to split by
	 */
	public function importFrom($path, $splitHeader = 1, $targetFolderID = null) {

		$sourcePage = $this->form->getRecord();
		$importerClass = self::$importer_class;
		$importer = new $importerClass($path, $targetFolderID);
		$content = $importer->import();

		// delete the File record, as it was just temporary to store a zip file of the import
		$fileID = $sourcePage->{$this->name . 'ID'};
		
		if($fileID && !$this->addLinkToFile) {
			$sourcePage->{$this->name}()->delete();
		}

		// you need Tidy, i.e. port install php5-tidy
		$tidy = new Tidy();
		$tidy->parseString($content, array('output-xhtml' => true), 'utf8');
		$tidy->cleanRepair();
		
		$doc = new DOMDocument();
		$doc->strictErrorChecking = false;
		libxml_use_internal_errors(true);
		$doc->loadHTML('' . $tidy);

		$xpath = new DOMXPath($doc);

		// Fix img links to be relative to assets
		$folderName = ($targetFolderID) ? DataObject::get_by_id('Folder', $targetFolderID)->Name : '';
		$imgs = $xpath->query('//img');
		for($i = 0; $i < $imgs->length; $i++) {
			$img = $imgs->item($i);
			$img->setAttribute('src', 'assets/'. $folderName . '/' . $img->getAttribute('src'));
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
		if($splitHeader == 1 || $splitHeader == 2) {
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
		} else {
			$this->writeContent($subtitle, $subdoc, $body, $sort, true);
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
	
	protected $targetFolderID;

	protected static $docvert_username;

	protected static $docvert_password;

	protected static $docvert_url;

	public static function set_docvert_username($username = null)  {
		self::$docvert_username = $username;
	}

	public static function get_docvert_username() {
		return self::$docvert_username;
	} 

	public static function set_docvert_password($password = null) {
		self::$docvert_password = $password;
	}

	public static function get_docvert_password() {
		return self::$docvert_password;
	}

	public static function set_docvert_url($url = null) {
		self::$docvert_url = $url;
	}

	public static function get_docvert_url() {
		return self::$docvert_url;
	}

	public function __construct($path, $targetFolderID) {
		$this->path = $path;
		$this->targetFolderID = $targetFolderID;
	}

	public function import() {
		$ch = curl_init();

		curl_setopt_array($ch, array(
			CURLOPT_URL => self::get_docvert_url(),
			CURLOPT_USERPWD => sprintf('%s:%s', self::get_docvert_username(), self::get_docvert_password()),
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => array('file' => '@' . $this->path),
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 20,
		));

		$folderName = ($this->targetFolderID) ? '/'.DataObject::get_by_id('Folder', $folderID)->Name : '';
		$outname = tempnam(ASSETS_PATH, 'convert');
		$outzip = $outname . '.zip';

		$out = fopen($outzip, 'w');
		curl_setopt($ch, CURLOPT_FILE, $out);
		curl_exec($ch);
		curl_close($ch);
		fclose($out);
		chmod($outzip, 0777);

		// extract the converted document into assets
		// you need php zip, i.e. port install php5-zip
		$zip = new ZipArchive();
		
		if($zip->open($outzip)) {
			$zip->extractTo(ASSETS_PATH .$folderName);
		}

		// remove temporary files
		unlink($outname);
		unlink($outzip);

		$content = file_get_contents(ASSETS_PATH . $folderName . '/index.html');

		unlink(ASSETS_PATH . $folderName . '/index.html');

		return $content;
	}

}
