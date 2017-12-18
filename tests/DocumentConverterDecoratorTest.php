<?php

class DocumentConverterDecoratorTest extends SapphireTest
{
    protected $requiredExtensions = array(
        'SiteTree' => array(
            'DocumentConverterDecorator',
        ),
    );

    public function testFieldListHasDocumentImportField()
    {
        $fields = (new SiteTree)->getCMSFields();
        $this->assertInstanceOf(
            'DocumentImportField',
            $fields->fieldByName('Root.Import')->Fields()->First()
        );
    }
}
