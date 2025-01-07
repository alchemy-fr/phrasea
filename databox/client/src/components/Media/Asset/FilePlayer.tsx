import {File} from '../../../types';
import {FileTypeEnum, getFileTypeFromMIMEType} from '../../../lib/file';
import AssetFileIcon from './AssetFileIcon';
import VideoPlayer from './Players/VideoPlayer';
import {FileWithUrl, PlayerProps} from './Players';
import PDFPlayer from './Players/PDFPlayer';
import ImagePlayer from "./Players/ImagePlayer.tsx";

type Props = {
    file: File;
    autoPlayable?: boolean;
} & Omit<PlayerProps, "file">;

export default function FilePlayer({
    file,
    title,
    onLoad,
    controls,
    noInteraction,
    autoPlayable,
    dimensions,
    annotations,
    onNewAnnotation,
}: Props) {
    const mainType = getFileTypeFromMIMEType(file.type);

    if (file.url) {
        const props: PlayerProps = {
            file: file as FileWithUrl,
            title,
            onLoad,
            controls,
            dimensions,
            annotations,
            noInteraction,
            onNewAnnotation,
        };

        switch (mainType) {
            case FileTypeEnum.Image:
                return <ImagePlayer
                    {...props}
                />
            case FileTypeEnum.Audio:
            case FileTypeEnum.Video:
                return (
                    <VideoPlayer
                        {...props}
                        autoPlayable={autoPlayable || false}
                    />
                );
            case FileTypeEnum.Document:
                if (file.type === 'application/pdf') {
                    return (
                        <PDFPlayer
                            {...props}
                        />
                    );
                }
        }
    }

    return <AssetFileIcon file={file}/>;
}
