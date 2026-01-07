import {Button} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {Publication} from '../../../../types.ts';
import ArchiveIcon from '@mui/icons-material/Archive';

type Props = {
    publication: Publication;
};

export default function DownloadArchiveButton({publication}: Props) {
    const {t} = useTranslation();

    const onDownload = () => {
        publication.archiveDownloadUrl;
    };

    return (
        <>
            <Button
                onClick={onDownload}
                variant={'outlined'}
                startIcon={<ArchiveIcon />}
            >
                {t('downloadArchiveButton.downloadArchive', 'Download Archive')}
            </Button>
        </>
    );
}
