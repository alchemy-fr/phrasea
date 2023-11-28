import {useCallback, useState} from 'react';
import SvgMenu from '../../icons/Menu';

type Props = {
    dashboardBaseUrl: string;
};

export default function DashboardMenu({
                                          dashboardBaseUrl,
                                      }: Props) {
    const [open, setOpen] = useState(false);
    const [openedOnce, setOpenedOnce] = useState(false);

    const toggleMenu = useCallback(() => {
        if (!openedOnce) {
            setOpenedOnce(true);
        }
        setOpen(p => !p);
    }, [openedOnce, setOpen, setOpenedOnce]);

    const size = 45;
    const bodyPadding = 20;

    return <div
        className={`services-menu${open ? ' opened' : ''}`}
        style={{
            position: 'absolute',
            cursor: 'pointer',
            zIndex: 1000,
            borderRadius: `50%`,
            border: `1px solid #CCC`,
            width: size,
            height: size,
            top: bodyPadding,
            right: bodyPadding,
            padding: Math.round(size / 5),
        }}
        onClick={toggleMenu}
    >
        <SvgMenu/>
        {(open || openedOnce) && <div
            className={'services-menu-content'}
            style={{
                display: open ? 'block' : 'none',
                top: size,
                right: 0,
                position: 'absolute',
                borderRadius: `10px`,
                border: `1px solid #CCC`,
                overflow: 'hidden',
            }}
        >
            <iframe
                title={'services-menu'}
                src={`${dashboardBaseUrl}/menu.html`}
                seamless
                style={{
                    border: '0',
                    minWidth: `350px`,
                    height: `350px`,
                }}
            />
        </div>}
    </div>
}
