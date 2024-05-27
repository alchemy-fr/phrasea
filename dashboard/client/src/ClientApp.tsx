import Service, {ServiceBaseProps} from './Service.tsx';
import AdminPanelSettingsIcon from '@mui/icons-material/AdminPanelSettings';
import ApiIcon from '@mui/icons-material/Api';

type Props = {
    apiUrl: string;
    clientUrl: string;
    isAdmin?: boolean;
} & ServiceBaseProps;

export default function ClientApp({
    apiUrl,
    clientUrl,
    isAdmin,
    ...props
}: Props) {
    const links = [];
    if (isAdmin) {
        links.push({
            icon: <AdminPanelSettingsIcon />,
            href: `${apiUrl}/admin`,
            title: `Admin of ${props.title}`,
        });
    }
    links.push({
        icon: <ApiIcon />,
        href: apiUrl,
        title: `API documentation of ${props.title}`,
    });
    return <Service mainUrl={clientUrl} links={links} {...props} />;
}
