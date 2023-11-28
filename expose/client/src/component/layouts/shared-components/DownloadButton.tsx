import React, {MouseEventHandler} from 'react'
import {Translation} from 'react-i18next'


type Props = {
    downloadUrl: string;
    onDownload: (downloadUrl: string, e: React.MouseEvent<HTMLAnchorElement>) => void;
};

export default function DownloadButton({
    downloadUrl,
    onDownload,
}: Props) {

    if (!downloadUrl) {
        return ''
    }
    const downloadHandler: MouseEventHandler<HTMLAnchorElement> = (e) => {
        onDownload(downloadUrl, e);
    };

    return <Translation>
        {(t) => (
            <a
                className={'btn btn-secondary'}
                href={downloadUrl}
                type={'button'}
                title={t('download')}
                onClick={downloadHandler}
            >
                {t('download')}
            </a>
        )}
    </Translation>
}
