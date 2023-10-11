<?php

namespace SilverStripe\DocumentConverter;

use SilverStripe\Assets\File;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

class PageExtension extends DataExtension
{

    private static $has_one = [
        'ImportedFromFile' => File::class
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->findOrMakeTab(
            'Root.Import',
            _t(__CLASS__ . '.ImportTab', 'Import')
        );
        $fields->addFieldToTab('Root.Import', SettingsField::create());
    }
}
