import React from 'react';
import {File} from "../../../types";
import ImageIcon from '@mui/icons-material/Image';
import PictureAsPdfIcon from '@mui/icons-material/PictureAsPdf';
import AudioFileIcon from '@mui/icons-material/AudioFile';
import VideoFileIcon from '@mui/icons-material/VideoFile';
import ArticleIcon from '@mui/icons-material/Article';
import InsertDriveFileIcon from '@mui/icons-material/InsertDriveFile';
import {SvgIcon, SvgIconProps} from "@mui/material";
import {FileTypeEnum, getFileTypeFromMIMEType} from "../../../lib/file";

function getIconFromType(type: string | undefined): typeof SvgIcon {
    switch (getFileTypeFromMIMEType(type)) {
        case FileTypeEnum.Image:
            return ImageIcon;
        case FileTypeEnum.Video:
            return VideoFileIcon;
        case FileTypeEnum.Audio:
            return AudioFileIcon;
        default:
            switch (type) {
                case 'application/pdf':
                    return PictureAsPdfIcon;
                case 'text/csv':
                case 'application/csv':
                case 'application/json':
                case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                case 'application/vnd.ms-excel.sheet.binary.macroEnabled.12':
                case 'application/vnd.ms-excel':
                case 'application/vnd.ms-excel.sheet.macroEnabled.12':
                    return ArticleIcon;
                case 'application/msword':
                case 'application/vnd.ms-word.document.macroEnabled.12':
                case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                case 'application/vnd.openxmlformats-officedocument.wordprocessingml.template':
                case 'application/vnd.ms-word.template.macroEnabled.12':
                case 'text/html':
                case 'application/vnd.ms-powerpoint.template.macroEnabled.12':
                case 'application/vnd.openxmlformats-officedocument.presentationml.template':
                case 'application/vnd.ms-powerpoint.addin.macroEnabled.12':
                case 'application/vnd.openxmlformats-officedocument.presentationml.slideshow':
                case 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12':
                case 'application/vnd.ms-powerpoint':
                case 'application/vnd.ms-powerpoint.presentation.macroEnabled.12':
                case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                case 'application/rtf':
                case 'text/rtf':
                case 'text/plain':
                    return ArticleIcon;
                default:
                    return InsertDriveFileIcon;
            }
    }
}

type Props = {
    file: File;
} & SvgIconProps;

export default function AssetFileIcon({
                                          file,
    ...iconProps
                                      }: Props) {
    return React.createElement(getIconFromType(file.type), {
        fontSize: 'large',
        ...iconProps,
    });
}
