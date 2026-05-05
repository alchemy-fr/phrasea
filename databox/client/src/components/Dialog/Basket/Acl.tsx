import {Basket} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import AclForm from '../../Permissions/AclForm.tsx';
import ContentTab from '../Tabbed/ContentTab';
import {PermissionObject} from '../../Permissions/permissionsTypes.ts';

type Props = {
    data: Basket;
} & DialogTabProps;

export default function Acl({data, onClose, minHeight}: Props) {
    return (
        <ContentTab
            onClose={onClose}
            minHeight={minHeight}
            disableGutters={true}
        >
            <AclForm objectId={data.id} objectType={PermissionObject.Basket} />
        </ContentTab>
    );
}
