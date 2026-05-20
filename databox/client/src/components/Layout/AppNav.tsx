import {
    BetaChip,
    NavItem,
    NavMenu,
    NavMenuProps,
} from '@alchemy/phrasea-framework';
import {routes} from '../../routes.ts';
import {useTranslation} from 'react-i18next';
import {useAuth} from '@alchemy/react-auth';
import {getPath, useLocation} from '@alchemy/navigation';
import MenuBookIcon from '@mui/icons-material/MenuBook';

type Props = {} & Pick<NavMenuProps, 'orientation'>;

export default function AppNav({orientation}: Props) {
    const {t} = useTranslation();
    const {isAuthenticated, user} = useAuth();
    const location = useLocation();

    const displayAdminMenu =
        isAuthenticated && user?.roles.includes('databox-admin');

    const items: NavItem[] = [];

    if (displayAdminMenu) {
        if (!location.pathname.startsWith(getPath(routes.assets))) {
            items.push({
                id: 'assets',
                label: t('appbar.assets', 'Assets'),
                route: routes.assets,
            });
        }
        if (
            !location.pathname.startsWith(
                getPath(routes.pageAdmin.routes.index)
            )
        ) {
            items.push({
                id: 'pages',
                icon: <MenuBookIcon />,
                label: (
                    <>
                        {t('appbar.pages', 'Pages')}
                        <BetaChip sx={{ml: 1}} size={'small'} />
                    </>
                ),
                route: routes.pageAdmin.routes.index,
            });
        }
    }

    return <NavMenu items={items} orientation={orientation} />;
}
