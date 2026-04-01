import {Workspace} from '../../../types';
import {DataTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import WorkspaceAclForm from './WorkspaceAclForm.tsx';

type Props = DataTabProps<Workspace>;

export default function Acl({data, onClose, minHeight}: Props) {
    return (
        <ContentTab
            onClose={onClose}
            minHeight={minHeight}
            disableGutters={true}
        >
            <WorkspaceAclForm data={data} helper={true} />
        </ContentTab>
    );
}
