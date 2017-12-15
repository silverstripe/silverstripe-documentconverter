<?php

namespace SilverStripe\DocumentConverter;

use CURLFile;

use ZipArchive;
use SilverStripe\Assets\Folder;
use SilverStripe\ORM\DataObject;



/**
 * Utility class hiding the specifics of the document conversion process.
 */
class DocumentImportIFrameFieldImporter {

	/**
	 * Associative array of:
	 * - name: the full name of the file including the extension.
	 * - path: the path to the file on the local filesystem.
	 * - mimeType
	 */
	protected $fileDescriptor;

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

	public function __construct($fileDescriptor, $chosenFolderID = null) {
		$this->fileDescriptor = $fileDescriptor;
		$this->chosenFolderID = $chosenFolderID;
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
			CURLOPT_URL => self::get_docvert_url(),
			CURLOPT_USERPWD => sprintf('%s:%s', self::get_docvert_username(), self::get_docvert_password()),
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => array('file' => $file),
			CURLOPT_CONNECTTIMEOUT => 25,
			CURLOPT_TIMEOUT => 100,
		));

		$folderName = ($this->chosenFolderID) ? '/'.DataObject::get_by_id(Folder::class, $this->chosenFolderID)->Name : '';
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
