import React, {useEffect, useState} from 'react';
import TabbedDialog from "../Tabbed/TabbedDialog";
import {useTranslation} from 'react-i18next';
import EditCollection from "./EditCollection";
import {useParams} from "react-router-dom";
import FullPageLoader from "../../Ui/FullPageLoader";
import {Collection} from "../../../types";
import Acl from "./Acl";
import {getCollection} from "../../../api/collection";
import TagRulesTab from "./TagRulesTab";
import Operations from "./Operations";
import InfoCollection from "./InfoCollection";

type Props = {};

export default function CollectionDialog({}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();

    const [data, setData] = useState<Collection>();

    useEffect(() => {
        getCollection(id!).then(c => setData(c));
    }, [id]);

    if (!data) {
        return <FullPageLoader/>
    }

    return <TabbedDialog
        routeName={'app_collection_manage'}
        routeParams={{id}}
        maxWidth={'md'}
        minHeight={400}
        title={t('collection.manage.title', 'Manage collection {{name}}', {
            name: data.title,
        })}
        tabs={[
            {
                title: t('collection.manage.info.title', 'Info'),
                component: InfoCollection,
                id: 'info',
                props: {
                    data,
                },
                enabled: data.capabilities.canEdit,
            },
            {
                title: t('collection.manage.edit.title', 'Edit'),
                component: EditCollection,
                id: 'edit',
                props: {
                    data,
                },
                enabled: data.capabilities.canEdit,
            },
            {
                title: t('collection.manage.acl.title', 'Permissions'),
                component: Acl,
                id: 'permissions',
                props: {
                    data,
                },
                enabled: data.capabilities.canEditPermissions,
            },
            {
                title: t('collection.manage.tag_rules.title', 'Tag rules'),
                component: TagRulesTab,
                id: 'tag-rules',
                props: {
                    data,
                },
                enabled: data.capabilities.canEdit,
            },
            {
                title: t('collection.manage.operations.title', 'Operations'),
                component: Operations,
                id: 'ops',
                props: {
                    data,
                },
                enabled: data.capabilities.canEdit,
            },
        ]}
    />
}
