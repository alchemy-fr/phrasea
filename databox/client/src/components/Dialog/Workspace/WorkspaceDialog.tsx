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
import AttributeClassManager from './AttributeClassManager';
import TagManager from './TagManager';
import RenditionClassManager from './RenditionClassManager';
import RenditionDefinitionManager from './RenditionDefinitionManager';
import InfoWorkspace from './InfoWorkspace';
import {modalRoutes} from '../../../routes';
import {useCloseModal} from '../../Routing/ModalLink';
import AttributeEntityManager from './AttributeEntityManager.tsx';
import IntegrationManager from './IntegrationManager.tsx';

type Props = {};

export default function WorkspaceDialog({}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();
    const [data, setData] = useState<Workspace>();
    const closeModal = useCloseModal();

    useEffect(() => {
        getWorkspace(id!)
            .then(c => setData(c))
            .catch(() => {
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
            maxWidth={'md'}
            title={t('workspace.manage.title', 'Manage workspace {{name}}', {
                name: data.name,
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
                    title: t('workspace.manage.tag_rules.title', 'Tag rules'),
                    component: TagRulesTab,
                    id: 'tag-rules',
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
                    component: AttributeEntityManager,
                    id: 'attribute-entity',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t(
                        'workspace.manage.attribute_class.title',
                        'Attribute classes'
                    ),
                    component: AttributeClassManager,
                    id: 'attribute-classes',
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
                    title: t(
                        'workspace.manage.rendition_class.title',
                        'Rendition classes'
                    ),
                    component: RenditionClassManager,
                    id: 'rendition-classes',
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
            ]}
        />
    );
}
