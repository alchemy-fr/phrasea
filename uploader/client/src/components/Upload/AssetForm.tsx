import {FormSchema, LiFormSchema} from '../../types.ts';
import React, {useEffect, useState} from 'react';
import {getFormSchema} from '../../api/targetApi.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import AssetLiForm from './AssetLiForm.tsx';
import {apiClient} from '../../init.ts';
import {OnSubmitForm} from './UploadStepper.tsx';
import {getAxiosError} from '@alchemy/api';
import {toast} from 'react-toastify';
import {RemoteErrors} from '@alchemy/react-form';

type Props = {
    targetId: string;
    submitPath: string;
    formDataKey?: string;
    onComplete: OnSubmitForm;
    onCancel: () => void;
    baseSchema?: LiFormSchema;
};

export default function AssetForm({
    targetId,
    submitPath,
    formDataKey,
    onComplete,
    onCancel,
    baseSchema,
}: Props) {
    const [schema, setSchema] = useState<FormSchema>();
    const [submitting, setSubmitting] = React.useState(false);
    const [schemaLoaded, setSchemaLoaded] = React.useState(false);
    const [errors, setErrors] = React.useState<string[] | undefined>();

    useEffect(() => {
        (async () => {
            try {
                const fetchedSchema = await getFormSchema(targetId);
                setSchemaLoaded(true);
                if (fetchedSchema) {
                    if (baseSchema) {
                        if (baseSchema.required) {
                            fetchedSchema.data.required = [
                                ...baseSchema.required,
                                ...(fetchedSchema.data.required || []),
                            ];
                        }

                        if (baseSchema.properties) {
                            fetchedSchema.data.properties = {
                                ...baseSchema.properties,
                                ...(fetchedSchema.data.properties || {}),
                            };
                        }
                    }

                    setSchema(fetchedSchema);
                } else if (!baseSchema) {
                    onComplete({}, undefined);
                }
            } catch (e: any) {
                const error = getAxiosError(e);
                if (error) {
                    if (error.code === 404) {
                        setSchemaLoaded(true);
                        return;
                    }
                    toast.error(error.message);
                } else {
                    toast.error(
                        'An unknown error occurred while fetching the form schema.'
                    );
                }
                throw e;
            }
        })();
    }, [targetId, baseSchema]);

    if (!schemaLoaded) {
        return <FullPageLoader backdrop={false} />;
    }

    const onSubmit = async (submittedData: Record<string, any>) => {
        const formData = {...submittedData};

        const data: Record<string, any> = {
            target: `/targets/${targetId}`,
        };
        if (baseSchema?.properties) {
            Object.keys(baseSchema.properties).forEach(key => {
                if (Object.prototype.hasOwnProperty.call(formData, key)) {
                    data[key] = formData[key];
                    delete formData[key];
                }
            });
        }

        data[formDataKey ?? 'formData'] = formData;

        setSubmitting(true);
        try {
            await apiClient.post(submitPath, {
                ...data,
            });

            onComplete?.(formData, schema?.id);
        } catch (e) {
            setSubmitting(false);
            console.debug(e);
            const error = getAxiosError(e);
            if (error) {
                if (error) {
                    setErrors([error.message]);
                    return;
                }
            }

            throw e;
        }
    };

    if (!schema && !baseSchema) {
        return null;
    }

    return (
        <>
            {submitting && <FullPageLoader backdrop={true} />}
            <AssetLiForm
                schema={(schema?.data || baseSchema)!}
                onSubmit={onSubmit}
                onCancel={onCancel}
            />
            {errors && <RemoteErrors errors={errors} />}
        </>
    );
}
