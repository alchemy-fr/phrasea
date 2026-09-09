'use client';

import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import type {Asset, AssetRendition} from '@/types/api';
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
import {Discussion} from '@/features/discussion/Discussion';
import {AssetAttachments} from './AssetAttachments';
import {useAuth} from '@/lib/auth/AuthProvider';
import {AssetMetrics} from './AssetMetrics';
import {useConfig} from '@/lib/config/ConfigProvider';
import {FileIntegrations} from '@/features/integrations/FileIntegrations';

export function AssetSidePanel({
    asset,
    rendition,
}: {
    asset: Asset;
    rendition?: AssetRendition;
}) {
    const {t} = useTranslation();
    const router = useRouter();
    const search = useOptionalSearch();
    const {isAuthenticated} = useAuth();
    const config = useConfig();

    return (
        <Accordion
            type="multiple"
            defaultValue={['attributes', 'discussion']}
            className="px-4"
        >
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
            {asset.collections && asset.collections.length > 0 ? (
                <AccordionItem value="appears-in">
                    <AccordionTrigger>
                        {t('asset.view.appears_in', 'Appears in')}
                    </AccordionTrigger>
                    <AccordionContent>
                        <ul className="space-y-1">
                            {asset.collections.map(c => (
                                <li key={c.id}>
                                    <CollectionChip
                                        collection={c}
                                        absolute
                                        size="sm"
                                        onClick={() => {
                                            router.push(routes.assets());
                                            setTimeout(
                                                () =>
                                                    search?.selectCollection(
                                                        c.id,
                                                        c
                                                    ),
                                                0
                                            );
                                        }}
                                    />
                                </li>
                            ))}
                        </ul>
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
            {isAuthenticated ? (
                <AccordionItem value="discussion">
                    <AccordionTrigger>
                        {t('asset.view.discussion', 'Discussion')}
                    </AccordionTrigger>
                    <AccordionContent>
                        <Discussion
                            threadKey={asset.threadKey}
                            threadId={asset.thread?.id}
                        />
                    </AccordionContent>
                </AccordionItem>
            ) : null}
            {isAuthenticated && rendition?.file ? (
                <AccordionItem value="integrations">
                    <AccordionTrigger>
                        {t('asset.view.integrations', 'Integrations')}
                    </AccordionTrigger>
                    <AccordionContent>
                        <FileIntegrations asset={asset} file={rendition.file} />
                    </AccordionContent>
                </AccordionItem>
            ) : null}
        </Accordion>
    );
}
