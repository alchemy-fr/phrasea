import React, {useEffect, useState} from 'react';
import TabbedDialog from "../Tabbed/TabbedDialog";
import {useTranslation} from 'react-i18next';
import EditWorkspace from "./EditWorkspace";
import {useParams} from "react-router-dom";
import {getWorkspace} from "../../../api/workspace";
import FullPageLoader from "../../Ui/FullPageLoader";
import {Workspace} from "../../../types";
import Acl from "./Acl";
import TagRulesTab from "./TagRulesTab";
import AttributeDefinitionManager from "./AttributeDefinitionManager";
import AttributeClassManager from "./AttributeClassManager";
import TagManager from "./TagManager";
import RenditionClassManager from "./RenditionClassManager";
import RenditionDefinitionManager from "./RenditionDefinitionManager";

type Props = {};

export default function WorkspaceDialog({}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();

    const [data, setData] = useState<Workspace>();

    useEffect(() => {
        getWorkspace(id!).then(c => setData(c));
    }, [id]);

    if (!data) {
        return <FullPageLoader/>
    }

    return <TabbedDialog
        routeName={'app_workspace_manage'}
        routeParams={{id}}
        maxWidth={'md'}
        minHeight={400}
        title={t('workspace.manage.title', 'Manage workspace {{name}}', {
            name: data.name,
        })}
        tabs={[
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
                title: t('workspace.manage.attribute_class.title', 'Attribute classes'),
                component: AttributeClassManager,
                id: 'attribute-classes',
                props: {
                    data,
                },
                enabled: data.capabilities.canEdit,
            },
            {
                title: t('workspace.manage.attribute_definitions.title', 'Attributes'),
                component: AttributeDefinitionManager,
                id: 'attribute-definitions',
                props: {
                    data,
                },
                enabled: data.capabilities.canEdit,
            },
            {
                title: t('workspace.manage.rendition_class.title', 'Rendition classes'),
                component: RenditionClassManager,
                id: 'rendition-classes',
                props: {
                    data,
                },
                enabled: data.capabilities.canEdit,
            },
            {
                title: t('workspace.manage.rendition_definition.title', 'Renditions'),
                component: RenditionDefinitionManager,
                id: 'rendition-definitions',
                props: {
                    data,
                },
                enabled: data.capabilities.canEdit,
            },
        ]}
    />
}
