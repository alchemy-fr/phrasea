import {Collection} from '../../../types';
import {DataTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import CollectionAclForm from './CollectionAclForm.tsx';

type Props = DataTabProps<Collection>;

export default function Acl({data, onClose, minHeight}: Props) {
    return (
        <ContentTab
            onClose={onClose}
            minHeight={minHeight}
            disableGutters={true}
        >
            <CollectionAclForm
                data={data}
                workspaceInheritance={true}
                helper={true}
            />
        </ContentTab>
    );
}
