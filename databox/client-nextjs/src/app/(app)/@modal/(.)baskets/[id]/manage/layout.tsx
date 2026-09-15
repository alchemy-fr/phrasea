import {type ReactNode} from 'react';
import {BasketManageShell} from '@/features/baskets/manage/BasketManageRoute';

export default async function Layout({
    children,
    params,
}: {
    children: ReactNode;
    params: Promise<{id: string}>;
}) {
    const {id} = await params;

    return <BasketManageShell basketId={id}>{children}</BasketManageShell>;
}
