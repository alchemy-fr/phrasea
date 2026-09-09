import {PublicPageScreen} from '@/features/cms/PublicPageScreen';

export default async function PublicPage({
    params,
}: {
    params: Promise<{slug: string}>;
}) {
    const {slug} = await params;

    return <PublicPageScreen slug={slug} />;
}
