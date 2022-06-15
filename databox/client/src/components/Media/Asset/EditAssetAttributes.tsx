import React, {useEffect, useState} from 'react';
import {getAssetAttributes, getWorkspaceAttributeDefinitions} from "../../../api/asset";
import {Asset, Attribute, AttributeDefinition} from "../../../types";
import AttributesEditor, {AttributeIndex, buildAttributeIndex, DefinitionIndex} from "./Attribute/AttributesEditor";
import FullPageLoader from "../../Ui/FullPageLoader";
import {StackedModalProps} from "@mattjennings/react-modal-stack/src/ModalStack";
import {useModals} from "@mattjennings/react-modal-stack";
import {useModalHash} from "../../../hooks/useModalHash";

export const NO_LOCALE = '_';

type Props = {
    asset: Asset;
    onEdit: () => void;
} & StackedModalProps;

export default function EditAssetAttributes({
                                                asset,
                                                onEdit,
                                            }: Props) {
    const {closeModal} = useModalHash();
    const [state, setState] = useState<{
        attributeIndex: AttributeIndex;
        definitionIndex: DefinitionIndex;
    }>();

    const load = async () => {
        const [
            definitions,
            attributes,
        ]: [AttributeDefinition[], Attribute[]] = await Promise.all([
            getWorkspaceAttributeDefinitions(asset.workspace.id),
            getAssetAttributes(asset.id),
        ]);

        const definitionIndex: DefinitionIndex = {};
        for (let ad of definitions) {
            definitionIndex[ad.id] = ad;
        }

        setState({
            definitionIndex,
            attributeIndex: buildAttributeIndex(definitionIndex, attributes),
        });
    }

    useEffect(() => {
        load();
    }, [asset.id]);

    if (!state) {
        return <FullPageLoader/>
    }

    return <AttributesEditor
        attributes={state.attributeIndex}
        definitions={state.definitionIndex}
        assetId={asset.id}
        onClose={closeModal}
        onEdit={onEdit}
    />
}
