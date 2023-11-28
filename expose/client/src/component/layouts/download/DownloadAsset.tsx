import Description from '../shared-components/Description'
import { Trans } from 'react-i18next'
import { getThumbPlaceholder } from '../shared-components/placeholders'
import {Asset} from "../../../types.ts";
import React from "react";

type Props = {
    asset: Asset;
    onDownload: (downloadUrl: string, e: React.MouseEvent<any>) => void;
};

export default function DownloadAsset({
    asset: {
        thumbUrl,
        originalName,
        mimeType,
        description,
        subDefinitions,
        downloadUrl,
    },
    onDownload,
}: Props) {
    return <>
        <div className="media">
            <img
                src={thumbUrl || getThumbPlaceholder(mimeType)}
                alt={originalName}
            />
            <div className="media-body">
                <h5 className="mt-0">
                    {originalName} - {mimeType}
                </h5>
                <Description descriptionHtml={description} />
                <div className={'download-btns'}>
                    <a
                        onClick={(e) => onDownload(downloadUrl, e)}
                        href={downloadUrl || '#'}
                        className={'btn btn-primary'}
                    >
                        <Trans i18nKey={'download_original'}>
                            Download original
                        </Trans>
                    </a>
                    {subDefinitions.map((d) => {
                        const name = d.name

                        return (
                            <a
                                key={d.id}
                                onClick={(e) =>
                                    onDownload(d.downloadUrl, e)
                                }
                                href={d.downloadUrl || '#'}
                                className={'btn btn-secondary'}
                            >
                                <Trans i18nKey={'download_custom'}>
                                    Download {{ name }}
                                </Trans>
                            </a>
                        )
                    })}
                </div>
            </div>
        </div>
    </>
}
