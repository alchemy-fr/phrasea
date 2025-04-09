import {Workspace} from '../../../types';
import {DataTabProps} from '../Tabbed/TabbedDialog';
import AclForm from '../../Acl/AclForm';
import ContentTab from '../Tabbed/ContentTab';
import {PermissionObject} from '../../Permissions/permissions';
import {AclPermission, aclPermissions} from '../../Acl/acl.ts';

type Props = DataTabProps<Workspace>;

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
                displayedPermissions={Object.keys(aclPermissions)
                    .filter(p => p !== AclPermission.SHARE)
                    .concat([AclPermission.ALL])}
            />
        </ContentTab>
    );
}
