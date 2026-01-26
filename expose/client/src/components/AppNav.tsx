import {NavItem, NavMenu, NavMenuProps} from '@alchemy/phrasea-framework';
import {routes} from '../routes.ts';
import {useTranslation} from 'react-i18next';
import {useAuth} from '@alchemy/react-auth';

type Props = {} & Pick<NavMenuProps, 'orientation'>;

export default function AppNav({orientation}: Props) {
    const {t} = useTranslation();
    const {isAuthenticated, user} = useAuth();

    const displayAdminMenu =
        isAuthenticated && user?.roles.includes('expose-admin');

    const items: NavItem[] = [];

    if (displayAdminMenu) {
        items.push(
            {
                id: 'publications',
                label: t('appbar.publications', 'Publications'),
                route: routes.index,
            },
            {
                id: 'profiles',
                label: t('appbar.profiles', 'Profiles'),
                route: routes.profile.routes.index,
            }
        );
    }

    return <NavMenu items={items} orientation={orientation} />;
}
