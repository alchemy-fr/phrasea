import React, {useState} from 'react';
import {Button, Checkbox, FormControlLabel, TextField} from '@mui/material';
import {useTranslation} from 'react-i18next';
import CheckIcon from '@mui/icons-material/Check';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useModals} from '@alchemy/navigation';
import {ConfirmDialogProps, ConfirmOptions, ConfirmOptionValues} from './types';
import {RemoteErrors} from '@alchemy/react-form';
import {getAxiosError} from '@alchemy/api';

export default function ConfirmDialog<CO extends ConfirmOptions>({
    onCancel,
    onConfirm,
    onConfirmed,
    title,
    maxWidth = 'sm',
    confirmLabel,
    disabled,
    options = {} as CO,
    textToType,
    assertions,
    children,
    confirmButtonProps,
    ...modalProps
}: ConfirmDialogProps<CO>) {
    const {closeModal} = useModals();
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);
    const {t} = useTranslation();
    const [confirmValue, setConfirmValue] = useState('');

    const [checks, setChecks] = React.useState<boolean[]>(
        assertions ? assertions.map(() => false) : []
    );

    const defaultOptionValues: Record<string, boolean> = {};
    Object.keys(options).map(k => {
        defaultOptionValues[k] = false;
    });
    const [optionValues, setOptionValue] = React.useState<
        ConfirmOptionValues<CO>
    >(defaultOptionValues as ConfirmOptionValues<CO>);

    const submittable = !assertions || !assertions.some((_a, i) => !checks![i]);

    const onChangeCheck = React.useCallback(
        (index: number, checked: boolean) => {
            setChecks(p => p.map((c, i) => (i === index ? checked : c)));
        },
        []
    );

    const onOptionCheck = React.useCallback(
        (key: keyof CO, checked: boolean) => {
            setOptionValue(p => {
                const np = {...p};
                np[key] = checked;

                return np;
            });
        },
        []
    );

    const confirm = async () => {
        setLoading(true);
        setErrors([]);
        try {
            const r = await onConfirm(optionValues);
            if (false === r) {
                return;
            }
            closeModal({force: true});
            onConfirmed?.();
        } catch (e: any) {
            const error = getAxiosError(e);
            if (error) {
                if ([400, 500, 404].includes(error.code)) {
                    setErrors(p => p.concat(error.message));
                }
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <AppDialog
            {...modalProps}
            maxWidth={maxWidth}
            onClose={
                onCancel ?? (() => closeModal({modalId: modalProps.modalId}))
            }
            loading={loading}
            title={title}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose} disabled={loading}>
                        {t('framework.dialog.cancel', 'Cancel')}
                    </Button>
                    <Button
                        loading={loading}
                        startIcon={<CheckIcon />}
                        onClick={confirm}
                        color={'primary'}
                        variant={'contained'}
                        disabled={
                            !submittable ||
                            disabled ||
                            (textToType ? textToType !== confirmValue : false)
                        }
                        {...(confirmButtonProps || {})}
                    >
                        {confirmLabel ||
                            t('framework.dialog.confirm', 'Confirm')}
                    </Button>
                </>
            )}
        >
            {textToType && (
                <div>
                    {t(
                        'framework.dialog.confirm_text_type.intro',
                        'Please type "{{ text }}" to confirm:',
                        {
                            text: textToType,
                        }
                    )}
                    <div>
                        <TextField
                            disabled={loading}
                            value={confirmValue}
                            onChange={e => setConfirmValue(e.target.value)}
                            placeholder={t(
                                'framework.dialog.confirm_text_type.placeholder',
                                'Type "{{ text }}"',
                                {
                                    text: textToType,
                                }
                            )}
                        />
                    </div>
                </div>
            )}
            {assertions && (
                <div data-testid="assertions">
                    {assertions.map((a, i) => (
                        <div key={i}>
                            <FormControlLabel
                                sx={{
                                    my: 1,
                                }}
                                checked={checks[i]}
                                onChange={(_e, checked) =>
                                    onChangeCheck(i, checked)
                                }
                                label={a}
                                control={<Checkbox sx={{mr: 1}} />}
                            />
                        </div>
                    ))}
                </div>
            )}
            {Object.keys(options).length > 0 && (
                <div data-testid="options">
                    {Object.keys(options).map(k => (
                        <div key={k}>
                            <FormControlLabel
                                sx={{
                                    my: 1,
                                }}
                                checked={optionValues[k]}
                                onChange={(_e, checked) =>
                                    onOptionCheck(k, checked)
                                }
                                label={options[k]}
                                control={<Checkbox sx={{mr: 1}} />}
                            />
                        </div>
                    ))}
                </div>
            )}
            {children}
            <RemoteErrors errors={errors} />
        </AppDialog>
    );
}
