<?php

namespace SilverStripe\DocumentConverter;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Page;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Assets\FileNameFilter;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Upload;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\HTMLEditor\HtmlEditorConfig;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Tidy;


/**
 * DocumentImporterField is built on top of UploadField to access a document
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
class DocumentImporterField extends UploadField {

	private static $allowed_actions = ['upload'];

	private static $importer_class = DocumentConverter::class;

	/**
	 * Process the document immediately upon upload.
	 */
	public function upload(HTTPRequest $request) {
		if($this->isDisabled() || $this->isReadonly()) return $this->httpError(403);

		// Protect against CSRF on destructive action
		$token = $this->getForm()->getSecurityToken();
		if(!$token->checkRequest($request)) return $this->httpError(400);

		$name = $this->getName();
		$tmpfile = $request->postVar($name);

		// Check if the file has been uploaded into the temporary storage.
		if (!$tmpfile) {
			$return = array('error' => _t('SilverStripe\\AssetAdmin\\Forms\\UploadField.FIELDNOTSET', 'File information not found'));
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

			$importResult = $this->importFromPOST($tmpfile, $splitHeader, $publishPages, $chosenFolderID);
			if (is_array($importResult) && isset($importResult['error'])) {
				$return['error'] = $importResult['error'];
			} else if ($includeTOC) {
				$this->writeTOC($publishPages, $keepSource ? $preservedDocument : null);
			}
		}

		$response = HTTPResponse::create(Convert::raw2json(array($return)));
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
		$upload = Upload::create();

		$file = File::create();
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
				$page = Page::create();
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
	 * @param array $tmpFile Array as received from PHP's POST upload.
	 * @param bool $splitHeader Heading level to split by.
	 * @param bool $publishPages Whether the underlying pages should be published after import.
	 * @param int $chosenFolderID ID of the working folder - here the converted file and images will be stored.
	 */
	public function importFromPOST($tmpFile, $splitHeader = false, $publishPages = false, $chosenFolderID = null) {

		$fileDescriptor = array(
			'name' => $tmpFile['name'],
			'path' => $tmpFile['tmp_name'],
			'mimeType' => $tmpFile['type']
		);

		$sourcePage = $this->form->getRecord();
		$importerClass = Config::inst()->get(__CLASS__, 'importer_class');
		$importer = Injector::inst()->create($importerClass, $fileDescriptor, $chosenFolderID);
		$content = $importer->import();

		if (is_array($content) && isset($content['error'])) {
			return $content;
		}

		// Clean up with tidy (requires tidy module)
		$tidy = new Tidy();
		$tidy->parseString($content, array('output-xhtml' => true), 'utf8');
		$tidy->cleanRepair();

		$fragment = [];
		foreach($tidy->body()->child as $child) {
			$fragment[] = $child->value;
		}

		$htmlValue = Injector::inst()->create('HTMLValue', implode("\n", $fragment));

		// Sanitise
		$santiser = Injector::inst()->create('HtmlEditorSanitiser', HtmlEditorConfig::get_active());
		$santiser->sanitise($htmlValue);

		// Load in the HTML
		$doc = $htmlValue->getDocument();
		$xpath = new DOMXPath($doc);

		// make sure any images are added as Image records with a relative link to assets
		$chosenFolder = ($this->chosenFolderID) ? DataObject::get_by_id(Folder::class, $this->chosenFolderID) : null;
		$folderName = ($chosenFolder) ? '/' . $chosenFolder->Name : '';
		$imgs = $xpath->query('//img');
		for($i = 0; $i < $imgs->length; $i++) {
			$img = $imgs->item($i);
			$originalPath = 'assets/' . $folderName . '/' . $img->getAttribute('src');
			$name = FileNameFilter::create()->filter(basename($originalPath));

			$image = Image::get()->filter(array('Name' => $name, 'ParentID' => (int) $chosenFolderID))->first();
			if(!($image && $image->exists())) {
				$image = Image::create();
				$image->ParentID = (int) $chosenFolderID;
				$image->Name = $name;
				$image->write();
			}

			// make sure it's put in place correctly so Image record knows where it is.
			// e.g. in the case of underscores being renamed to dashes.
			@rename(Director::getAbsFile($originalPath), Director::getAbsFile($image->getFilename()));

			$img->setAttribute('src', $image->getFilename());
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

		$documentImporterFieldError;

		$documentImporterFieldErrorHandler = function ($errno, $errstr, $errfile, $errline) use ( $documentImporterFieldError ) {
			$documentImporterFieldError = _t(
				'SilverStripe\\DocumentConverter\\DocumentConverter.PROCESSFAILED',
				'Could not process document, please double-check you uploaded a .doc or .docx format.',
				'Document Converter processes Word documents into HTML.'
			);

			// Do not cascade the error through other handlers
			return true;
		};

		set_error_handler($documentImporterFieldErrorHandler);

		$subtitle = null;
		$subdoc = new DOMDocument();
		$subnode = $subdoc->createElement('body');
		$node = $body->firstChild;
		$sort = 1;
		if($splitHeader == 1 || $splitHeader == 2) {
			while($node && !$documentImporterFieldError) {
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

		if($subnode->hasChildNodes() && !$documentImporterFieldError) {
			$this->writeContent($subtitle, $subdoc, $subnode, null, $publishPages);
		}

		restore_error_handler();
		if ($documentImporterFieldError) {
			return array('error' => $documentImporterFieldError);
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
