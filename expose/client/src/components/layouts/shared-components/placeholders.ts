import previewVideoImg from '../../../images/player.webp';
import squareImg from '../../../images/square.svg';
import pdfImg from '../../../images/pdf.svg';

export function getThumbPlaceholder(type: string): string {
    switch (true) {
        case type === 'application/pdf':
            return pdfImg;
        case type.startsWith('video/'):
        case type.startsWith('audio/'):
            return previewVideoImg;
        default:
            return squareImg;
    }
}

export function getPosterPlaceholder(type: string): string | undefined {
    switch (true) {
        case type === 'application/pdf':
            return pdfImg;
        case type.startsWith('video/'):
        case type.startsWith('audio/'):
            return previewVideoImg;
        default:
            return squareImg;
    }
}
