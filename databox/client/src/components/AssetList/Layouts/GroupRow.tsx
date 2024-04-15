import {PropsWithChildren, ReactNode} from 'react';
import {Asset} from '../../../types';
import GroupDivider from './GroupDivider.tsx';

type Props = PropsWithChildren<{
    asset: Asset;
    top?: number | undefined;
}>;

export default function GroupRow({asset: {groupValue}, children, top}: Props) {
    if (!groupValue) {
        return children as ReactNode;
    }

    return (
        <>
            <GroupDivider groupValue={groupValue} top={top} />
            {children}
        </>
    );
}
