import {useState} from 'react';
import {Collection} from '../../../types';
import {useTranslation} from 'react-i18next';
import {Typography} from '@mui/material';
import {
    CollectionsTreeView,
    treeViewPathSeparator,
} from './CollectionsTreeView';
import {clearWorkspaceCache, moveCollection} from '../../../api/collection';
import {toast} from 'react-toastify';
import {LoadingButton} from '@mui/lab';
import DriveFileMoveIcon from '@mui/icons-material/DriveFileMove';

type Props = {
    collection: Collection;
    onMoved?: () => void;
};

export default function CollectionMoveSection({collection, onMoved}: Props) {
    const {t} = useTranslation();
    const [dest, setDest] = useState<string>('');
    const [loading, setLoading] = useState(false);

    const move = async () => {
        setLoading(true);
        try {
            const d = dest.startsWith('/workspaces/')
                ? 'root'
                : dest.replace(/^\/collections\//, '');
            await moveCollection(collection.id, d);
            clearWorkspaceCache();
            toast.success(t('form.collection_move.success', 'Collection moved!') as string);
            onMoved && onMoved();
        } catch (e) {
            setLoading(false);
        }
    };

    return (
        <div>
            <Typography variant={'h2'} sx={{mb: 1}}>
                {t('collection_move.title', 'Move collection')}
            </Typography>

            <Typography variant={'body1'} sx={{mb: 2}}>
                {t(
                    'collection_move.intro',
                    'Select the destination where to move this collection:'
                )}
            </Typography>

            <CollectionsTreeView
                workspaceId={collection.workspace.id}
                value={dest}
                onChange={collections => {
                    setDest(collections as string);
                }}
                disabledBranches={[
                    `${collection.workspace.id}${treeViewPathSeparator}${collection['@id']}`,
                ]}
            />
            <LoadingButton
                sx={{mt: 2}}
                startIcon={<DriveFileMoveIcon />}
                variant={'contained'}
                onClick={move}
                disabled={loading || !dest}
                loading={loading}
            >
                {t('', 'Move collection')}
            </LoadingButton>
        </div>
    );
}
