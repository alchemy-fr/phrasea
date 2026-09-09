import {BasketManageRoute} from '@/features/baskets/manage/BasketManageRoute';

export default async function Modal({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return <BasketManageRoute basketId={id} tab={tab} />;
}
