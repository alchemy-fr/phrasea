<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Transformer\Document\Libreoffice;

/**
 * The only formats handed to LibreOffice, each with the import filter forced on it:
 * LibreOffice never guesses the type of a file by itself.
 */
enum DocumentFormat: string
{
    case Doc = 'doc';
    case Docx = 'docx';
    case Odt = 'odt';
    case Xls = 'xls';
    case Xlsx = 'xlsx';
    case Ods = 'ods';
    case Ppt = 'ppt';
    case Pptx = 'pptx';
    case Odp = 'odp';
    case Rtf = 'rtf';
    case Text = 'txt';

    public function getExtension(): string
    {
        return $this->value;
    }

    public function getImportFilter(): string
    {
        return match ($this) {
            self::Doc => 'MS Word 97',
            self::Docx => 'MS Word 2007 XML',
            self::Odt => 'writer8',
            self::Xls => 'MS Excel 97',
            self::Xlsx => 'Calc MS Excel 2007 XML',
            self::Ods => 'calc8',
            self::Ppt => 'MS PowerPoint 97',
            self::Pptx => 'Impress MS PowerPoint 2007 XML',
            self::Odp => 'impress8',
            self::Rtf => 'Rich Text Format',
            self::Text => 'Text (encoded):UTF8',
        };
    }
}
