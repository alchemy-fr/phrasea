import AssetsPage from '@/app/(app)/assets/page';
import {BasketManageRoute} from '@/features/baskets/manage/BasketManageRoute';

export default async function Page({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return (
        <>
            <AssetsPage />
            <BasketManageRoute basketId={id} tab={tab} />
        </>
    );
}
