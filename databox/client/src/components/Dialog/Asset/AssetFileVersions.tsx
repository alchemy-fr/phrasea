import {useEffect, useState} from 'react';
import {Asset, AssetFileVersion} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {deleteAssetFileVersion, getAssetFileVersions} from '../../../api/asset';
import {
    AssetFileVersionCard,
    AssetFileVersionSkeleton,
} from './AssetFileVersion';
import {useTranslation} from 'react-i18next';
import ConfirmDialog from "../../Ui/ConfirmDialog.tsx";
import {toast} from "react-toastify";
import {useModals} from "@alchemy/navigation";

type Props = {
    data: Asset;
} & DialogTabProps;

const maxDimensions = {
    width: 300,
    height: 230,
};

export default function AssetFileVersions({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
    const [versions, setVersions] = useState<AssetFileVersion[]>();
    const {openModal} = useModals();

    useEffect(() => {
        getAssetFileVersions(data.id).then(d => setVersions(d.result));
    }, []);


    const onDelete = async (id: string) => {
        openModal(ConfirmDialog, {
            title: t(
                'asset_version_delete.confirm',
                'Are you sure you want to delete this version?'
            ),
            onConfirm: async () => {
                await deleteAssetFileVersion(id);

                setVersions(versions?.filter(v => v.id !== id));
                toast.success(
                    t(
                        'asset_version_delete.confirmed',
                        'Version has been deleted!'
                    ) as string
                );
            },
        });
    }

    return (
        <ContentTab
            onClose={onClose}
            minHeight={minHeight}
            disableGutters={true}
        >
            {data.source && (
                <AssetFileVersionCard
                    asset={data}
                    version={{
                        file: data.source,
                        asset: data,
                        id: 'current',
                        name: t('asset_file_versions.current', `Current`),
                        createdAt: '',
                    }}
                    dimensions={maxDimensions}
                />
            )}
            {versions &&
                versions.map(v => {
                    return (
                        <AssetFileVersionCard
                            key={v.id}
                            asset={data}
                            version={v}
                            dimensions={maxDimensions}
                            onDelete={() => onDelete(v.id)}
                        />
                    );
                })}
            {!versions &&
                [0, 1, 2].map(i => (
                    <AssetFileVersionSkeleton
                        key={i}
                        dimensions={maxDimensions}
                    />
                ))}
        </ContentTab>
    );
}
