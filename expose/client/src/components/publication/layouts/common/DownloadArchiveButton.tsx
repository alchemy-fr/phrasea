import {Button} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {Publication} from '../../../../types.ts';
import ArchiveIcon from '@mui/icons-material/Archive';
import {useDownload} from '../../../../hooks/useDownload.ts';

type Props = {
    publication: Publication;
};

export default function DownloadArchiveButton({publication}: Props) {
    const {t} = useTranslation();

    const onDownload = useDownload({
        publication,
        newWindow: true,
    });

    return (
        <Button
            onClick={() => onDownload(publication.archiveDownloadUrl!)}
            variant={'outlined'}
            startIcon={<ArchiveIcon />}
        >
            {t('downloadArchiveButton.downloadArchive', 'Download Archive')}
        </Button>
    );
}
