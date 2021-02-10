import AbstractEdit from "../AbstractEdit";
import {getAsset} from "../../../api/asset";
import {Asset} from "../../../types";
import TagSelector from "../Tag/TagSelector";

export default class EditAsset extends AbstractEdit<Asset> {
    renderForm() {
        const data: Asset = this.state.data!;

        return <div>
            <TagSelector
                value={data.tags}
                workspaceId={data.workspace.id}
            />
        </div>;
    }

    async loadItem() {
        return await getAsset(this.props.id);
    }
}
