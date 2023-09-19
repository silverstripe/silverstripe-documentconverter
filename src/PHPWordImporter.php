<?php

namespace SilverStripe\DocumentConverter;

use GraphQL\Exception\InvalidArgument;
use SilverStripe\ORM\DataObject;

class PHPWordImporter implements Importer
{
    private array $fileDescriptor;
    private ?int $chosenFolderID;

    /**
     * @param array $fileDescriptor
     * @param int|null $chosenFolderID
     */
    public function __construct($fileDescriptor, $chosenFolderID = null)
    {
        if (!is_array($fileDescriptor)) {
            throw new InvalidArgument('fileDescriptor must be an array');
        }
        if (!is_int($chosenFolderID) && !is_null($chosenFolderID)) {
            throw new InvalidArgument('chosenFolderID must be an int or null');
        }
        $this->fileDescriptor = $fileDescriptor;
        $this->chosenFolderID = $chosenFolderID;
    }

    /**
     * @return string
     */
    public function import()
    {
        // read word doc
        $source = $this->fileDescriptor['path'];
        $ext = pathinfo($this->fileDescriptor['name'], PATHINFO_EXTENSION);
        $readerName = 'Word2007';
        if ($ext === 'doc') {
            // Word 1997
            $readerName = 'MsDoc';
        }
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($source, $readerName);
        // write it out as HTML
        $chosenFolder = ($this->chosenFolderID) ? DataObject::get_by_id(Folder::class, $this->chosenFolderID) : null;
        $folderName = ($chosenFolder) ? '/' . $chosenFolder->Name : '';
        $filepath = tempnam(ASSETS_PATH . $folderName, 'converted');
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        $objWriter->save($filepath);
        $content = file_get_contents($filepath);
        unlink($filepath);
        return $content;
    }
}
