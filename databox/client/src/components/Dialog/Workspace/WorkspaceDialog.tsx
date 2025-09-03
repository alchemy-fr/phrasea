import {useEffect, useState} from 'react';
import TabbedDialog from '../Tabbed/TabbedDialog';
import {useTranslation} from 'react-i18next';
import EditWorkspace from './EditWorkspace';
import {useParams} from '@alchemy/navigation';
import {getWorkspace} from '../../../api/workspace';
import FullPageLoader from '../../Ui/FullPageLoader';
import {Workspace} from '../../../types';
import Acl from './Acl';
import TagRulesTab from './TagRulesTab';
import AttributeDefinitionManager from './AttributeDefinitionManager';
import AttributePolicyManager from './AttributePolicyManager';
import TagManager from './TagManager';
import RenditionPolicyManager from './RenditionPolicyManager.tsx';
import RenditionDefinitionManager from './RenditionDefinitionManager';
import InfoWorkspace from './InfoWorkspace';
import {modalRoutes} from '../../../routes';
import {useCloseModal} from '../../Routing/ModalLink';
import IntegrationManager from './IntegrationManager.tsx';
import EntityListManager from './EntityListManager.tsx';
import {useWorkspaceStore} from '../../../store/workspaceStore.ts';

type Props = {};

export default function WorkspaceDialog({}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();
    const [data, proxySetData] = useState<Workspace>();
    const closeModal = useCloseModal();

    const updateWorkspace = useWorkspaceStore(s => s.updateWorkspace);

    const setData = (newData: Workspace) => {
        updateWorkspace(newData);
        proxySetData({...newData});
    };

    useEffect(() => {
        getWorkspace(id!)
            .then(c => setData(c))
            .catch(e => {
                console.error(e);
                closeModal();
            });
    }, [id]);

    if (!data) {
        return <FullPageLoader />;
    }

    return (
        <TabbedDialog
            route={modalRoutes.workspaces.routes.manage}
            routeParams={{id}}
            maxWidth={'lg'}
            title={t('workspace.manage.title', 'Manage workspace {{name}}', {
                name: data.nameTranslated,
            })}
            tabs={[
                {
                    title: t('workspace.manage.info.title', 'Info'),
                    component: InfoWorkspace,
                    id: 'info',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('workspace.manage.edit.title', 'Edit'),
                    component: EditWorkspace,
                    id: 'edit',
                    props: {
                        data,
                        setData,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('workspace.manage.acl.title', 'Permissions'),
                    component: Acl,
                    id: 'permissions',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEditPermissions,
                },
                {
                    title: t('workspace.manage.tags.title', 'Tags'),
                    component: TagManager,
                    id: 'tags',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t(
                        'workspace.manage.attribute_entity.title',
                        'Entities'
                    ),
                    component: EntityListManager,
                    id: 'entities',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t(
                        'workspace.manage.attribute_definitions.title',
                        'Attributes'
                    ),
                    component: AttributeDefinitionManager,
                    id: 'attribute-definitions',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t(
                        'workspace.manage.rendition_definition.title',
                        'Renditions'
                    ),
                    component: RenditionDefinitionManager,
                    id: 'rendition-definitions',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t(
                        'workspace.manage.rendition_policy.title',
                        'Rendition classes'
                    ),
                    component: RenditionPolicyManager,
                    id: 'rendition-classes',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t(
                        'workspace.manage.attribute_policy.title',
                        'Attribute classes'
                    ),
                    component: AttributePolicyManager,
                    id: 'attribute-classes',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t(
                        'workspace.manage.integrations.title',
                        'Integrations'
                    ),
                    component: IntegrationManager,
                    id: 'integrations',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('workspace.manage.tag_rules.title', 'Tag rules'),
                    component: TagRulesTab,
                    id: 'tag-rules',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
            ]}
        />
    );
}
