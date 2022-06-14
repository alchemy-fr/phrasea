import React, {useEffect, useState} from 'react';
import TabbedDialog from "../Tabbed/TabbedDialog";
import {useTranslation} from 'react-i18next';
import EditWorkspace from "./EditWorkspace";
import {useParams} from "react-router-dom";
import {getWorkspace} from "../../../api/workspace";
import FullPageLoader from "../../Ui/FullPageLoader";
import {Workspace} from "../../../types";
import Acl from "./Acl";
import Tags from "./Tags";

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
        title={t('workspace.manage.title', 'Manage workspace')}
        tabs={[
            {
                title: t('workspace.manage.edit.title', 'Edit'),
                component: EditWorkspace,
                id: 'edit',
                props: {
                    workspace: data,
                },
                enabled: data.capabilities.canEdit,
            },
            {
                title: t('workspace.manage.acl.title', 'Permissions'),
                component: Acl,
                id: 'acl',
                props: {
                    workspace: data,
                },
                enabled: data.capabilities.canEditPermissions,
            },
            {
                title: t('workspace.manage.tags.title', 'Tags'),
                component: Tags,
                id: 'tags',
                props: {
                    workspace: data,
                },
                enabled: data.capabilities.canEdit,
            },
        ]}
    />
}
