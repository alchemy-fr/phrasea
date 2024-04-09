import {DOMAttributes, MouseEventHandler, PropsWithChildren} from 'react';
import assetClasses from '../../AssetList/classes';

type Props = PropsWithChildren<
    {
        size: number;
        onMouseOver?: MouseEventHandler | undefined;
    } & DOMAttributes<HTMLElement>
>;

export default function Thumb({
    children,
    onMouseOver,
    onMouseLeave,
}: Props) {
    return (
        <div
            onMouseOver={onMouseOver}
            onMouseLeave={onMouseLeave}
            className={assetClasses.thumbWrapper}
        >
            {children}
        </div>
    );
}
