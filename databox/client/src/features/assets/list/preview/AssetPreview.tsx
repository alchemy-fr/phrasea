'use client';

import type {Asset} from '@/types/api';
import {useDisplayPreferences} from '@/features/preferences/store';
import {FilePlayer} from '@/features/assets/player/FilePlayer';
import {AttributeList} from '@/features/attributes/AttributeList';
import {cn} from '@/lib/utils/cn';

/**
 * Player and pinned attributes of an asset, side by side as set in the
 * preview options. Laid out in the flex row of its sized container.
 */
export function AssetPreview({
    asset,
    interactive,
}: {
    asset: Asset;
    /** Player controls and scrollable attributes */
    interactive: boolean;
}) {
    const display = useDisplayPreferences();
    const {attributesRatio, displayFile, displayAttributes} =
        display.previewOptions;
    const file = asset.preview?.file ?? asset.thumbnail?.file;
    const showFile = displayFile && !!file;
    const showAttrs = displayAttributes;

    return (
        <>
            {showFile ? (
                <div
                    className="relative flex min-w-0 flex-1 items-center justify-center bg-media-bg"
                    style={{
                        flexBasis: showAttrs
                            ? `${(1 - attributesRatio) * 100}%`
                            : '100%',
                    }}
                >
                    <FilePlayer
                        file={file!}
                        autoPlay={display.playVideos}
                        controls={interactive}
                        fit="contain"
                    />
                </div>
            ) : null}
            {showAttrs ? (
                <div
                    className={cn(
                        'min-w-0 overflow-y-auto p-3',
                        !interactive && 'overflow-hidden'
                    )}
                    style={{
                        flexBasis: showFile
                            ? `${attributesRatio * 100}%`
                            : '100%',
                    }}
                >
                    {/* Room for the button in the top right corner */}
                    <h3 className="mb-2 truncate pr-6 text-sm font-semibold">
                        {asset.name}
                    </h3>
                    <AttributeList asset={asset} pinnedOnly dense />
                </div>
            ) : null}
        </>
    );
}
