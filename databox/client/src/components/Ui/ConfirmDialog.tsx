import React, {PropsWithChildren, ReactNode, useState} from 'react';
import {Button, Checkbox, FormControlLabel, TextField} from '@mui/material';
import {useTranslation} from 'react-i18next';
import CheckIcon from '@mui/icons-material/Check';
import {LoadingButton, LoadingButtonProps} from '@mui/lab';
import {AxiosError} from 'axios';
import {AppDialog} from '@alchemy/phrasea-ui';
import type {AppDialogProps} from '@alchemy/phrasea-ui';
import RemoteErrors from '../Form/RemoteErrors';
import {StackedModalProps, useModals} from '@alchemy/navigation';

type ConfirmOptions = {[key: string]: ReactNode};
type ConfirmOptionValues<CO extends ConfirmOptions> = {
    [key in keyof CO]: boolean;
};

type Props<CO extends ConfirmOptions> = PropsWithChildren<
    {
        onCancel?: (() => void) | undefined;
        onConfirm: (options: ConfirmOptionValues<CO>) => Promise<void | false>;
        onConfirmed?: () => void;
        title?: ReactNode;
        options?: CO;
        confirmLabel?: ReactNode;
        disabled?: boolean;
        textToType?: string | undefined;
        assertions?: ReactNode[] | undefined;
        confirmButtonProps?: LoadingButtonProps | undefined;
        maxWidth?: AppDialogProps['maxWidth'];
    } & StackedModalProps
>;

export type {Props as ConfirmDialogProps};

export default function ConfirmDialog<CO extends ConfirmOptions>({
    onCancel,
    onConfirm,
    modalIndex,
    onConfirmed,
    title,
    maxWidth = 'sm',
    confirmLabel,
    disabled,
    open,
    options = {} as CO,
    textToType,
    assertions,
    children,
    confirmButtonProps,
}: Props<CO>) {
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
            closeModal(true);
            onConfirmed && onConfirmed();
        } catch (e: any) {
            if (e.isAxiosError) {
                const err = e as AxiosError;
                if (
                    err.response &&
                    [400, 500, 404].includes(err.response.status)
                ) {
                    setErrors(p =>
                        p.concat(
                            (err.response!.data as any)[
                                'hydra:description'
                            ] as string
                        )
                    );
                }
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <AppDialog
            maxWidth={maxWidth}
            onClose={onCancel ?? closeModal}
            loading={loading}
            title={title}
            open={open}
            modalIndex={modalIndex}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose} disabled={loading}>
                        {t('dialog.cancel', 'Cancel')}
                    </Button>
                    <LoadingButton
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
                        {confirmLabel || t('dialog.confirm', 'Confirm')}
                    </LoadingButton>
                </>
            )}
        >
            {textToType && (
                <div>
                    {t(
                        'dialog.confirm_text_type.intro',
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
                                'dialog.confirm_text_type.placeholder',
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
