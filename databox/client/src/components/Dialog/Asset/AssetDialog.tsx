import React, {useEffect, useState} from 'react';
import TabbedDialog from "../Tabbed/TabbedDialog";
import {useTranslation} from 'react-i18next';
import EditAsset from "./EditAsset";
import {useParams} from "react-router-dom";
import FullPageLoader from "../../Ui/FullPageLoader";
import {Asset} from "../../../types";
import Acl from "./Acl";
import {getAsset} from "../../../api/asset";
import EditAttributes from "./EditAttributes";
import Renditions from "./Renditions";
import InfoAsset from "./InfoAsset";
import AssetFileVersions from "./AssetFileVersions";
import OperationsAsset from "./OperationsAsset";

type Props = {};

export default function AssetDialog({}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();

    const [data, setData] = useState<Asset>();

    useEffect(() => {
        getAsset(id!).then(c => setData(c));
    }, [id]);

    if (!data) {
        return <FullPageLoader/>
    }

    return <TabbedDialog
        routeName={'app_asset_manage'}
        routeParams={{id}}
        maxWidth={'md'}
        minHeight={400}
        title={t('asset.manage.title', 'Manage asset {{name}}', {
            name: data.title,
        })}
        tabs={[
            {
                title: t('asset.manage.info.title', 'Info'),
                component: InfoAsset,
                id: 'info',
                props: {
                    data,
                },
                enabled: data.capabilities.canEdit,
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
                title: t('asset.manage.operations.title', 'Operations'),
                component: OperationsAsset,
                id: 'operations',
                props: {
                    data,
                },
                enabled: data.capabilities.canEdit,
            },
        ]}
    />
}
