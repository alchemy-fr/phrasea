<?php

declare(strict_types=1);

namespace Alchemy\ReportSDK;

interface LogActionInterface
{
    const ASSET_VIEW = 'asset_view';
    const ASSET_DOWNLOAD = 'asset_download';
    const SUBDEF_DOWNLOAD = 'subdef_download';
    const ASSET_PREVIEW = 'asset_preview';
    const RESET_PASSWORD = 'reset_password';
}
