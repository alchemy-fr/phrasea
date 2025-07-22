import {useCallback, useEffect, useState} from 'react';
import {Asset, AssetRendition, RenditionDefinition} from '../../../../types.ts';
import {DialogTabProps} from '../../Tabbed/TabbedDialog.tsx';
import ContentTab from '../../Tabbed/ContentTab.tsx';
import {
    deleteRendition,
    getAssetRenditions,
    getRenditionDefinitions,
} from '../../../../api/rendition.ts';
import {Rendition} from './Rendition.tsx';
import {RenditionSkeleton} from './RenditionSkeleton.tsx';
import ConfirmDialog from '../../../Ui/ConfirmDialog.tsx';
import {toast} from 'react-toastify';
import {useModals} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import UploadRenditionDialog from '../../../Media/Asset/Actions/UploadRenditionDialog.tsx';
import {useChannelRegistration} from '../../../../lib/pusher.ts';
import {RenditionPlaceholder} from './RenditionPlaceholder.tsx';

type Props = {
    data: Asset;
} & DialogTabProps;

export default function Renditions({data, onClose, minHeight}: Props) {
    const [renditions, setRenditions] = useState<AssetRendition[]>();
    const [definitions, setDefinitions] = useState<RenditionDefinition[]>();
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
            const [r, rd] = await Promise.all([
                getAssetRenditions(data.id),
                getRenditionDefinitions({
                    workspaceIds: [data.workspace.id],
                }),
            ]);
            setRenditions(r.result);
            setDefinitions(rd.result);
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

    const onUploadFromDef = async (def: RenditionDefinition) => {
        openModal(UploadRenditionDialog, {
            asset: data,
            renditionName: def.nameTranslated,
            renditionId: def.id,
        });
    };

    const remainingDefinitions = definitions?.filter(
        d => !renditions?.some(r => r.definition.id === d.id)
    );

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
            {remainingDefinitions && remainingDefinitions.length > 0 && (
                <>
                    {remainingDefinitions.map(def => (
                        <RenditionPlaceholder
                            definition={def}
                            key={def.id}
                            dimensions={maxDimensions}
                            onUpload={onUploadFromDef}
                        />
                    ))}
                </>
            )}
        </ContentTab>
    );
}
