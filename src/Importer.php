<?php

namespace SilverStripe\DocumentConverter;

interface Importer
{
    public function __construct($fileDescriptor, $chosenFolderID = null);

    public function import();
}
