<?php

namespace SilverStripe\DocumentConverter;

use InvalidArgumentException;
use SilverStripe\Assets\Folder;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\View\Requirements;

/**
 * Provides a document import capability through the use of an external service.
 * Includes several options fields, which are bundled together with an UploadField
 * into a CompositeField.
 */
class SettingsField extends CompositeField
{
    /**
     * Reference to the inner upload field (ImportField).
     */
    private $innerField = null;

    /**
     * Augments a simple CompositeField with uploader and import options.
     *
     * @param $children FieldSet/array Any additional children.
     */
    public function __construct($children = null)
    {
        if (is_string($children)) {
            throw new InvalidArgumentException(
                'DocumentConversionField::__construct does not accept a name as its parameter,' .
                ' it defaults to "ImportedFromFile" instead. Use DocumentConversionField::getInnerField()->setName()' .
                ' if you want to change it.'
            );
        }
        if ($children) {
            throw new InvalidArgumentException(
                'DocumentConversionField::__construct provides its own fields and does not accept additional children.'
            );
        }

        // Add JS specific to this field.
        Requirements::javascript('silverstripe/documentconverter: javascript/DocumentConversionField.js');

        $fields = FieldList::create([
            HeaderField::create(
                'FileWarningHeader',
                _t(
                    __CLASS__ . '.FileWarningHeader',
                    'Warning: import will remove all content and subpages of this page.'
                ),
                4
            ),
            $splitHeader = DropdownField::create(
                'DocumentConversionField-SplitHeader',
                _t(
                    __CLASS__ . '.SplitHeader',
                    'Split document into pages'
                ),
                [
                    0 => _t(__CLASS__ . '.No', 'no'),
                    1 => _t(__CLASS__ . '.EachH1', 'for each heading 1'),
                    2 => _t(__CLASS__ . '.EachH2', 'for each heading 2')
                ]
            ),
            $keepSource = CheckboxField::create(
                'DocumentConversionField-KeepSource',
                _t(
                    __CLASS__ . '.KeepSource',
                    'Keep the original document. Adds a link to it on TOC, if enabled.'
                )
            ),
            $chosenFolderID = TreeDropdownField::create(
                'DocumentConversionField-ChosenFolderID',
                _t(__CLASS__ . '.ChooseFolder', 'Choose a folder to save this file'),
                Folder::class
            ),
            $includeTOC = CheckboxField::create(
                'DocumentConversionField-IncludeTOC',
                _t(__CLASS__ . '.IncludeTOC', 'Replace this page with a Table of Contents.')
            ),
            $publishPages = CheckboxField::create(
                'DocumentConversionField-PublishPages',
                _t(
                    __CLASS__ . '.publishPages',
                    'Publish modified pages (not recommended unless you are sure about the conversion outcome)'
                )
            ),
            $this->innerField = ImportField::create(
                'ImportedFromFile',
                _t(__CLASS__ . '.ImportedFromFile', 'Import content from a word document')
            )
        ]);

        // Prevent the warning popup that appears when navigating away from the page.
        $splitHeader->addExtraClass('no-change-track');
        $keepSource->addExtraClass('no-change-track');
        $chosenFolderID->addExtraClass('no-change-track');
        $includeTOC->addExtraClass('no-change-track');
        $publishPages->addExtraClass('no-change-track');

        return parent::__construct($fields);
    }

    public function getInnerField()
    {
        return $this->innerField;
    }
}
