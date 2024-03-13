import {Basket} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import AclForm from '../../Acl/AclForm';
import ContentTab from '../Tabbed/ContentTab';
import {PermissionObject} from "../../Permissions/permissions";

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
