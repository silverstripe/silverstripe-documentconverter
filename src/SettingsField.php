<?php

namespace SilverStripe\DocumentConverter;

use InvalidArgumentException;
use SilverStripe\Assets\Folder;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
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
        if ($children) {
            $class = self::class;
            throw new InvalidArgumentException(
                "{$class}::__construct does not accept extra parameters."
            );
        }

        // Add JS specific to this field.
        Requirements::javascript('silverstripe/documentconverter: client/dist/js/DocumentConversionField.js');

        $fields = FieldList::create([
            LiteralField::create(
                'FileWarningHeader',
                '<div class="alert alert-warning">' . _t(
                    __CLASS__ . '.FileWarningHeader',
                    'Warning: import will remove all content and subpages of this page.'
                ) . '</div>',
                4
            ),
            $splitHeader = DropdownField::create(
                'DocumentConversionSettings-SplitHeader',
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
                'DocumentConversionSettings-KeepSource',
                _t(
                    __CLASS__ . '.KeepSource',
                    'Keep the original document. Adds a link to it on TOC, if enabled.'
                )
            ),
            $chosenFolderID = TreeDropdownField::create(
                'DocumentConversionSettings-ChosenFolderID',
                _t(__CLASS__ . '.ChooseFolder', 'Choose a folder to save this file'),
                Folder::class
            ),
            $includeTOC = CheckboxField::create(
                'DocumentConversionSettings-IncludeTOC',
                _t(__CLASS__ . '.IncludeTOC', 'Replace this page with a Table of Contents.')
            ),
            $publishPages = CheckboxField::create(
                'DocumentConversionSettings-PublishPages',
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
