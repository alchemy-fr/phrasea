<?php

declare(strict_types=1);

namespace App\Report;

interface ExposeLogActionInterface
{
    const ASSET_VIEW = 'asset_view';
    const ASSET_DOWNLOAD = 'asset_download';
    const ASSET_DOWNLOAD_REQUEST = 'asset_download_request';
    const SUBDEF_DOWNLOAD = 'subdef_download';
    const ASSET_PREVIEW = 'asset_preview';
    const PUBLICATION_VIEW = 'publication_view';
}
