import {Asset} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import AclForm from '../../Acl/AclForm';
import ContentTab from '../Tabbed/ContentTab';
import {PermissionObject} from '../../Permissions/permissions';
import {AclPermission, aclPermissions} from '../../Acl/acl.ts';

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
                displayedPermissions={
                    Object.entries(aclPermissions)
                        .filter(
                            ([key, value]) =>
                                value <
                                    aclPermissions[
                                        AclPermission.CHILD_CREATE
                                    ] &&
                                ![
                                    AclPermission.OPERATOR,
                                    AclPermission.UNDELETE,
                                    AclPermission.MASTER,
                                ].includes(key as AclPermission)
                        )
                        .map(([key]) => key)
                        .concat([AclPermission.ALL]) as AclPermission[]
                }
            />
        </ContentTab>
    );
}
