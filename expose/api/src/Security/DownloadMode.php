<?php

declare(strict_types=1);

namespace App\Security;

/**
 * How the current reader may download the files of a publication.
 */
enum DownloadMode
{
    /** Downloads are disabled, or the reader cannot see the details of the publication */
    case None;
    /** The download links are sent by email */
    case Email;
    /** The download terms are accepted first, through POST /publications/{id}/download-url */
    case Terms;
    case Direct;
    /** An editor of the publication, subject to none of the download settings */
    case Editor;

    /**
     * Whether the URLs of the original files (and of the renditions) are given to the reader.
     */
    public function exposesFiles(): bool
    {
        return self::Direct === $this || self::Editor === $this;
    }
}
