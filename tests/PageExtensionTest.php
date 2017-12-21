<?php

namespace SilverStripe\DocumentConverter\Tests;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\DocumentConverter\PageExtension;
use SilverStripe\DocumentConverter\SettingsField;

class PageExtensionTest extends SapphireTest
{
    protected static $required_extensions = [
        SiteTree::class => [PageExtension::class]
    ];

    public function testFieldListHasDocumentImportField()
    {
        $siteTree = new SiteTree;
        $fields = $siteTree->getCMSFields();
        $this->assertInstanceOf(
            SettingsField::class,
            $fields->fieldByName('Root.Import')->Fields()->First()
        );
    }
}
