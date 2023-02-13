import React, {useEffect, useState} from 'react';
import {Asset, Attribute, AttributeDefinition} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import ContentTab from "../Tabbed/ContentTab";
import AttributesEditor, {
    AttributeIndex,
    buildAttributeIndex,
    DefinitionIndex
} from "../../Media/Asset/Attribute/AttributesEditor";
import {getWorkspaceAttributeDefinitions} from "../../../api/attributes";
import {getAssetAttributes} from "../../../api/asset";
import {FormLabel, Skeleton} from "@mui/material";
import FormRow from "../../Form/FormRow";

type Props = {
    data: Asset;
} & DialogTabProps;

export default function EditAttributes({
    data,
    onClose,
    minHeight,
}: Props) {
    const [state, setState] = useState<{
        attributeIndex: AttributeIndex;
        definitionIndex: DefinitionIndex;
    }>();

    useEffect(() => {
        (async () => {
            const [
                definitions,
                attributes,
            ]: [AttributeDefinition[], Attribute[]] = await Promise.all([
                getWorkspaceAttributeDefinitions(data.workspace.id),
                getAssetAttributes(data.id),
            ]);

            const definitionIndex: DefinitionIndex = {};
            for (let ad of definitions) {
                definitionIndex[ad.id] = ad;
            }

            setState({
                definitionIndex,
                attributeIndex: buildAttributeIndex(definitionIndex, attributes),
            });
        })();
    }, [data.id]);

    return state ? <AttributesEditor
        attributes={state.attributeIndex}
        definitions={state.definitionIndex}
        assetId={data.id}
        onClose={onClose}
        minHeight={minHeight}
        onEdit={() => {
        }}
    /> : <ContentTab
        minHeight={minHeight}
        onClose={onClose}
    >
        {[0, 1, 2].map(x => <React.Fragment key={x}>
            <FormRow>
                <FormLabel>
                    <Skeleton
                        width={'200'}
                        variant={'text'}
                        style={{
                            display: 'inline-block',
                            width: '200px',
                        }}
                    />
                </FormLabel>
                <Skeleton
                    width={'100%'}
                    height={56}
                    variant={'rectangular'}
                    sx={{
                        mb: 2,
                    }}
                />
            </FormRow>
        </React.Fragment>)}
    </ContentTab>
}
