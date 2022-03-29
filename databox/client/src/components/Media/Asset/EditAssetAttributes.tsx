import React, {PureComponent} from "react";
import {getAssetAttributes, getWorkspaceAttributeDefinitions} from "../../../api/asset";
import {Attribute, AttributeDefinition} from "../../../types";
import Button from "../../ui/Button";
import Modal from "../../Layout/Modal";
import AttributeRow from "./Attribute/AttributeRow";
import MultiAttributeRow, {AttrValue} from "./Attribute/MultiAttributeRow";

type Props = {
    id: string;
    workspaceId: string;
    onClose: () => void;
};

type State = {
    attributeDefinitions: AttributeDefinition[] | undefined;
    attributes: Attribute[] | undefined;
}

export default class EditAssetAttributes extends PureComponent<Props, State> {
    state: State = {
        attributeDefinitions: undefined,
        attributes: undefined,
    };

    componentDidMount() {
        this.loadItem();
    }

    async loadItem() {
        const [
            attributeDefinitions,
            attributes,
        ] = await Promise.all([
            getWorkspaceAttributeDefinitions(this.props.workspaceId),
            getAssetAttributes(this.props.id),
        ]);

        this.setState({
            attributeDefinitions,
            attributes,
        });
    }

    renderContent() {
        const {attributes, attributeDefinitions} = this.state;

        if (!attributeDefinitions || !attributes) {
            return <div>Loading...</div>
        }

        const definitions: Record<string, AttributeDefinition> = {};
        attributeDefinitions.forEach(ad => {
            definitions[ad.id] = ad;
        });

        const values: Record<string, AttrValue | AttrValue[] | undefined> = {};
        for (let ad of attributeDefinitions) {
            values[ad.id] = undefined;
        }
        for (let a of attributes) {
            const v = {
                id: a.id,
                value: a.value,
            };
            if (definitions[a.definition.id].multiple) {
                if (!values[a.definition.id]) {
                    values[a.definition.id] = [];
                }
                (values[a.definition.id]! as AttrValue[]).push(v);
            } else {
                values[a.definition.id] = v;
            }
        }

        return attributeDefinitions.map(ad => {
            const value = values[ad.id] || (ad.multiple ? [] : undefined);

            if (ad.multiple) {
                return <MultiAttributeRow
                    id={ad.id}
                    assetId={this.props.id}
                    key={ad.id}
                    type={ad.type}
                    name={ad.name}
                    values={value as AttrValue[]}
                />
            }

            const v = value as AttrValue;

            return <AttributeRow
                id={ad.id}
                assetId={this.props.id}
                key={ad.id}
                type={ad.type}
                name={ad.name}
                value={v ? v.value : undefined}
                valueId={v ? v.id : undefined}
            />
        })
    }

    render() {
        return <Modal
            onClose={this.props.onClose}
            header={() => <div>Attributes</div>}
            footer={({onClose}) => <>
                <Button
                    onClick={onClose}
                    className={'btn-secondary'}
                >
                    Close
                </Button>
            </>}
        >
            {this.renderContent()}
        </Modal>
    }
}
