import {File} from '../../../types';
import {FileTypeEnum, getFileTypeFromMIMEType} from '../../../lib/file';
import AssetFileIcon from './AssetFileIcon';
import VideoPlayer from './Players/VideoPlayer';
import {Dimensions, FileWithUrl} from './Players';
import PDFPlayer from './Players/PDFPlayer';
import {useTranslation} from 'react-i18next';

type Props = {
    file: File;
    controls?: boolean | undefined;
    title: string | undefined;
    onLoad?: () => void;
    noInteraction?: boolean;
    autoPlayable: boolean;
    dimensions?: Dimensions;
};

export default function FilePlayer({
    file,
    title,
    onLoad,
    controls,
    noInteraction,
    autoPlayable,
    dimensions,
}: Props) {
    const {t} = useTranslation();
    const mainType = getFileTypeFromMIMEType(file.type);

    if (!file.url) {
        return <AssetFileIcon file={file} />;
    }

    switch (mainType) {
        case FileTypeEnum.Image:
            const isSvg = file.type === 'image/svg+xml';

            return (
                <img
                    style={{
                        maxWidth: '100%',
                        maxHeight: '100%',
                        display: 'block',
                        ...(isSvg ? {width: '100%'} : {}),
                    }}
                    crossOrigin="anonymous"
                    src={file.url}
                    alt={title}
                    onLoad={onLoad}
                />
            );
        case FileTypeEnum.Audio:
        case FileTypeEnum.Video:
            return (
                <VideoPlayer
                    dimensions={dimensions}
                    file={file as FileWithUrl}
                    controls={controls}
                    onLoad={onLoad}
                    noInteraction={noInteraction}
                    autoPlayable={autoPlayable}
                />
            );
        case FileTypeEnum.Document:
            return (
                <PDFPlayer
                    dimensions={dimensions}
                    file={file as FileWithUrl}
                    onLoad={onLoad}
                    noInteraction={noInteraction}
                />
            );
        default:
            return (
                <div
                    style={{
                        width: '100%',
                        height: '100%',
                    }}
                >
                    {t('file_player.unsupported_format', {
                        defaultValue: `Unsupported format "{{type}}"`,
                        type: file.type,
                    })}
                </div>
            );
    }
}
