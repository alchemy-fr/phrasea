import {TFunction} from '@alchemy/i18n';
import {
    RenderWidgetOptionsProps,
    RenderWidgetProps,
    WidgetInterface,
} from './widgetTypes.ts';
import {Box, TextField} from '@mui/material';
import {useTranslation} from 'react-i18next';
import React from 'react';
import {FormRow} from '@alchemy/react-form';
import WidgetOptionsDialogWrapper from './components/WidgetOptionsDialogWrapper.tsx';

type Props = {
    spacing: number;
};

const SpacerWidget: WidgetInterface<Props> = {
    name: 'spacer',

    getTitle(t: TFunction): string {
        return t('editor.widgets.spacer.title', 'Spacer');
    },

    component: Component,
    optionsComponent: Options,

    defaultOptions: {
        spacing: 1,
    },
};

export default SpacerWidget;

function Component({options}: RenderWidgetProps<Props>) {
    return (
        <Box
            sx={theme => ({
                height: theme.spacing(options.spacing),
            })}
        />
    );
}

function Options({
    options,
    updateOptions,
    ...props
}: RenderWidgetOptionsProps<Props>) {
    const {t} = useTranslation();

    return (
        <WidgetOptionsDialogWrapper {...props}>
            <FormRow>
                <TextField
                    label={t(
                        'editor.widgets.spacer.options.spacing.label',
                        'Spacing'
                    )}
                    type="number"
                    value={options.spacing}
                    onChange={e => {
                        const value = parseInt(e.target.value, 10);
                        if (!isNaN(value)) {
                            updateOptions({
                                spacing: value,
                            });
                        }
                    }}
                />
            </FormRow>
        </WidgetOptionsDialogWrapper>
    );
}
