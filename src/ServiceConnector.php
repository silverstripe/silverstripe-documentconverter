<?php

namespace SilverStripe\DocumentConverter;

use CURLFile;
use SilverStripe\Assets\Folder;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use ZipArchive;

/**
 * Utility class hiding the specifics of the document conversion process.
 */
class ServiceConnector {

	use Configurable;
	use Injectable;

	/**
	 * @config
	 * @var array Docvert connection username
	 */
	private static $username = null;

	/**
	 * @config
	 * @var array Docvert connection password
	 */
	private static $password = null;

	/**
	 * @config
	 * @var array Docvert service URL
	 */
	private static $url = null;

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
	 */
	protected $docvertDetails = [
		'username' => null,
		'password' => null,
		'url' => null
	];

	public function __construct($fileDescriptor, $chosenFolderID = null) {
		$this->fileDescriptor = $fileDescriptor;
		$this->chosenFolderID = $chosenFolderID;
	}

	public function setUsername($username = null)  {
		$this->docvertDetails['username'] = $username;
	}

	public function getUsername() {
		$username = $this->docvertDetails['username'];
		if ($username) {
			return $username;
		}
		$username = $this->config()->get('username');
		if ($username) {
			return $username;
		}
		$username = Environment::getEnv('DOCVERT_USERNAME');
		if ($username) {
			return $username;
		}
		return null;
	}

	public function setPassword($password = null) {
		$this->docvertDetails['password'] = $password;
	}

	public function getPassword() {
		$username = $this->docvertDetails['password'];
		if ($username) {
			return $username;
		}
		$username = $this->config()->get('password');
		if ($username) {
			return $username;
		}
		$username = Environment::getEnv('DOCVERT_PASSWORD');
		if ($username) {
			return $username;
		}
		return null;
	}

	public function setUrl($url = null) {
		$this->docvertDetails['url'] = $url;
	}

	public function getUrl() {
		$username = $this->docvertDetails['url'];
		if ($username) {
			return $username;
		}
		$username = $this->config()->get('url');
		if ($username) {
			return $username;
		}
		$username = Environment::getEnv('DOCVERT_URL');
		if ($username) {
			return $username;
		}
		return null;
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

		curl_setopt_array($ch, [
			CURLOPT_URL => $this->getUrl(),
			CURLOPT_USERPWD => sprintf('%s:%s', $this->getUsername(), $this->getPassword()),
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => ['file' => $file],
			CURLOPT_CONNECTTIMEOUT => 25,
			CURLOPT_TIMEOUT => 100,
		]);

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
			return ['error' => _t(
				__CLASS__ . '.SERVERUNREACHABLE',
				'Could not contact document conversion server. Please try again later or contact your system administrator.',
				'Document Converter process Word documents into HTML.'
			)];
		}

		// extract the converted document into assets
		// you need php zip, e.g. apt-get install php-zip
		$zip = new ZipArchive();

		if($zip->open($outzip)) {
			$zip->extractTo(ASSETS_PATH .$folderName);
			$zip->close();
		}

		// remove temporary files
		unlink($outname);
		unlink($outzip);

		if (!file_exists(ASSETS_PATH . $folderName . '/index.html')) {
			return ['error' =>  _t(
				__CLASS__ . '.PROCESSFAILED',
				'Could not process document, please double-check you uploaded a .doc or .docx format.',
				'Document Converter processes Word documents into HTML.'
			)];
		}

		$content = file_get_contents(ASSETS_PATH . $folderName . '/index.html');

		unlink(ASSETS_PATH . $folderName . '/index.html');

		return $content;
	}

}
