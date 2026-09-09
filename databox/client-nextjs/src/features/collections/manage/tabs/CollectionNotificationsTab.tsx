'use client';

import {useTranslation} from 'react-i18next';
import {EntityName} from '@/types/api';
import type {CollectionTabProps} from '../CollectionManageRoute';
import {FollowButton} from '@/components/FollowButton';

export function CollectionNotificationsTab({collection}: CollectionTabProps) {
    const {t} = useTranslation();

    return (
        <div className="max-w-xl space-y-3">
            <p className="text-sm text-muted-foreground">
                {t(
                    'collection.notifications.help',
                    'Receive a notification when something happens in this collection.'
                )}
            </p>
            <FollowButton
                entity={EntityName.Collection}
                id={collection.id}
                size="default"
                subscriptions={collection.topicSubscriptions ?? []}
                topics={[
                    {
                        key: 'collection:asset_add',
                        label: t('collection.follow.asset_add', 'Asset added'),
                    },
                    {
                        key: 'collection:asset_remove',
                        label: t(
                            'collection.follow.asset_remove',
                            'Asset removed'
                        ),
                    },
                    {
                        key: 'collection:asset_update',
                        label: t(
                            'collection.follow.asset_update',
                            'Asset updated'
                        ),
                    },
                    {
                        key: 'collection:new_comment',
                        label: t(
                            'collection.follow.new_comment',
                            'New comment'
                        ),
                    },
                ]}
            />
        </div>
    );
}
