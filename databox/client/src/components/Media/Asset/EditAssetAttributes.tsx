import React, {PureComponent, RefObject} from "react";
import AbstractEdit, {AbstractEditProps} from "../AbstractEdit";
import {getAsset, getAssetAttributes, getWorkspaceAttributeDefinitions, patchAsset} from "../../../api/asset";
import {Asset, Attribute, AttributeDefinition} from "../../../types";
import TagSelect from "../Tag/TagSelect";
import {Field, Form, Formik} from "formik";
import {TextField} from "formik-material-ui";
import PrivacyField from "../../ui/PrivacyField";
import {InputLabel} from "@material-ui/core";
import Button from "../../ui/Button";
import Modal from "../../Layout/Modal";
import AttributeRow from "./AttributeRow";

type Props = {
    id: string;
    workspaceId: string;
    onClose: () => void;
};

type State = {
    attributeDefinitions: AttributeDefinition[] | undefined;
    attributes: Attribute[] | undefined;
}

type AttrValue = {
    id: string;
    value: any;
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

        console.log('attributeDefinitions', attributeDefinitions);

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

        console.log('attributeDefinitions', attributeDefinitions);

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

            console.log('value', value);

            if (ad.multiple) {
                return (value as AttrValue[]).map(v => <AttributeRow
                    id={v.id}
                    assetId={this.props.id}
                    name={ad.name}
                    valueId={v.id}
                    key={v.id}
                    type={ad.type}
                    value={v.value}
                />)
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
