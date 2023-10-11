<?php

namespace SilverStripe\DocumentConverter;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Page;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Assets\FileNameFilter;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Upload;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\HTMLEditor\HTMLEditorConfig;
use SilverStripe\Forms\HTMLEditor\HTMLEditorSanitiser;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Parsers\HTMLValue;
use Tidy;
use SilverStripe\AssetAdmin\Controller\AssetAdmin;
use SilverStripe\DocumentConverter\PHPWordImporter;
use SilverStripe\Dev\Deprecation;

/**
 * DocumentImporterField is built on top of UploadField to access a document
 * conversion capabilities. The original field is stripped down to allow only
 * uploads from the user's computer, and triggers the conversion when the upload
 * is completed.
 */
class ImportField extends UploadField
{

    private static $allowed_actions = ['upload'];

    private static $importer_class = PHPWordImporter::class;

    protected $attachEnabled = false;

    /**
     * Process the document immediately upon upload.
     */
    public function upload(HTTPRequest $request)
    {
        if ($this->isDisabled() || $this->isReadonly()) {
            return $this->httpError(403);
        }

        // Protect against CSRF on destructive action
        $token = $this->getForm()->getSecurityToken();
        if (!$token->checkRequest($request)) {
            return $this->httpError(400);
        }

        $tmpfile = $request->postVar('Upload');

        // Check if the file has been uploaded into the temporary storage.
        if (!$tmpfile) {
            $return = [
                'error' => _t(
                    'SilverStripe\\AssetAdmin\\Forms\\UploadField.FIELDNOTSET',
                    'File information not found'
                )
            ];
        } else {
            $return = [
                'name' => $tmpfile['name'],
                'size' => $tmpfile['size'],
                'type' => $tmpfile['type'],
                'error' => $tmpfile['error']
            ];
        }

        if (!$return['error']) {
            // Process the document and write the page.
            $importResult = $this->importFromPOST($tmpfile);
            if (is_array($importResult) && isset($importResult['error'])) {
                $return['error'] = $importResult['error'];
            }
        }

        if (($return['error'] ?? 1) == 0) {
            // asset-admin UploadField.js considers any error including 0 to be an error
            // so simply unset the key if there is no error
            unset($return['error']);
        }

        // generate the same result as UploadField
        // note we don't need to do this if there is an actual error because the JSON that's
        // returned is good enough to display an error message
        if (!isset($return['error'])) {
            // create a temporary File object to return to the client
            $upload = Upload::create();
            $file = File::create();
            $upload->loadIntoFile($tmpfile, $file);
            $objectData = AssetAdmin::singleton()->getObjectFromData($file);
            $return = array_merge($objectData, $return);
            $file->delete();
        }

        $response = HTTPResponse::create(json_encode([$return]));
        $response->addHeader('Content-Type', 'application/json');
        return $response;
    }

    /**
     * Preserves the source file by copying it to a specified folder.
     *
     * @param $tmpfile Temporary file data structure.
     * @param int $chosenFolderID Target folder.
     * @return File Stored file.
     *
     * @deprecated 3.2.0 Will be removed without equivalent functionality to replace it.
     */
    protected function preserveSourceDocument($tmpfile, $chosenFolderID = null)
    {
        Deprecation::notice('3.2.0', 'Will be removed without equivalent functionality to replace it.');
    }

    /**
     * Builds and writes the table of contents for the document.
     *
     * @param bool $publishPage Should the parent page be published.
     * @param File $preservedDocument Set if the link to the original document should be added.
     *
     * @deprecated 3.2.0 Will be removed without equivalent functionality to replace it.
     */
    protected function writeTOC($publishPages = false, $preservedDocument = null)
    {
        Deprecation::notice('3.2.0', 'Will be removed without equivalent functionality to replace it.');
    }

    protected function getBodyText($doc, $node)
    {
         // Build a new doc
         $htmldoc = new DOMDocument();
         // Create the html element
         $html = $htmldoc->createElement('html');
         $htmldoc->appendChild($html);
         // Append the body node
         $html->appendChild($htmldoc->importNode($node, true));

         // Get the text as html, remove the entry and exit root tags and return
         $text = $htmldoc->saveHTML();
         $text = preg_replace('/^.*<body>/', '', $text ?? '');
         $text = preg_replace('/<\/body>.*$/', '', $text ?? '');

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
    protected function writeContent($subtitle, $subdoc, $subnode, $sort = null, $publishPages = false)
    {
        $record = $this->form->getRecord();

        if ($subtitle) {
            // Write the chapter page to a subpage.
            $page = DataObject::get_one(
                'Page',
                sprintf('"Title" = \'%s\' AND "ParentID" = %d', $subtitle, $record->ID)
            );
            if (!$page) {
                $page = Page::create();
                $page->ParentID = $record->ID;
                $page->Title = $subtitle;
            }

            unset($this->unusedChildren[$page->ID]);
            file_put_contents(ASSETS_PATH . '/index-' . $sort . '.html', $this->getBodyText($subdoc, $subnode));

            if ($sort) {
                $page->Sort = $sort;
            }
            $page->Content = $this->getBodyText($subdoc, $subnode);
            $page->write();
            if ($publishPages) {
                $page->publishRecursive();
            }
        } else {
            // Write to the master page.
            $record->Content = $this->getBodyText($subdoc, $subnode);
            $record->write();

            if ($publishPages) {
                $record->publishRecursive();
            }
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
    public function importFromPOST($tmpFile, $splitHeader = false, $publishPages = false, $chosenFolderID = null)
    {
        $fileDescriptor = [
            'name' => $tmpFile['name'],
            'path' => $tmpFile['tmp_name'],
            'mimeType' => $tmpFile['type']
        ];

        $sourcePage = $this->form->getRecord();
        $importerClass = $this->config()->get('importer_class');
        /** @var Importer $importer */
        $importer = Injector::inst()->create($importerClass, $fileDescriptor, $chosenFolderID);
        $content = $importer->import();

        if (is_array($content) && isset($content['error'])) {
            return $content;
        }

        // Clean up with tidy (requires tidy module)
        $tidy = new Tidy();
        $tidy->parseString($content, ['output-xhtml' => true], 'utf8');
        $tidy->cleanRepair();

        $fragment = [];
        foreach ($tidy->body()->child as $child) {
            $fragment[] = $child->value;
        }

        $htmlValue = Injector::inst()->create(HTMLValue::class, implode("\n", $fragment));

        // Sanitise
        $santiser = Injector::inst()->create(HTMLEditorSanitiser::class, HTMLEditorConfig::get_active());
        $santiser->sanitise($htmlValue);

        // Load in the HTML
        $doc = $htmlValue->getDocument();
        $xpath = new DOMXPath($doc);

        // make sure any images are added as Image records with a relative link to assets
        $imgs = $xpath->query('//img');
        for ($i = 0; $i < $imgs->length; $i++) {
            $img = $imgs->item($i);
            $originalPath = Controller::join_links(ASSETS_DIR, $img->getAttribute('src'));
            // ignore base64 encoded images which show up when importing using PHPOffice/PHPWord Word2007
            // counter-intuitively it seems that we can simply ignore these and images
            // are still imported correctly
            if (preg_match("#data:image/.+?;base64,#", $originalPath)) {
                continue;
            }
            $name = FileNameFilter::create()->filter(basename($originalPath ?? ''));
            $image = Image::get()->filter([
                'Name' => $name,
                'ParentID' => (int) $chosenFolderID
            ])->first();
            if (!($image && $image->exists())) {
                $image = Image::create();
                $image->ParentID = (int) $chosenFolderID;
                $image->Name = $name;
                $image->write();
            }
            // make sure it's put in place correctly so Image record knows where it is.
            // e.g. in the case of underscores being renamed to dashes.
            @rename(Director::getAbsFile($originalPath) ?? '', Director::getAbsFile($image->getFilename()) ?? '');
            $img->setAttribute('src', $image->getFilename());
        }

        $remove_rules = [
            // Change any headers that contain font tags (other than font face tags) into p elements
            '//h1[.//font[not(@face)]]' => 'p',
            // Remove any font tags
            '//font'
        ];

        foreach ($remove_rules as $rule => $parenttag) {
            if (is_numeric($rule)) {
                $rule = $parenttag;
                $parenttag = null;
            }

            $nodes = [];
            foreach ($xpath->query($rule) as $node) {
                $nodes[] = $node;
            }

            foreach ($nodes as $node) {
                $parent = $node->parentNode;

                if ($parenttag) {
                    $parent = $doc->createElement($parenttag);
                    $node->nextSibling ?
                        $node->parentNode->insertBefore($parent, $node->nextSibling) :
                        $node->parentNode->appendChild($parent);
                }

                while ($node->firstChild) {
                    $parent->appendChild($node->firstChild);
                }
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

        $headingXPath = [
            'self::h1',
            'self::h2',
            'self::h3',
            'self::h4',
            'self::h5',
            'self::h6',
        ];
        // Remove a bunch of unwanted elements
        $clean = [
            // Empty paragraphs
            '//p[not(descendant-or-self::text() | descendant-or-self::img)]',
            // Empty headers
            '//*[' . implode(' | ', $headingXPath) . '][not(descendant-or-self::text() | descendant-or-self::img)]',
            // Anchors
            '//a[not(@href)]',
            // BR tags
            '//br'
        ];

        foreach ($clean as $query) {
            // First get all the nodes. Need to build array, as they'll disappear from the
            // nodelist while we're deleteing them, causing the indexing to screw up.
            $nodes = [];
            foreach ($xpath->query($query) as $node) {
                $nodes[] = $node;
            }

            // Then remove them all
            foreach ($nodes as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        // Now split the document into portions by H1
        $body = $doc->getElementsByTagName('body')->item(0);

        $this->unusedChildren = [];
        foreach ($sourcePage->Children() as $child) {
            $this->unusedChildren[$child->ID] = $child;
        }

        $documentImporterFieldError = false;

        $documentImporterFieldErrorHandler = function (
            $errno,
            $errstr,
            $errfile,
            $errline
        ) use ($documentImporterFieldError) {
            $documentImporterFieldError = _t(
                'SilverStripe\\DocumentConverter\\ServiceConnector.PROCESSFAILED',
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
        if ($splitHeader == 1 || $splitHeader == 2) {
            while ($node && !$documentImporterFieldError) {
                if ($node instanceof DOMElement && $node->tagName == 'h' . $splitHeader) {
                    if ($subnode->hasChildNodes()) {
                        $this->writeContent($subtitle, $subdoc, $subnode, $sort, $publishPages);
                        $sort++;
                    }

                    $subdoc = new DOMDocument();
                    $subnode = $subdoc->createElement('body');
                    $subtitle = trim(preg_replace('/\n|\r/', '', Convert::html2raw($node->textContent) ?? '') ?? '');
                } else {
                    $subnode->appendChild($subdoc->importNode($node, true));
                }

                $node = $node->nextSibling;
            }
        } else {
            $this->writeContent($subtitle, $subdoc, $body, null, $publishPages);
        }

        if ($subnode->hasChildNodes() && !$documentImporterFieldError) {
            $this->writeContent($subtitle, $subdoc, $subnode, null, $publishPages);
        }

        restore_error_handler();
        if ($documentImporterFieldError) {
            return ['error' => $documentImporterFieldError];
        }

        foreach ($this->unusedChildren as $child) {
            $origStage = Versioned::current_stage();

            Versioned::set_stage(Versioned::DRAFT);
            $draft = clone $child;
            $draft->delete();

            Versioned::set_stage(Versioned::LIVE);
            $published = clone $child;
            $published->delete();

            Versioned::set_stage($origStage);
        }

        $sourcePage->write();
    }
}
