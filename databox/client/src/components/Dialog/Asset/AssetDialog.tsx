import React, {useEffect, useState} from 'react';
import TabbedDialog from "../Tabbed/TabbedDialog";
import {useTranslation} from 'react-i18next';
import EditAsset from "./EditAsset";
import {useParams} from "react-router-dom";
import FullPageLoader from "../../Ui/FullPageLoader";
import {Asset} from "../../../types";
import Acl from "./Acl";
import {getAsset} from "../../../api/asset";

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
                title: t('asset.manage.edit.title', 'Edit'),
                component: EditAsset,
                id: 'edit',
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
        ]}
    />
}
