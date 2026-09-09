import {BasketViewRoute} from '@/features/baskets/view/BasketViewRoute';

export default async function Modal({params}: {params: Promise<{id: string}>}) {
    const {id} = await params;

    return <BasketViewRoute basketId={id} />;
}
