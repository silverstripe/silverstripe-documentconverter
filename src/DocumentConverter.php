<?php

namespace SilverStripe\DocumentConverter;

use CURLFile;
use SilverStripe\Assets\Folder;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataObject;
use ZipArchive;

/**
 * Utility class hiding the specifics of the document conversion process.
 */
class DocumentConverter {

	use Configurable;

	/**
	 * @var array Docvert connection details
	 * @config
	 */
	private static $docvert_details = [
		'username' => '',
		'password' => '',
		'url' => ''
	];

	/**
	 * Associative array of:
	 * - name: the full name of the file including the extension.
	 * - path: the path to the file on the local filesystem.
	 * - mimeType
	 */
	protected $fileDescriptor;

	/**
	 * @var int
	 * ID of a SilverStripe\Assets\Folder
	 */
	protected $chosenFolderID;

	/**
	 * @var array instance specific connection details
	 * initially filled with the config settings
	 */
	protected $docvertDetails = [
		'username' => '',
		'password' => '',
		'url' => ''
	];

	public function __construct($fileDescriptor, $chosenFolderID = null) {
		$this->fileDescriptor = $fileDescriptor;
		$this->chosenFolderID = $chosenFolderID;
		array_merge($this->docvertDetails, (array)$this->config()->get('docvert_details'));
	}

	public function setDocvertUsername($username = null)  {
		$this->docvertDetails['username'] = $username;
	}

	public function getDocvertUsername() {
		return $this->docvertDetails['username'];
	}

	public function setDocvertPassword($password = null) {
		$this->docvertDetails['password'] = $password;
	}

	public function getDocvertPassword() {
		return $this->docvertDetails['password'];
	}

	public function setDocvertUrl($url = null) {
		$this->docvertDetails['url'] = $url;
	}

	public function getDocvertUrl() {
		return $this->docvertDetails['url'];
	}

	public function import() {
		$ch = curl_init();

		// PHP 5.5+ introduced CURLFile which makes the '@/path/to/file' syntax deprecated.
		if(class_exists('CURLFile')) {
			$file = new CURLFile(
				$this->fileDescriptor['path'],
				$this->fileDescriptor['mimeType'],
				$this->fileDescriptor['name']
			);
		} else {
			$file = '@' . $this->fileDescriptor['path'];
		}

		curl_setopt_array($ch, array(
			CURLOPT_URL => $this->getDocvertUrl(),
			CURLOPT_USERPWD => sprintf('%s:%s', $this->getDocvertUsername(), $this->getDocvertPassword()),
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => array('file' => $file),
			CURLOPT_CONNECTTIMEOUT => 25,
			CURLOPT_TIMEOUT => 100,
		));

		$chosenFolder = ($this->chosenFolderID) ? DataObject::get_by_id(Folder::class, $this->chosenFolderID) : null;
		$folderName = ($chosenFolder) ? '/' . $chosenFolder->Name : '';
		$outname = tempnam(ASSETS_PATH, 'convert');
		$outzip = $outname . '.zip';

		$out = fopen($outzip, 'w');
		curl_setopt($ch, CURLOPT_FILE, $out);
		$returnValue = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		fclose($out);
		chmod($outzip, 0666);

		if (!$returnValue || ($status != 200)) {
			return array('error' => _t(
				__CLASS__ . '.SERVERUNREACHABLE',
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
				__CLASS__ . '.PROCESSFAILED',
				'Could not process document, please double-check you uploaded a .doc or .docx format.',
				'Document Converter processes Word documents into HTML.'
			));
		}

		$content = file_get_contents(ASSETS_PATH . $folderName . '/index.html');

		unlink(ASSETS_PATH . $folderName . '/index.html');

		return $content;
	}

}
