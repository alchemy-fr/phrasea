import {Collection} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import TagRules from '../../Media/TagFilterRule/TagRules';
import ContentTab from '../Tabbed/ContentTab';

type Props = {
    data: Collection;
} & DialogTabProps;

export default function TagRulesTab({data, onClose, minHeight}: Props) {
    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <TagRules
                id={data.id}
                workspaceId={data.workspace.id}
                type={'collection'}
            />
        </ContentTab>
    );
}
