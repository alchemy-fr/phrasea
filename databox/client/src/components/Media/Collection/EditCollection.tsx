import AbstractEdit from "../AbstractEdit";
import {Collection} from "../../../types";
import {getCollection} from "../../../api/collection";
import TagFilterRules from "../TagFilterRule/TagFilterRules";

export default class EditCollection extends AbstractEdit<Collection> {
    async loadItem() {
        return await getCollection(this.props.id);
    }

    getType(): string {
        return 'collection';
    }

    getTitle(): string | null {
        const d = this.getData();
        return d ? d.title : null;
    }

    renderForm(): React.ReactNode {
        return <div>
            <h4>Tag filter rules</h4>
            <TagFilterRules
                id={this.props.id}
                workspaceId={this.getData()!.workspace.id}
                type={'collection'}
            />
        </div>;
    }

    handleSave(): Promise<boolean> {
        return Promise.resolve(false);
    }
}
