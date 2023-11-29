import {useForm} from 'react-hook-form';

type Model = {
    id?: string | undefined;
    override?: boolean;
    name: string;
    rememberCollection: boolean;
    includeCollectionChildren: boolean;
    rememberPrivacy: boolean;
    rememberTags: boolean;
    rememberAttributes: boolean;
    public: boolean;
};

export function useAssetDataTemplateOptions() {
    const [saveAsTemplate, setSaveAsTemplate] = React.useState(false);

    const usedForm = useForm<Model>({
        defaultValues: {
            name: '',
            override: true,
            rememberCollection: true,
            includeCollectionChildren: true,
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
        };
    }, [usedForm, saveAsTemplate]);
}
