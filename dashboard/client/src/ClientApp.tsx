import Service, {ServiceBaseProps} from './Service.tsx';
import AdminPanelSettingsIcon from '@mui/icons-material/AdminPanelSettings';
import ApiIcon from '@mui/icons-material/Api';

type Props = {
    apiUrl: string;
    clientUrl: string;
} & ServiceBaseProps;

export default function ClientApp({apiUrl, clientUrl, ...props}: Props) {
    return (
        <Service
            mainUrl={clientUrl}
            links={[
                {
                    icon: <AdminPanelSettingsIcon />,
                    href: `${apiUrl}/admin`,
                    title: `Admin of ${props.title}`,
                },
                {
                    icon: <ApiIcon />,
                    href: apiUrl,
                    title: `API documentation of ${props.title}`,
                },
            ]}
            {...props}
        />
    );
}
