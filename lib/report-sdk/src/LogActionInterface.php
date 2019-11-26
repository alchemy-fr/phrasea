<?php

declare(strict_types=1);

namespace Alchemy\ReportSDK;

interface LogActionInterface
{
    const ACTION_ASSET_VIEW = 'asset_view';
    const ACTION_ASSET_DOWNLOAD = 'asset_download';
    const ACTION_ASSET_PREVIEW = 'asset_preview';
}
