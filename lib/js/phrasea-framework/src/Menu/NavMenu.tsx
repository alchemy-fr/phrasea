import {MenuOrientation, NavMenuProps} from './types';
import {useLocation} from 'react-router-dom';
import NavButton from './NavButton';
import NavMenuItem from './NavMenuItem';

export default function NavMenu({orientation, items}: NavMenuProps) {
    const location = useLocation();

    if (orientation === MenuOrientation.Vertical) {
        return <>
            {items.map(({id, label, ...props}) => (
                <NavMenuItem key={id} location={location} {...props}>
                    {label}
                </NavMenuItem>
            ))}
        </>;
    }

    return (
        <>
            {items.map(({id, label, ...props}) => (
                <NavButton key={id} location={location} {...props}>
                    {label}
                </NavButton>
            ))}
        </>
    );
}
