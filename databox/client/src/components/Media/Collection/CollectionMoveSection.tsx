import {useState} from 'react';
import {Collection} from '../../../types';
import {useTranslation} from 'react-i18next';
import {Button, Typography} from '@mui/material';
import {clearWorkspaceCache, moveCollection} from '../../../api/collection';
import {toast} from 'react-toastify';
import DriveFileMoveIcon from '@mui/icons-material/DriveFileMove';
import CollectionsTreeView from './CollectionTree/CollectionsTreeView.tsx';

// TODO test separator consistency
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
                ? undefined
                : dest.replace(/^\/collections\//, '');
            await moveCollection(collection.id, d);

            clearWorkspaceCache();
            toast.success(
                t('form.collection_move.success', 'Collection moved!') as string
            );
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
                onChange={collection => {
                    setDest(collection!.id!);
                }}
                disabledBranches={[collection.id]}
            />
            <Button
                sx={{mt: 2}}
                startIcon={<DriveFileMoveIcon />}
                variant={'contained'}
                onClick={move}
                disabled={loading || !dest}
                loading={loading}
            >
                {t('collection_move.move.label', 'Move collection')}
            </Button>
        </div>
    );
}
