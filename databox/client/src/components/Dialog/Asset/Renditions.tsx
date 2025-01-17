import {useEffect, useState} from 'react';
import {Asset, AssetRendition} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {deleteRendition, getAssetRenditions} from '../../../api/rendition';
import {Rendition} from './Rendition';
import {RenditionSkeleton} from './RenditionSkeleton.tsx';
import ConfirmDialog from '../../Ui/ConfirmDialog.tsx';
import {toast} from 'react-toastify';
import {useModals} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';

type Props = {
    data: Asset;
} & DialogTabProps;

export default function Renditions({data, onClose, minHeight}: Props) {
    const [renditions, setRenditions] = useState<AssetRendition[]>();
    const {openModal} = useModals();
    const {t} = useTranslation();

    const maxDimensions = {
        width: 300,
        height: 230,
    };

    useEffect(() => {
        getAssetRenditions(data.id).then(d => setRenditions(d.result));
    }, [data.id]);

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

    return (
        <ContentTab
            onClose={onClose}
            minHeight={minHeight}
            disableGutters={true}
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
