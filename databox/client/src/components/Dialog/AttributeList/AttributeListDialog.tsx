import {useEffect, useState} from 'react';
import TabbedDialog from '../Tabbed/TabbedDialog';
import {useTranslation} from 'react-i18next';
import {useParams} from '@alchemy/navigation';
import FullPageLoader from '../../Ui/FullPageLoader';
import {AttributeList} from '../../../types';
import Acl from './Acl';
import {modalRoutes} from '../../../routes';
import {useCloseModal} from '../../Routing/ModalLink';
import EditAttributeList from './EditAttributeList';
import {getAttributeList} from "../../../api/attributeList.ts";
import OrganizeAttributeList from "./OrganizeAttributeList.tsx";
import {useAttributeListStore} from "../../../store/attributeListStore.ts";

type Props = {};

export default function AttributeListDialog({}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();
    const closeModal = useCloseModal();

    const loadList = useAttributeListStore(state => state.loadList)
    const lists = useAttributeListStore(state => state.lists);
    const data = lists.find(l => l.id === id);

    useEffect(() => {
        loadList(id!)
            .catch(() => {
                closeModal();
            });
    }, [loadList, id]);

    if (!data) {
        return <FullPageLoader />;
    }

    return (
        <TabbedDialog
            route={modalRoutes.attributeList.routes.manage}
            routeParams={{id}}
            maxWidth={'md'}
            title={t('attribute_list.manage.title', 'Manage Attribute List {{name}}', {
                name: data.title,
            })}
            tabs={[
                {
                    title: t('attribute_list.manage.organize.title', 'Organize'),
                    component: OrganizeAttributeList,
                    id: 'organize',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('attribute_list.manage.edit.title', 'Edit'),
                    component: EditAttributeList,
                    id: 'edit',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('attribute_list.manage.acl.title', 'Permissions'),
                    component: Acl,
                    id: 'permissions',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEditPermissions,
                },
            ]}
        />
    );
}
