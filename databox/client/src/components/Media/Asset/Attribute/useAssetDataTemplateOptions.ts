import React, {useCallback, useEffect, useState} from "react";
import {
    AttributeIndex,
    AttrValue,
    DefinitionIndex,
    NO_LOCALE,
    OnChangeHandler
} from "./AttributesEditor";
import {Attribute, AttributeDefinition} from "../../../../types";
import {getWorkspaceAttributeDefinitions} from "../../../../api/attributes";
import {getAssetAttributes} from "../../../../api/asset";
import {getBatchActions} from "./BatchActions";
import {useForm} from "react-hook-form";

type Model = {
    name: string;
    rememberPrivacy: boolean;
    rememberTags: boolean;
    rememberAttributes: boolean;
    public: boolean;
}
export function useAssetDataTemplateOptions() {
    const [saveAsTemplate, setSaveAsTemplate] = React.useState(false);

    const usedForm = useForm<Model>({
        defaultValues: {
            name: '',
            rememberAttributes: true,
            rememberPrivacy: true,
            rememberTags: true,
            public: false,
        },
    });

    return React.useMemo(() => {
        return {
            saveAsTemplate,
            setSaveAsTemplate,
            usedForm,
        }
    }, [usedForm, saveAsTemplate]);
}
