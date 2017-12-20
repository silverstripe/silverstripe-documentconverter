<?php

namespace SilverStripe\DocumentConverter\Tests;

use SilverStripe\Dev\SapphireTest;

class DocumentConverterDecoratorTest extends SapphireTest
{
    protected $requiredExtensions = [
        'SiteTree' => ['DocumentConverterDecorator']
    ];

    public function testFieldListHasDocumentImportField()
    {
        $fields = (new SiteTree)->getCMSFields();
        $this->assertInstanceOf(
            'DocumentImportField',
            $fields->fieldByName('Root.Import')->Fields()->First()
        );
    }
}
