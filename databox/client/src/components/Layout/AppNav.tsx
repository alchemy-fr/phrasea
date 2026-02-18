import {NavItem, NavMenu, NavMenuProps} from '@alchemy/phrasea-framework';
import {routes} from '../../routes.ts';
import {useTranslation} from 'react-i18next';
import {useAuth} from '@alchemy/react-auth';

type Props = {} & Pick<NavMenuProps, 'orientation'>;

export default function AppNav({orientation}: Props) {
    const {t} = useTranslation();
    const {isAuthenticated, user} = useAuth();

    const displayAdminMenu =
        isAuthenticated && user?.roles.includes('databox-admin');

    const items: NavItem[] = [];

    if (displayAdminMenu) {
        items.push(
            {
                id: 'assets',
                label: t('appbar.assets', 'Assets'),
                route: routes.assets,
            },
            {
                id: 'pages',
                label: t('appbar.pages', 'Pages'),
                route: routes.pageAdmin.routes.index,
            }
        );
    }

    return <NavMenu items={items} orientation={orientation} />;
}
