import {Collection} from '../../../types';
import {DataTabProps} from '../Tabbed/TabbedDialog';
import AclForm from '../../Acl/AclForm';
import ContentTab from '../Tabbed/ContentTab';
import {PermissionObject} from '../../Permissions/permissions';
import {AclPermission} from '../../Acl/acl.ts';
import {useTranslation} from 'react-i18next';

type Props = DataTabProps<Collection>;

export default function Acl({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
    return (
        <ContentTab
            onClose={onClose}
            minHeight={minHeight}
            disableGutters={true}
        >
            <AclForm
                objectId={data.id}
                objectType={PermissionObject.Collection}
                permissionHelper={{
                    [AclPermission.EDIT]: {
                        label: t('acl.permission.manage_collection', 'Manage'),
                    },
                    [AclPermission.OPERATOR]: {
                        label: AclPermission.EDIT,
                    },
                }}
            />
        </ContentTab>
    );
}
