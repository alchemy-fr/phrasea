import {Asset} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import AclForm from '../../Permissions/AclForm.tsx';
import ContentTab from '../Tabbed/ContentTab';
import {
    AclPermission,
    aclPermissions,
    PermissionObject,
} from '../../Permissions/permissionsTypes.ts';

type Props = {
    data: Asset;
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
                objectType={PermissionObject.Asset}
                filterDefinitions={({value, key}) =>
                    value < aclPermissions[AclPermission.CHILD_CREATE] &&
                    ![
                        AclPermission.OPERATOR,
                        AclPermission.UNDELETE,
                        AclPermission.MASTER,
                    ].includes(key as AclPermission)
                }
            />
        </ContentTab>
    );
}
