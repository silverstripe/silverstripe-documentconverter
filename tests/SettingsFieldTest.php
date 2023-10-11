<?php

namespace SilverStripe\DocumentConverter\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\DocumentConverter\SettingsField;
use SilverStripe\DocumentConverter\ImportField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\View\Requirements;

class SettingsFieldTest extends SapphireTest
{
    public function testConstructorThrowsExceptionWhenGivenString()
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingsField('exception time!');
    }

    public function testConstructorThrowsExceptionWhenGivenChildren()
    {
        $this->expectException(\InvalidArgumentException::class);
        new SettingsField(['i', 'don\'t', 'like', 'kids']);
    }

    public function testFieldListGeneration()
    {
        $importField = new SettingsField();

        $fields = $importField->getChildren();
        $this->assertInstanceOf(FieldList::class, $fields);

        // We don't need to check that all of the fields are there, but just check a couple
        $this->assertInstanceOf(LiteralField::class, $fields->fieldByName('FileWarningHeader'));
        $innerField = $fields->fieldByName('ImportedFromFile');
        $this->assertInstanceOf(ImportField::class, $innerField);

        // Check the getter works
        $this->assertSame($innerField, $importField->getInnerField());
    }
}
