import {FormLabel, Skeleton} from '@mui/material';
import {attributeBatchUpdate} from '../../../../api/asset';
import {Asset} from '../../../../types';
import {toast} from 'react-toastify';
import FormTab from '../../../Dialog/Tabbed/FormTab';
import AttributesEditor from './AttributesEditor';
import {useAttributeEditor} from './useAttributeEditor';
import {FormRow} from '@alchemy/react-form';
import React from 'react';
import {WorkspaceContext} from '../../../../context/WorkspaceContext.tsx';
import { useTranslation } from 'react-i18next';

type Props = {
    workspaceId: string;
    assetId: string | string[];
    multiAssets?: Asset[];
    onClose: () => void;
    minHeight?: number | undefined;
};

export default function AttributesEditorForm({
    workspaceId,
    assetId,
    onClose,
    minHeight,
}: Props) {
    const {t} = useTranslation();
    const {
        getActions,
        onChangeHandler,
        attributes,
        definitionIndex,
        reloadAssetAttributes,
    } = useAttributeEditor({
        workspaceId,
        assetId: assetId as string,
    });
    const [saving, setSaving] = React.useState(false);
    const [error, setError] = React.useState<string | undefined>();

    const onSave = React.useCallback(async () => {
        setSaving(true);

        const actions = getActions();
        try {
            if (actions.length > 0) {
                await attributeBatchUpdate(assetId, actions);
            }
            await reloadAssetAttributes(assetId as string);

            toast.success(t('attributes_editor_form.attributes_saved', `Attributes saved!`), {});

            setSaving(false);

            if (error) {
                setError(undefined);
            }
        } catch (e: any) {
            console.error('e', e);
            setSaving(false);
            if (e.response && typeof e.response.data === 'object') {
                const data = e.response.data;
                setError(
                    `${data['hydra:title']}: ${data['hydra:description']}`
                );
            } else {
                setError(e.toString());
            }
        }
    }, [getActions]);

    return (
        <>
            <WorkspaceContext.Provider
                value={{
                    workspaceId,
                }}
            >
                <FormTab
                    formId={'a'}
                    onSave={onSave}
                    onClose={onClose}
                    minHeight={minHeight}
                    loading={saving}
                >
                    {attributes && definitionIndex ? (
                        <AttributesEditor
                            attributes={attributes}
                            definitions={definitionIndex}
                            disabled={saving}
                            onChangeHandler={onChangeHandler}
                        />
                    ) : (
                        <>
                            {[0, 1, 2].map(x => (
                                <React.Fragment key={x}>
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
                                </React.Fragment>
                            ))}
                        </>
                    )}
                </FormTab>
            </WorkspaceContext.Provider>
        </>
    );
}
