import React, {PureComponent} from "react";
import {getAssetAttributes, getWorkspaceAttributeDefinitions} from "../../../api/asset";
import {Attribute, AttributeDefinition} from "../../../types";
import AttributesEditor, {AttributeIndex, AttrValue, DefinitionIndex} from "./Attribute/AttributesEditor";

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

        const attributeIndex: AttributeIndex = {};
        const definitionIndex: DefinitionIndex = {};
        for (let ad of definitions) {
            attributeIndex[ad.id] = {};
            definitionIndex[ad.id] = ad;
        }

        for (let a of attributes) {
            const l = a.locale || NO_LOCALE;
            const v = {
                id: a.id,
                value: a.value,
            };

            if (!attributeIndex[a.definition.id]) {
                attributeIndex[a.definition.id] = {};
            }

            if (definitionIndex[a.definition.id].multiple) {
                if (!attributeIndex[a.definition.id][l]) {
                    attributeIndex[a.definition.id][l] = [];
                }
                (attributeIndex[a.definition.id][l]! as AttrValue[]).push(v);
            } else {

                attributeIndex[a.definition.id][l] = v;
            }
        }

        this.setState({
            definitionIndex,
            attributeIndex,
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
