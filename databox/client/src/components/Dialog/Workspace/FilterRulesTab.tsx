import {Workspace} from '../../../types';
import {DataTabProps} from '../Tabbed/TabbedDialog';
import AttributeFilterRules from '../../Media/AttributeFilterRule/AttributeFilterRules';
import ContentTab from '../Tabbed/ContentTab';

type Props = DataTabProps<Workspace>;

export default function FilterRulesTab({data, onClose, minHeight}: Props) {
    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <AttributeFilterRules workspaceId={data.id} />
        </ContentTab>
    );
}
