<?php
class DocumentImportIFrameField extends FileIFrameField {

	public static $importer_class = 'DocumentImportIFrameField_Importer';

	protected $unusedChildren = array();

	protected $addLinkToFile = false;

	protected $PublishChildren = false;

	protected $ContentTable = false;

	static $folder_id = null;

	public function save($data, $form) {
		if(isset($data['PublishChildren'])) $this->PublishChildren = true;
		if(isset($data['ContentTable'])) $this->ContentTable = true;

		if(isset($data['KeepSource'])) {
			$file = new File();
			$file->Name = $_FILES['Upload']['name'];
			if(isset($data['ChosenFolder'])) {
				$folder = DataObject::get_by_id('Folder',(int)$data['ChosenFolder']);
				if($folder) {
					copy($_FILES['Upload']['tmp_name'], ASSETS_PATH . '/' . $folder->Name . '/' . str_replace(' ','-',$_FILES['Upload']['name']));
					$file->ParentID = (int)$data['ChosenFolder'];
					self::$folder_id = $folder->ID;
				} else {
					copy($_FILES['Upload']['tmp_name'], ASSETS_PATH . '/' . str_replace(' ','-',$_FILES['Upload']['name']));
				}
				
			} else {
				copy($_FILES['Upload']['tmp_name'], ASSETS_PATH . '/' . str_replace(' ','-',$_FILES['Upload']['name']));
			}
			$file->write();
			$this->addLinkToFile = true;
			$page = $this->form->getRecord();
			$page->ImportFromFileID = $file->ID;
			$page->write();
		} 
		
		$splitHeader = isset($data['SplitHeader']) ? (int) $data['SplitHeader'] : 1;
		$this->importFrom($_FILES['Upload']['tmp_name'], $splitHeader);

		// Respond by making the tree expand and the page reload
		$ID = $this->form->getRecord()->ID;
		$title = $this->form->getRecord()->TreeTitle();

		// create table of content
		if($this->ContentTable) {
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
				$page->write();		
			} 

		}

		if($this->addLinkToFile) {
			$page->Content = '<a href="' . $file->Link() . '" title="download original document">download original document (' . $file->getSize() . ')</a>' . $page->Content;	
			$page->write();
		} 
		
		if($this->PublishChildren) $page->doPublish();

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
		
		$form = new Form (
			$this,
			'EditFileForm',
			new FieldSet(
				new HeaderField('FileSelectHeader', 'Select the word document to import'),
				new HeaderField('FileWarningHeader', 'Warning: import will remove all content and subpages of this page', 4),
				new DropdownField('SplitHeader', 'Split document into pages', array(0 => 'no', 1 => 'for each heading 1', 2 => 'for each heading 2')),
				$filefield,
				new CheckboxField('KeepSource', 'Keep document and add a link to it on this page'),
				new TreeDropdownField('ChosenFolder','Choose a folder to save this file', 'Folder'),
				new CheckboxField('PublishChildren', 'Publish new pages generated from DOC file (not recommended)'),
				new CheckboxField('ContentTable', 'Create a Table of Content')

			),
			new FieldSet(
				new FormAction('save', 'Import content from doc')
			)
		);

		$form->disableSecurityToken();
		return $form;
	}

	public function Field() {
		parent::Field();
		if($this->form->getRecord() && $this->form->getRecord()->exists()) {
			$record = $this->form->getRecord();
			if(Object::has_extension('SiteTree', 'Translatable') && $record->Locale){
				$iframe = "iframe?locale=".$record->Locale;
			}else{
				$iframe = "iframe";
			}
			
			return $this->createTag (
				'iframe',
				array (
					'name'  => $this->Name() . '_iframe',
					'src'   => Controller::join_links($this->Link(), $iframe),
					'style' => 'height: 300px; width: 100%; border: none;'
				)
			) . $this->createTag (
				'input',
				array (
					'type'  => 'hidden',
					'id'    => $this->ID(),
					'name'  => $this->Name() . 'ID',
					'value' => $this->attrValue()
				)
			);
		}
		
		$this->setValue(sprintf(_t (
			'FileIFrameField.ATTACHONCESAVED', '%ss can be attached once you have saved the record for the first time.'
		), $this->FileTypeName()));
		
		return FormField::field();
	}

	public function iframe() {
		// clear the requirements added by any parent controllers
		Requirements::clear();
		Requirements::add_i18n_javascript('sapphire/javascript/lang');
		Requirements::javascript(THIRDPARTY_DIR . '/prototype/prototype.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript('sapphire/javascript/FileIFrameField.js');
		Requirements::javascript('documentconverter/javascript/documentImportIFrameField.js');
		Requirements::css('cms/css/typography.css');
		Requirements::css('sapphire/css/FileIFrameField.css');
		Requirements::css('documentconverter/css/DocumentImportIFrameField.css');
		
		return $this->renderWith('FileIFrameField');
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
	 */
	public function importFrom($path, $splitHeader = 1) {

		$sourcePage = $this->form->getRecord();
		$importerClass = self::$importer_class;
		$importer = new $importerClass($path);
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
		$folderName = (self::$folder_id) ? DataObject::get_by_id('Folder', self::$folder_id)->Name : '';
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

	protected static $docvert_username;

	protected static $docvert_password;

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

	public function __construct($path) {
		$this->path = $path;
	}

	public function import() {
		$ch = curl_init();

		curl_setopt_array($ch, array(
			CURLOPT_URL => 'http://docvert.silverstripe.com/',
			CURLOPT_PORT => 8888,
			CURLOPT_USERPWD => sprintf('%s:%s', self::get_docvert_username(), self::get_docvert_password()),
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => array('file' => '@' . $this->path),
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 20,
		));

		$folderID = DocumentImportIFrameField::$folder_id;

		$folderName = ($folderID) ? '/'.DataObject::get_by_id('Folder', $folderID)->Name : '';
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
