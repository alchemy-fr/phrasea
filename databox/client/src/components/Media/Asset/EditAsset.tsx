import React, {Ref, RefObject} from "react";
import AbstractEdit, {AbstractEditProps} from "../AbstractEdit";
import {getAsset, patchAsset} from "../../../api/asset";
import {Asset} from "../../../types";
import TagSelect from "../Tag/TagSelect";

export default class EditAsset extends AbstractEdit<Asset> {
    private readonly tagRef: RefObject<TagSelect>;

    constructor(props: Readonly<AbstractEditProps>) {
        super(props);

        this.tagRef = React.createRef<TagSelect>();
    }

    renderForm() {
        const data: Asset = this.state.data!;

        return <div>
            <TagSelect
                ref={this.tagRef}
                value={data.tags}
                workspaceId={data.workspace.id}
            />
        </div>;
    }

    async loadItem() {
        return await getAsset(this.props.id);
    }

    async handleSave(): Promise<boolean> {
        await patchAsset(this.props.id, {
            tags: this.tagRef!.current!.getData(),
        });

        return true;
    }

}
