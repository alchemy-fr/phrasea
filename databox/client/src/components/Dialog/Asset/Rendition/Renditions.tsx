import {useCallback, useEffect, useState} from 'react';
import {Asset, AssetRendition} from '../../../../types.ts';
import {DialogTabProps} from '../../Tabbed/TabbedDialog.tsx';
import ContentTab from '../../Tabbed/ContentTab.tsx';
import {
    deleteRendition,
    getAssetRenditions,
} from '../../../../api/rendition.ts';
import {Rendition} from './Rendition.tsx';
import {RenditionSkeleton} from './RenditionSkeleton.tsx';
import ConfirmDialog from '../../../Ui/ConfirmDialog.tsx';
import {toast} from 'react-toastify';
import {useModals} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import UploadRenditionDialog from '../../../Media/Asset/Actions/UploadRenditionDialog.tsx';
import {useChannelRegistration} from '../../../../lib/pusher.ts';

type Props = {
    data: Asset;
} & DialogTabProps;

export default function Renditions({data, onClose, minHeight}: Props) {
    const [renditions, setRenditions] = useState<AssetRendition[]>();
    const [loading, setLoading] = useState(false);
    const {openModal} = useModals();
    const {t} = useTranslation();

    const maxDimensions = {
        width: 300,
        height: 230,
    };

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const r = await getAssetRenditions(data.id);
            setRenditions(r.result);
        } finally {
            setLoading(false);
        }
    }, [data.id]);

    useChannelRegistration(
        'assets',
        'rendition-update',
        (event: {assetId: string}) => {
            if (data.id === event.assetId) {
                load();
            }
        }
    );

    useEffect(() => {
        load();
    }, [load]);

    const onDelete = async (id: string) => {
        openModal(ConfirmDialog, {
            title: t(
                'rendition_delete.confirm',
                'Are you sure you want to delete this rendition?'
            ),
            onConfirm: async () => {
                await deleteRendition(id);
                setRenditions(renditions?.filter(r => r.id !== id));
                toast.success(
                    t(
                        'rendition_delete.confirmed',
                        'Rendition has been deleted!'
                    ) as string
                );
            },
        });
    };

    const onUpload = async (rendition: AssetRendition) => {
        openModal(UploadRenditionDialog, {
            asset: data,
            renditionName: rendition.nameTranslated,
            renditionId: rendition.definition.id,
        });
    };

    return (
        <ContentTab
            onClose={onClose}
            minHeight={minHeight}
            disableGutters={true}
            loading={loading}
        >
            {renditions &&
                renditions.map(r => {
                    return (
                        <Rendition
                            onDelete={() => onDelete(r.id)}
                            asset={data}
                            key={r.id}
                            rendition={r}
                            title={data.resolvedTitle}
                            dimensions={maxDimensions}
                            onUpload={onUpload}
                        />
                    );
                })}
            {!renditions &&
                [0, 1, 2].map(i => (
                    <RenditionSkeleton key={i} dimensions={maxDimensions} />
                ))}
        </ContentTab>
    );
}
