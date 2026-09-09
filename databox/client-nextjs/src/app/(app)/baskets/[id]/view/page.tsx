import AssetsPage from '@/app/(app)/assets/page';
import {BasketViewRoute} from '@/features/baskets/view/BasketViewRoute';

export default async function Page({params}: {params: Promise<{id: string}>}) {
    const {id} = await params;

    return (
        <>
            <AssetsPage />
            <BasketViewRoute basketId={id} />
        </>
    );
}
