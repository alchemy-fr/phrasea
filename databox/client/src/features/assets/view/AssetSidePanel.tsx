'use client';

import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import type {Asset, Collection} from '@/types/api';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/misc';
import {AttributeList} from '@/features/attributes/AttributeList';
import {AssetInfoList} from './AssetInfoList';
import {CollectionChip} from '@/components/chips';
import {useOptionalSearch} from '@/features/search/SearchProvider';
import {routes} from '@/lib/routes';
import {AssetAttachments} from './AssetAttachments';
import {useAuth} from '@/lib/auth/AuthProvider';
import {AssetMetrics} from './AssetMetrics';
import {useConfig} from '@/lib/config/ConfigProvider';

/** Content of the `details` tab of the asset side panel (see `AssetPanel`). */
export function AssetSidePanel({asset}: {asset: Asset}) {
    const {t} = useTranslation();
    const {isAuthenticated} = useAuth();
    const config = useConfig();
    const collections = asset.collections?.filter(c => !c.storyAsset) ?? [];
    const stories = asset.collections?.filter(c => !!c.storyAsset) ?? [];

    return (
        <Accordion type="multiple" defaultValue={['attributes']}>
            <AccordionItem value="attributes">
                <AccordionTrigger>
                    {t('asset.view.attributes', 'Attributes')}
                </AccordionTrigger>
                <AccordionContent>
                    <AttributeList asset={asset} controls />
                </AccordionContent>
            </AccordionItem>
            <AccordionItem value="info">
                <AccordionTrigger>
                    {t('asset.view.information', 'Information')}
                </AccordionTrigger>
                <AccordionContent>
                    <AssetInfoList asset={asset} />
                </AccordionContent>
            </AccordionItem>
            {collections.length > 0 ? (
                <AccordionItem value="collections">
                    <AccordionTrigger>
                        {t('asset.view.collections', 'Collections')}
                    </AccordionTrigger>
                    <AccordionContent>
                        <CollectionList collections={collections} />
                    </AccordionContent>
                </AccordionItem>
            ) : null}
            {stories.length > 0 ? (
                <AccordionItem value="stories">
                    <AccordionTrigger>
                        {t('asset.view.stories', 'Stories')}
                    </AccordionTrigger>
                    <AccordionContent>
                        <CollectionList collections={stories} />
                    </AccordionContent>
                </AccordionItem>
            ) : null}
            {config.analytics.matomo && isAuthenticated ? (
                <AccordionItem value="metrics">
                    <AccordionTrigger>
                        {t('asset.view.metrics', 'Metrics')}
                    </AccordionTrigger>
                    <AccordionContent>
                        <AssetMetrics assetId={asset.id} />
                    </AccordionContent>
                </AccordionItem>
            ) : null}
            {isAuthenticated ? (
                <AccordionItem value="attachments">
                    <AccordionTrigger>
                        {t('asset.view.attachments', 'Attachments')}
                        {asset.attachments?.length ? (
                            <span className="ml-2 text-xs text-muted-foreground">
                                ({asset.attachments.length})
                            </span>
                        ) : null}
                    </AccordionTrigger>
                    <AccordionContent>
                        <AssetAttachments asset={asset} />
                    </AccordionContent>
                </AccordionItem>
            ) : null}
        </Accordion>
    );
}

function CollectionList({collections}: {collections: Collection[]}) {
    const router = useRouter();
    const search = useOptionalSearch();

    return (
        <ul className="space-y-1">
            {collections.map(c => (
                <li key={c.id}>
                    <CollectionChip
                        collection={c}
                        absolute
                        size="sm"
                        onClick={() => {
                            // Navigates to the search screen
                            if (search) {
                                search.selectCollection(c.id, c);
                            } else {
                                router.push(routes.assets());
                            }
                        }}
                    />
                </li>
            ))}
        </ul>
    );
}
