import {useEffect} from 'react';
import TabbedDialog from '../Tabbed/TabbedDialog';
import {useTranslation} from 'react-i18next';
import EditAsset from './EditAsset';
import {useParams} from '@alchemy/navigation';
import FullPageLoader from '../../Ui/FullPageLoader';
import Acl from './Acl';
import EditAttributes from './EditAttributes';
import Renditions from './Rendition/Renditions.tsx';
import InfoAsset from './InfoAsset';
import AssetFileVersions from './AssetFileVersions';
import OperationsAsset from './OperationsAsset';
import {modalRoutes} from '../../../routes';
import {useNavigateToModal} from '../../Routing/ModalLink.tsx';
import AssetWorkflow from './AssetWorkflow.tsx';
import {useAuth} from '@alchemy/react-auth';
import ESDocument from './ESDocument.tsx';
import {useAssetStore} from '../../../store/assetStore.ts';

type Props = {};

export default function AssetDialog({}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();
    const navigateToModal = useNavigateToModal();
    const {user} = useAuth();

    const assets = useAssetStore(state => state.assets);
    const loadAsset = useAssetStore(state => state.loadAsset);
    const data = assets[id!];

    useEffect(() => {
        loadAsset(id!);
    }, [loadAsset, id]);

    if (!data) {
        return <FullPageLoader />;
    }

    return (
        <TabbedDialog
            route={modalRoutes.assets.routes.manage}
            routeParams={{id}}
            maxWidth={'md'}
            title={t('asset.manage.title', 'Manage asset {{name}}', {
                name: data.title,
            })}
            tabs={[
                {
                    title: t('asset.manage.open.title', 'Open'),
                    onClick: () =>
                        navigateToModal(modalRoutes.assets.routes.view, {
                            id: data.id,
                            renditionId: data.original?.id,
                        }),
                    id: 'open',
                    props: {
                        data,
                    },
                    enabled: !!data.original,
                },
                {
                    title: t('asset.manage.info.title', 'Info'),
                    component: InfoAsset,
                    id: 'info',
                    props: {
                        data,
                    },
                },
                {
                    title: t('asset.manage.edit.title', 'Edit'),
                    component: EditAsset,
                    id: 'edit',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('asset.manage.attributes.title', 'Attributes'),
                    component: EditAttributes,
                    id: 'attributes',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEditAttributes,
                },
                {
                    title: t('asset.manage.renditions.title', 'Renditions'),
                    component: Renditions,
                    id: 'renditions',
                    props: {
                        data,
                    },
                    enabled: true,
                },
                {
                    title: t('asset.manage.acl.versions', 'Versions'),
                    component: AssetFileVersions,
                    id: 'versions',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('asset.manage.acl.title', 'Permissions'),
                    component: Acl,
                    id: 'permissions',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEditPermissions,
                },
                {
                    title: t('asset.manage.workflow.title', 'Workflow'),
                    component: AssetWorkflow,
                    id: 'workflow',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('asset.manage.operations.title', 'Operations'),
                    component: OperationsAsset,
                    id: 'operations',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('asset.manage.es_doc.title', 'ES Document'),
                    component: ESDocument,
                    id: 'es_doc',
                    props: {
                        data,
                        entity: 'assets',
                    },
                    enabled: user?.roles?.includes('tech') ?? false,
                },
            ]}
        />
    );
}
