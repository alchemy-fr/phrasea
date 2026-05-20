import {Asset} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import AssetAclForm from './AssetAclForm.tsx';

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
            <AssetAclForm
                data={data}
                helper={true}
                workspaceInheritance={true}
            />
        </ContentTab>
    );
}
