import {Workspace} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import AclForm from '../../Acl/AclForm';
import ContentTab from '../Tabbed/ContentTab';
import {PermissionObject} from '../../Permissions/permissions';
import {AclPermission, aclPermissions} from '../../Acl/acl.ts';

type Props = {
    data: Workspace;
} & DialogTabProps;

export default function Acl({data, onClose, minHeight}: Props) {
    return (
        <ContentTab
            onClose={onClose}
            minHeight={minHeight}
            disableGutters={true}
        >
            <AclForm
                objectId={data.id}
                objectType={PermissionObject.Workspace}
                displayedPermissions={Object.keys(aclPermissions).filter(
                    p => p !== AclPermission.SHARE
                )}
            />
        </ContentTab>
    );
}
