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
        position: 'sticky',
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
                            value="absolute"
                            control={<Radio />}
                            label={t(
                                'editor.widgets.asset.options.position.absolute',
                                'Absolute'
                            )}
                        />
                        <FormControlLabel
                            value="sticky"
                            control={<Radio />}
                            label={t(
                                'editor.widgets.asset.options.position.sticky',
                                'Sticky'
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
                        <FormControlLabel
                            value="relative"
                            control={<Radio />}
                            label={t(
                                'editor.widgets.asset.options.position.relative',
                                'Relative'
                            )}
                        />
                    </RadioGroup>
                </FormControl>
            </FormRow>
        </WidgetOptionsDialogWrapper>
    );
}
