import {PropsWithChildren, useCallback} from 'react';
import {Badge, Button, Stack, Tooltip} from '@mui/material';
import {EmojiFlags} from '@mui/icons-material';
import {useModals} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import FieldTranslationsEditDialog, {
    FieldTranslationsEditDialogProps,
} from './FieldTranslationsEditDialog';
import {getFieldTranslationCount} from "./localeHelper";
import {WithTranslations} from "../types";

type Props<T extends WithTranslations> = {
    getData: (() => T) | undefined;
    onUpdate: FieldTranslationsEditDialogProps<T>['onUpdate'] | undefined;
} & Omit<
    PropsWithChildren<FieldTranslationsEditDialogProps<T>>,
    'getData' | 'onUpdate'
>;

export default function TranslatedField<T extends WithTranslations>({
    children,
    field,
    getData,
    onUpdate,
    title,
    inputProps,
    noToast,
}: Props<T>) {
    const {openModal} = useModals();
    const {t} = useTranslation();

    const openTitleTranslations = useCallback(async () => {
        openModal(FieldTranslationsEditDialog, {
            getData,
            onUpdate: onUpdate!,
            field,
            title,
            noToast,
            inputProps,
        } as any);
    }, [getData, field, title, onUpdate, inputProps, noToast]);

    if (!getData) {
        return <>{children}</>;
    }

    return (
        <Stack direction={'row'}>
            <div style={{
                flexGrow: 1,
            }}>
                {children}
            </div>
            <Tooltip
                title={t(
                    'lib.form.translations.tooltip',
                    'Manage translations',
                )}
            >
                <Button onClick={openTitleTranslations}>
                    <Badge
                        badgeContent={getFieldTranslationCount(
                            getData()?.translations,
                            field,
                        )}
                        color="primary"
                    >
                        <EmojiFlags color="action" />
                    </Badge>
                </Button>
            </Tooltip>
        </Stack>
    );
}
