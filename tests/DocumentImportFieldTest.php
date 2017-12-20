<?php

namespace SilverStripe\DocumentConverter\Tests;

use SilverStripe\Dev\SapphireTest;

class DocumentImportFieldTest extends SapphireTest
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorThrowsExceptionWhenGivenString()
    {
        new DocumentImportField('exception time!');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorThrowsExceptionWhenGivenChildren()
    {
        new DocumentImportField(['i', 'don\'t', 'like', 'kids']);
    }

    public function testFieldAddsJavascriptRequirements()
    {
        // Start with a clean slate (no global state interference)
        Requirements::backend()->clear();

        new DocumentImportField();
        $javascript = Requirements::backend()->get_javascript();
        $this->assertNotEmpty($javascript);
    }

    public function testFieldListGeneration()
    {
        $importField = new DocumentImportField();

        $fields = $importField->getChildren();
        $this->assertInstanceOf('FieldList', $fields);

        // We don't need to check that all of the fields are there, but just check a couple
        $this->assertInstanceOf('HeaderField', $fields->fieldByName('FileWarningHeader'));
        $innerField = $fields->fieldByName('ImportedFromFile');
        $this->assertInstanceOf('DocumentImportInnerField', $innerField);

        // Check the getter works
        $this->assertSame($innerField, $importField->getInnerField());

        // Check the fields have been given has the change tracker disabled
        $splitHeader = $fields->fieldByName('DocumentImportField-SplitHeader');
        $this->assertInstanceOf('DropdownField', $splitHeader);
        $this->assertContains('no-change-track', $splitHeader->extraClass());
    }
}
