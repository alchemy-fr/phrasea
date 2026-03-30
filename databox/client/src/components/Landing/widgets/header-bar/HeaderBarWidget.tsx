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
    Button,
    IconButton,
    Stack,
    InputLabel,
} from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import WidgetOptionsDialogWrapper from '../components/WidgetOptionsDialogWrapper.tsx';
import {useTranslation} from 'react-i18next';
import React from 'react';
import {FormRow} from '@alchemy/react-form';
import HeaderBar from './HeaderBar.tsx';
import {HeaderBarWidgetProps} from './types.ts';
import AddIcon from '@mui/icons-material/Add';

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
    const links = options.links || [];

    const handleLinkChange = (
        idx: number,
        field: 'label' | 'url',
        value: string
    ) => {
        const newLinks = links.map((link, i) =>
            i === idx ? {...link, [field]: value} : link
        );
        updateOptions({links: newLinks});
    };

    const handleAddLink = () => {
        updateOptions({links: [...links, {label: '', url: ''}]});
    };

    const handleRemoveLink = (idx: number) => {
        const newLinks = links.filter((_, i) => i !== idx);
        updateOptions({links: newLinks});
    };

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
                <InputLabel sx={{mb: 1}}>
                    {t(
                        'editor.widgets.header_bar.options.links.label',
                        'Links'
                    )}
                </InputLabel>
                <Stack spacing={2}>
                    {links.map((link, idx) => (
                        <Stack
                            direction="row"
                            spacing={1}
                            alignItems="center"
                            key={idx}
                        >
                            <TextField
                                label={t(
                                    'editor.widgets.header_bar.options.link_label.label',
                                    'Label'
                                )}
                                value={link.label}
                                onChange={e =>
                                    handleLinkChange(
                                        idx,
                                        'label',
                                        e.target.value
                                    )
                                }
                            />
                            <TextField
                                label={t(
                                    'editor.widgets.header_bar.options.link_url.label',
                                    'URL'
                                )}
                                value={link.url}
                                onChange={e =>
                                    handleLinkChange(idx, 'url', e.target.value)
                                }
                            />
                            <IconButton
                                aria-label="delete"
                                onClick={() => handleRemoveLink(idx)}
                            >
                                <DeleteIcon />
                            </IconButton>
                        </Stack>
                    ))}
                    <div>
                        <Button
                            startIcon={<AddIcon />}
                            variant="contained"
                            onClick={handleAddLink}
                        >
                            {t(
                                'editor.widgets.header_bar.options.add_link',
                                'Add Link'
                            )}
                        </Button>
                    </div>
                </Stack>
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
