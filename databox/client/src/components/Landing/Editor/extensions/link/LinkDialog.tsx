import {AppDialog} from '@alchemy/phrasea-ui';
import {
    Button,
    FormControlLabel,
    InputLabel,
    Radio,
    RadioGroup,
    TextField,
} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {Editor} from '@tiptap/core';
import {LinkAttributes} from './types.ts';
import {useFormSubmit} from '@alchemy/api';
import {Controller} from 'react-hook-form';
import {FormRow} from '@alchemy/react-form';
import LinkOffIcon from '@mui/icons-material/LinkOff';

type Props = {
    editor: Editor;
    currentLinkSpec?: LinkAttributes | null;
} & StackedModalProps;

export default function LinkDialog({open, editor, currentLinkSpec}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const {handleSubmit, register, control} = useFormSubmit<LinkAttributes>({
        defaultValues: {
            href: currentLinkSpec?.href || '',
            target: currentLinkSpec?.target || '_blank',
            title: currentLinkSpec?.title || '',
        },
        onSubmit: async data => {
            editor.chain().focus().setLink(data).run();

            return data;
        },
        onSuccess: () => {
            closeModal();
        },
    });

    const formId = 'link-form';

    return (
        <AppDialog
            open={open}
            title={t('editor.link.dialog.title.label', 'Insert Link')}
            onClose={() => closeModal()}
            actions={({onClose}) => {
                return (
                    <>
                        <Button
                            onClick={() => {
                                editor.chain().focus().unsetLink().run();
                                closeModal();
                            }}
                            startIcon={<LinkOffIcon />}
                            color={'error'}
                            sx={{mr: 3}}
                        >
                            {t(
                                'editor.link.dialog.remove.label',
                                'Remove Link'
                            )}
                        </Button>
                        <Button onClick={() => onClose()}>
                            {t('common.cancel', 'Cancel')}
                        </Button>
                        <Button
                            type={'submit'}
                            form={formId}
                            variant="contained"
                        >
                            {t('common.confirm', 'Confirm')}
                        </Button>
                    </>
                );
            }}
        >
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <TextField
                        fullWidth
                        label={t('editor.link.dialog.url.label', 'URL')}
                        {...register('href', {required: true})}
                    />
                </FormRow>
                <FormRow>
                    <TextField
                        fullWidth
                        label={t(
                            'editor.link.dialog.title.label',
                            'Title (optional)'
                        )}
                        {...register('title')}
                    />
                </FormRow>
                <FormRow>
                    <InputLabel>
                        {t('editor.link.dialog.target.label', 'Open link in')}
                    </InputLabel>
                    <Controller
                        render={({field: {value, onChange}}) => {
                            return (
                                <RadioGroup
                                    name="target"
                                    value={value}
                                    onChange={e => {
                                        onChange(e.target.value);
                                    }}
                                    sx={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                    }}
                                >
                                    <FormControlLabel
                                        value={'_blank'}
                                        control={<Radio />}
                                        label={t(
                                            'editor.link.dialog.target.blank.label',
                                            'New tab'
                                        )}
                                    />
                                    <FormControlLabel
                                        value={'_self'}
                                        control={<Radio />}
                                        label={t(
                                            'editor.link.dialog.target.self.label',
                                            'Same tab'
                                        )}
                                    />
                                </RadioGroup>
                            );
                        }}
                        name={'target'}
                        control={control}
                    />
                </FormRow>
            </form>
        </AppDialog>
    );
}
