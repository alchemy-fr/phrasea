import React, {Ref, RefObject} from "react";
import AbstractEdit, {AbstractEditProps} from "../AbstractEdit";
import {getAsset, patchAsset} from "../../../api/asset";
import {Asset} from "../../../types";
import TagSelect from "../Tag/TagSelect";
import {ApiHydraObjectResponse} from "../../../api/hydra";

export default class EditAsset extends AbstractEdit<Asset> {
    private readonly tagRef: RefObject<TagSelect>;

    constructor(props: Readonly<AbstractEditProps>) {
        super(props);

        this.tagRef = React.createRef<TagSelect>();
    }

    getType(): string {
        return 'asset';
    }

    getTitle(): string | null {
        const d = this.getData();
        return d ? d.title : null;
    }

    renderForm() {
        const data: Asset = this.state.data!;

        return <div>
            <h4>Tags</h4>
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
            tags: this.tagRef!.current!.getData().map(t => t['@id']),
        });

        return true;
    }
}
