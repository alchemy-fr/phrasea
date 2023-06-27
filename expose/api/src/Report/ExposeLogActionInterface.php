<?php

declare(strict_types=1);

namespace App\Report;

interface ExposeLogActionInterface
{
    public const ASSET_VIEW = 'asset_view';
    public const ASSET_DOWNLOAD = 'asset_download';
    public const ASSET_DOWNLOAD_REQUEST = 'asset_download_request';
    public const PUBLICATION_ARCHIVE_DOWNLOAD_REQUEST = 'pub_archive_download_request';
    public const PUBLICATION_ARCHIVE_DOWNLOAD = 'pub_archive_download';
    public const SUBDEF_DOWNLOAD = 'subdef_download';
    public const PUBLICATION_VIEW = 'publication_view';
}
