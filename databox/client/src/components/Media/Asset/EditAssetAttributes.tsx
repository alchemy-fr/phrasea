import React, {PureComponent} from "react";
import {getAssetAttributes, getWorkspaceAttributeDefinitions} from "../../../api/asset";
import {Attribute, AttributeDefinition} from "../../../types";
import AttributesEditor, {
    AttributeIndex,
    AttrValue,
    buildAttributeIndex,
    DefinitionIndex
} from "./Attribute/AttributesEditor";

type Props = {
    id: string;
    workspaceId: string;
    onClose: () => void;
};

type State = {
    attributeIndex?: AttributeIndex;
    definitionIndex?: DefinitionIndex;
}

export const NO_LOCALE = '_';

export default class EditAssetAttributes extends PureComponent<Props, State> {
    state: State = {};

    componentDidMount() {
        this.loadItem();
    }

    async loadItem() {
        const [
            definitions,
            attributes,
        ]: [AttributeDefinition[], Attribute[]] = await Promise.all([
            getWorkspaceAttributeDefinitions(this.props.workspaceId),
            getAssetAttributes(this.props.id),
        ]);

        const definitionIndex: DefinitionIndex = {};
        for (let ad of definitions) {
            definitionIndex[ad.id] = ad;
        }

        this.setState({
            definitionIndex,
            attributeIndex: buildAttributeIndex(definitionIndex, attributes),
        });
    }

    render() {
        if (!this.state.attributeIndex || !this.state.definitionIndex) {
            return 'Loading';
        }

        return <AttributesEditor
                attributes={this.state.attributeIndex}
                definitions={this.state.definitionIndex}
                assetId={this.props.id}
                onClose={this.props.onClose}
            />
    }
}
