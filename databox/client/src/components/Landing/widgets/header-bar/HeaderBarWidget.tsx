import {TFunction} from '@alchemy/i18n';
import {
    RenderWidgetOptionsProps,
    RenderWidgetProps,
    WidgetInterface,
} from '../widgetTypes.ts';
import {
    FormControl,
    FormControlLabel,
    FormLabel,
    Radio,
    RadioGroup,
    TextField,
} from '@mui/material';
import WidgetOptionsDialogWrapper from '../components/WidgetOptionsDialogWrapper.tsx';
import {useTranslation} from 'react-i18next';
import React from 'react';
import {FormRow} from '@alchemy/react-form';
import HeaderBar from './HeaderBar.tsx';
import {HeaderBarWidgetProps} from './types.ts';

const HeaderBarWidget: WidgetInterface<HeaderBarWidgetProps> = {
    name: 'header_bar',

    getTitle(t: TFunction): string {
        return t('editor.widgets.header_bar.title', 'Header Bar');
    },

    component: Component,
    optionsComponent: Options,

    defaultOptions: {
        position: 'fixed',
        title: 'App Name',
    },
};

export default HeaderBarWidget;

function Component({options}: RenderWidgetProps<HeaderBarWidgetProps>) {
    return <HeaderBar {...options} />;
}

function Options({
    options,
    updateOptions,
    ...props
}: RenderWidgetOptionsProps<HeaderBarWidgetProps>) {
    const {t} = useTranslation();

    return (
        <WidgetOptionsDialogWrapper {...props}>
            <FormRow>
                <TextField
                    label={t(
                        'editor.widgets.asset.options.title.label',
                        'Title'
                    )}
                    value={options.title}
                    onChange={e => {
                        updateOptions({
                            title: e.target.value,
                        });
                    }}
                />
            </FormRow>

            <FormRow>
                <TextField
                    label={t(
                        'editor.widgets.asset.options.link1.label',
                        'Link 1'
                    )}
                    value={options.link1}
                    onChange={e => {
                        updateOptions({
                            link1: e.target.value,
                        });
                    }}
                />
            </FormRow>

            <FormRow>
                <TextField
                    label={t(
                        'editor.widgets.asset.options.link2.label',
                        'Link 2'
                    )}
                    value={options.link2}
                    onChange={e => {
                        updateOptions({
                            link2: e.target.value,
                        });
                    }}
                />
            </FormRow>

            <FormRow>
                <FormControl>
                    <FormLabel id={'position-label'}>
                        {t(
                            'editor.widgets.asset.options.position.label',
                            'Image Position'
                        )}
                    </FormLabel>
                    <RadioGroup
                        aria-labelledby="position-label"
                        defaultValue={options.position}
                        onChange={e => {
                            updateOptions({
                                position: e.target
                                    .value as HeaderBarWidgetProps['position'],
                            });
                        }}
                    >
                        <FormControlLabel
                            value="fixed"
                            control={<Radio />}
                            label={t(
                                'editor.widgets.asset.options.position.fixed',
                                'Fixed'
                            )}
                        />
                        <FormControlLabel
                            value="static"
                            control={<Radio />}
                            label={t(
                                'editor.widgets.asset.options.position.static',
                                'Static'
                            )}
                        />
                    </RadioGroup>
                </FormControl>
            </FormRow>
        </WidgetOptionsDialogWrapper>
    );
}
