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
    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorThrowsExceptionWhenGivenString()
    {
        new SettingsField('exception time!');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorThrowsExceptionWhenGivenChildren()
    {
        new SettingsField(['i', 'don\'t', 'like', 'kids']);
    }

    public function testFieldAddsJavascriptRequirements()
    {
        // Start with a clean slate (no global state interference)
        Requirements::backend()->clear();

        new SettingsField();
        $javascript = Requirements::backend()->getJavascript();
        $this->assertNotEmpty($javascript);
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

        // Check the fields have been given has the change tracker disabled
        $settingsFields = [
            'SplitHeader' => DropdownField::class,
            'KeepSource' => CheckboxField::class,
            'ChosenFolderID' => TreeDropdownField::class,
            'IncludeTOC' => CheckboxField::class,
            'PublishPages' => CheckboxField::class
        ];
        foreach ($settingsFields as $fieldName => $className) {
            $field = $fields->fieldByName(
                'DocumentConversionSettings-' . $fieldName
            );
            $this->assertInstanceOf($className, $field);
            $this->assertContains('no-change-track', $field->extraClass());
        }
    }
}
