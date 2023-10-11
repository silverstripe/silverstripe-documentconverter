<?php

namespace SilverStripe\DocumentConverter;

use InvalidArgumentException;
use PhpOffice\PhpWord\IOFactory;

class PHPWordImporter implements Importer
{
    private array $fileDescriptor;

    public function __construct($fileDescriptor, $chosenFolderID = null)
    {
        if (!is_array($fileDescriptor)) {
            throw new InvalidArgumentException('fileDescriptor must be an array');
        }
        $this->fileDescriptor = $fileDescriptor;
    }

    public function import()
    {
        // read word doc
        $source = $this->fileDescriptor['path'];
        $ext = strtolower(pathinfo($this->fileDescriptor['name'], PATHINFO_EXTENSION));
        // .docx
        $readerName = 'Word2007';
        // note: "MsDoc" reader for .doc files does not work in php 8.1, though it does in php 7.4
        $phpWord = IOFactory::load($source, $readerName);
        // write it out as HTML
        $filepath = tempnam(ASSETS_PATH, 'converted');
        $objWriter = IOFactory::createWriter($phpWord, 'HTML');
        $objWriter->save($filepath);
        $content = file_get_contents($filepath);
        unlink($filepath);
        return $content;
    }
}
