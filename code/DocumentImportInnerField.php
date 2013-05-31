<?php
/**
 * DocumentImportInnerField is built on top of UploadField to access a document
 * conversion capabilities. The original field is stripped down to allow only
 * uploads from the user's computer, and triggers the conversion when the upload
 * is completed.
 *
 * The file upload has additional parameters injected. They are set by the user
 * through the fields provided on the DocumentImportField:
 *
 * * SplitHeader: if enabled, scans the document looking for H1 or H2 headers and
 *   puts each subsection into separate page. The first part of the document until
 *   the first header occurence is added to the current page.
 * * KeepSource: prevents the removal of the uploaded document, and stores its ID
 *   in the has_one relationship on the parent page (see the 
 *   DocumentImportField::__construct for how to configure the name of this has_one)
 * * ChosenFolderID: directory to be used for storing the original document and the
 *   image files that come along with the document.
 * * PublishPages: whether the current and the chapter pages should be published.
 * * IncludeTOC: builds a table of contents and puts it into the parent page. This
 *   could potentially replace the document content from before the first heading.
 *   Also, if the KeepSource is enabled, it will inject the document link into this
 *   page.
 *
 *  Caveat: there is some coupling between the above parameters.
 */
class DocumentImportInnerField extends UploadField {

	public static $importer_class = 'DocumentImportIFrameField_Importer';

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

		if (!$return['error']) {
			// Get options for this import.
			$splitHeader = (int)$request->postVar('SplitHeader');
			$keepSource = (bool)$request->postVar('KeepSource');
			$chosenFolderID = (int)$request->postVar('ChosenFolderID');
			$publishPages = (bool)$request->postVar('PublishPages');
			$includeTOC = (bool)$request->postVar('IncludeTOC');

			// Process the document and write the page.
			$preservedDocument = null;
			if ($keepSource) $preservedDocument = $this->preserveSourceDocument($tmpfile, $chosenFolderID);

			$importResult = $this->importFrom($tmpfile['tmp_name'], $splitHeader, $publishPages, $chosenFolderID);
			if (is_array($importResult) && isset($importResult['error'])) {
				$return['error'] = $importResult['error'];
			} else if ($includeTOC) {
				$this->writeTOC($publishPages, $keepSource ? $preservedDocument : null);
			}
		}

		$response = new SS_HTTPResponse(Convert::raw2json(array($return)));
		$response->addHeader('Content-Type', 'text/plain');
		return $response;
	}

	/**
	 * Preserves the source file by copying it to a specified folder.
	 *
	 * @param $tmpfile Temporary file data structure.
	 * @param int $chosenFolderID Target folder.
	 * @return File Stored file.
	 */
	protected function preserveSourceDocument($tmpfile, $chosenFolderID = null) {
		$upload = new Upload();

		$file = new File();
		$upload->loadIntoFile($tmpfile, $file, $chosenFolderID);

		$page = $this->form->getRecord();
		$page->ImportedFromFileID = $file->ID;
		$page->write();

		return $file;
	}

	/**
	 * Builds and writes the table of contents for the document.
	 *
	 * @param bool $publishPage Should the parent page be published.
	 * @param File $preservedDocument Set if the link to the original document should be added.
	 */
	protected function writeTOC($publishPages = false, $preservedDocument = null) {
		$page = $this->form->getRecord();
		$content = '<ul>';

		if($page) {
			if($page->Children()->Count() > 0) {
				foreach($page->Children() as $child) {
					$content .= '<li><a href="' . $child->Link() . '">' . $child->Title . '</a></li>';
				}
				$page->Content = $content . '</ul>';
			}  else {
				$doc = new DOMDocument();
				$doc->loadHTML($page->Content);
				$body = $doc->getElementsByTagName('body')->item(0);
				$node = $body->firstChild;
				$h1 = $h2 = 1;
				while($node) {
					if($node instanceof DOMElement && $node->tagName == 'h1') {
						$content .= '<li><a href="#h1.' . $h1 . '">'. trim(preg_replace('/\n|\r/', '', Convert::html2raw($node->textContent))) . '</a></li>';
						$node->setAttributeNode(new DOMAttr("id", "h1.".$h1));
						$h1++;
					} elseif($node instanceof DOMElement && $node->tagName == 'h2') {
						$content .= '<li class="menu-h2"><a href="#h2.' . $h2 . '">'. trim(preg_replace('/\n|\r/', '', Convert::html2raw($node->textContent))) . '</a></li>';
						$node->setAttributeNode(new DOMAttr("id", "h2.".$h2));
						$h2++;
					}
					$node = $node->nextSibling;
				}
				$page->Content = $content . '</ul>' . $doc->saveHTML();
			}

			// Add in the link to the original document, if provided.
			if($preservedDocument) {
				$page->Content = '<a href="' . $preservedDocument->Link() . '" title="download original document">download original document (' .
									$preservedDocument->getSize() . ')</a>' . $page->Content;
			}

			// Store the result
			$page->write();
			if($publishPages) $page->doPublish();
		} 
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

	/**
	 * Used only when writing the document that has been split by headers.
	 * Can write both to the chapter pages as well as the master page.
	 *
	 * @param string $subtitle Title of the chapter - if missing, it will write to the master page.
	 * @param $subdoc
	 * @param $subnode
	 * @param int $sort Order of the chapter page.
	 * @param $publishPages Whether to publish the resulting child/master pages.
	 */
	protected function writeContent($subtitle, $subdoc, $subnode, $sort = null, $publishPages = false) {
		$record = $this->form->getRecord();
		
		if($subtitle) {
			// Write the chapter page to a subpage.
			$page = DataObject::get_one('Page', sprintf('"Title" = \'%s\' AND "ParentID" = %d', $subtitle, $record->ID));
			if(!$page) {
				$page = new Page();
				$page->ParentID = $record->ID;
				$page->Title = $subtitle;
			}

			unset($this->unusedChildren[$page->ID]);
			file_put_contents(ASSETS_PATH . '/index-' . $sort . '.html', $this->getBodyText($subdoc, $subnode));

			if ($sort) $page->Sort = $sort;
			$page->Content = $this->getBodyText($subdoc, $subnode);
			$page->write();
			if($publishPages) $page->doPublish();
		} else {
			// Write to the master page.
			$record->Content = $this->getBodyText($subdoc, $subnode);
			$record->write();
			
			if($publishPages) $record->doPublish();
		}
		
	}

	/**
	 * Imports a document at a certain path onto the current page and writes it.
	 * CAUTION: Overwrites any existing content on the page!
	 *
	 * @param string $path Path to the document to convert.
	 * @param bool $splitHeader Heading level to split by.
	 * @param bool $publishPages Whether the underlying pages should be published after import.
	 * @param int $chosenFolderID ID of the working folder - here the converted file and images will be stored.
	 */
	public function importFrom($path, $splitHeader = false, $publishPages = false, $chosenFolderID = null) {

		$sourcePage = $this->form->getRecord();
		$importerClass = self::$importer_class;
		$importer = new $importerClass($path, $chosenFolderID);
		$content = $importer->import();

		if (is_array($content) && isset($content['error'])) {
			return $content;
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
		$folderName = ($chosenFolderID) ? DataObject::get_by_id('Folder', $chosenFolderID)->Name : '';
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

		// Strip style, class, lang attributes.
		$els = $doc->getElementsByTagName('*');
		for ($i = 0; $i < $els->length; $i++) {
			$el = $els->item($i);
			$el->removeAttribute('class');
			$el->removeAttribute('style');
			$el->removeAttribute('lang');
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

		set_error_handler('DocumentImportInnerField_error_handler');
		global $DocumentImportInnerfield_error;
		
		$subtitle = null;
		$subdoc = new DOMDocument();
		$subnode = $subdoc->createElement('body');
		$node = $body->firstChild;
		$sort = 1;
		if($splitHeader == 1 || $splitHeader == 2) {
			while($node && !$DocumentImportInnerfield_error) {
				if($node instanceof DOMElement && $node->tagName == 'h' . $splitHeader) {
					if($subnode->hasChildNodes()) {
						$this->writeContent($subtitle, $subdoc, $subnode, $sort, $publishPages);
						$sort++;
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
			$this->writeContent($subtitle, $subdoc, $body, null, $publishPages);
		}
		
		if($subnode->hasChildNodes() && !$DocumentImportInnerfield_error) {
			$this->writeContent($subtitle, $subdoc, $subnode, null, $publishPages);
		}

		restore_error_handler();
		if ($DocumentImportInnerfield_error) {
			return array('error' => $DocumentImportInnerfield_error);
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

/**
 * Utility class hiding the specifics of the document conversion process.
 */
class DocumentImportIFrameField_Importer {

	protected $path;
	
	protected $chosenFolderID;

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

	public function __construct($path, $chosenFolderID = null) {
		$this->path = $path;
		$this->chosenFolderID = $chosenFolderID;
	}

	public function import() {
		$ch = curl_init();

		curl_setopt_array($ch, array(
			CURLOPT_URL => self::get_docvert_url(),
			CURLOPT_USERPWD => sprintf('%s:%s', self::get_docvert_username(), self::get_docvert_password()),
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => array('file' => '@' . $this->path),
			CURLOPT_CONNECTTIMEOUT => 25,
			CURLOPT_TIMEOUT => 100,
		));

		$folderName = ($this->chosenFolderID) ? '/'.DataObject::get_by_id('Folder', $this->chosenFolderID)->Name : '';
		$outname = tempnam(ASSETS_PATH, 'convert');
		$outzip = $outname . '.zip';

		$out = fopen($outzip, 'w');
		curl_setopt($ch, CURLOPT_FILE, $out);
		$returnValue = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		fclose($out);
		chmod($outzip, 0777);

		if (!$returnValue || ($status != 200)) {
			return array('error' => _t(
				'DocumentConverter.SERVERUNREACHABLE',
				'Could not contact document conversion server. Please try again later or contact your system administrator.',
				'Document Converter process Word documents into HTML.'
			));
		}

		// extract the converted document into assets
		// you need php zip, i.e. port install php5-zip
		$zip = new ZipArchive();
		
		if($zip->open($outzip)) {
			$zip->extractTo(ASSETS_PATH .$folderName);
		}

		// remove temporary files
		unlink($outname);
		unlink($outzip);

		if (!file_exists(ASSETS_PATH . $folderName . '/index.html')) {
			return array('error' =>  _t(
				'DocumentConverter.PROCESSFAILED',
				'Could not process document, please double-check you uploaded a .doc or .docx format.',
				'Document Converter process Word documents into HTML.'
			));
		}

		$content = file_get_contents(ASSETS_PATH . $folderName . '/index.html');

		unlink(ASSETS_PATH . $folderName . '/index.html');

		return $content;
	}

}

static $DocumentImportInnerfield_error = '';

function DocumentImportInnerField_error_handler($errno, $errstr, $errfile, $errline) {
	global $DocumentImportInnerfield_error;
	$DocumentImportInnerfield_error = _t(
		'DocumentConverter.PROCESSFAILED',
		'Could not process document, please double-check you uploaded a .doc or .docx format.',
		'Document Converter process Word documents into HTML.'
	);

	return true;
}
