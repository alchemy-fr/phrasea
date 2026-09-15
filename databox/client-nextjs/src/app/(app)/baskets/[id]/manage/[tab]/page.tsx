import {BasketManageTab} from '@/features/baskets/manage/BasketManageRoute';

export default async function Page({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return <BasketManageTab basketId={id} tab={tab} />;
}
