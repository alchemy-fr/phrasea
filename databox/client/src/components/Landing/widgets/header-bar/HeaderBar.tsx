import {PropsWithChildren} from 'react';
import {AppBar, Container, Toolbar} from '@mui/material';
import {HeaderBarWidgetProps} from './types.ts';
import ElevationScroll from './ElevationScroll.tsx';

type Props = PropsWithChildren<HeaderBarWidgetProps>;

export default function HeaderBar({title, position}: Props) {
    const content = <Toolbar>{title && <div>{title}</div>}</Toolbar>;

    return (
        <>
            <ElevationScroll>
                <AppBar contentEditable={false} position={position}>
                    {position !== 'fixed' ? (
                        <Container>{content}</Container>
                    ) : (
                        content
                    )}
                </AppBar>
            </ElevationScroll>
        </>
    );
}
