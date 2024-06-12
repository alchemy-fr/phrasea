import {useEffect, useState} from 'react';
import TabbedDialog from '../Tabbed/TabbedDialog';
import {useTranslation} from 'react-i18next';
import {useParams} from '@alchemy/navigation';
import FullPageLoader from '../../Ui/FullPageLoader';
import {Basket} from '../../../types';
import Acl from './Acl';
import InfoBasket from './InfoBasket';
import {modalRoutes} from '../../../routes';
import {useCloseModal} from '../../Routing/ModalLink';
import {getBasket} from '../../../api/basket';
import EditBasket from './EditBasket';
import Operations from './Operations';
import Integrations from './Integrations.tsx';

type Props = {};

export default function BasketDialog({}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();
    const [data, setData] = useState<Basket>();
    const closeModal = useCloseModal();

    useEffect(() => {
        getBasket(id!)
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
            route={modalRoutes.baskets.routes.manage}
            routeParams={{id}}
            maxWidth={'md'}
            minHeight={400}
            title={t('basket.manage.title', 'Manage basket {{name}}', {
                name: data.title,
            })}
            tabs={[
                {
                    title: t('basket.manage.info.title', 'Info'),
                    component: InfoBasket,
                    id: 'info',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('basket.manage.edit.title', 'Edit'),
                    component: EditBasket,
                    id: 'edit',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('basket.manage.acl.title', 'Permissions'),
                    component: Acl,
                    id: 'permissions',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEditPermissions,
                },
                {
                    title: t('basket.manage.operations.title', 'Operations'),
                    component: Operations,
                    id: 'ops',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t(
                        'basket.manage.integrations.title',
                        'Integrations'
                    ),
                    component: Integrations,
                    id: 'integrations',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
            ]}
        />
    );
}
