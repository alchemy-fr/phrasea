<?php

declare(strict_types=1);

namespace App\Report;

interface ExposeLogActionInterface
{
    public const string ASSET_VIEW = 'asset_view';
    public const string ASSET_DOWNLOAD = 'asset_download';
    public const string ASSET_DOWNLOAD_REQUEST = 'asset_download_request';
    public const string PUBLICATION_ARCHIVE_DOWNLOAD_REQUEST = 'pub_archive_download_request';
    public const string PUBLICATION_ARCHIVE_DOWNLOAD = 'pub_archive_download';
    public const string SUBDEF_DOWNLOAD = 'subdef_download';
    public const string PUBLICATION_VIEW = 'publication_view';
}
