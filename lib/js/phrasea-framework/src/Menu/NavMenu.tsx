import {MenuOrientation, NavMenuProps} from './types';
import {useLocation} from 'react-router-dom';
import NavButton from './NavButton';
import NavMenuItem from './NavMenuItem';
import {ListItemIcon} from '@mui/material';

export default function NavMenu({orientation, items}: NavMenuProps) {
    const location = useLocation();

    if (orientation === MenuOrientation.Vertical) {
        return <>
            {items.map(({id, label, icon, ...props}) => (
                <NavMenuItem
                    key={id}
                    location={location}
                    {...props}
                >
                    {icon ? <ListItemIcon>{icon}</ListItemIcon> : null}
                    {label}
                </NavMenuItem>
            ))}
        </>;
    }

    return (
        <>
            {items.map(({id, label, icon, ...props}) => (
                <NavButton
                    key={id}
                    location={location}
                    startIcon={icon}
                    {...props}
                >
                    {label}
                </NavButton>
            ))}
        </>
    );
}
