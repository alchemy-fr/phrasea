import {Collection} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {useTranslation} from 'react-i18next';
import FollowButton from "../../Ui/FollowButton.tsx";

type Props = {
    data: Collection;
} & DialogTabProps;

export default function CollectionNotifications({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <FollowButton
                entity={'collections'}
                id={data.id}
                topics={[
                    {
                        key: `collection:${data.id}:asset_add`,
                        label: t('notification.topics.collection.asset_add.label', 'Asset added'),
                        description:
                            t('notification.topics.collection.asset_add.desc', 'Get notified when an asset is added to the collection'),
                    },
                    {
                        key: `collection:${data.id}:asset_removed`,
                        label: t('notification.topics.collection.asset_removed.label', 'Asset removed'),
                        description:
                            t('notification.topics.collection.asset_removed.desc', 'Get notified when an asset is removed from the collection'),
                    },
                    {
                        key: `collection:${data.id}:asset_new_comment`,
                        label: t('notification.topics.collection.asset_new_comment.label', 'Discussion'),
                        description:
                            t('notification.topics.collection.asset_new_comment.desc', 'Get notified when there is a new comment on an asset in this collection'),
                    },
                    {
                        key: `collection:${data.id}:asset_update`,
                        label: t('notification.topics.collection.asset_update.label', 'Asset updated'),
                        description:
                            t('notification.topics.collection.asset_update.desc', 'Get notified when an asset in this collection is updated'),
                    },
                ]} subscriptions={data.topicSubscriptions}/>
        </ContentTab>
    );
}
