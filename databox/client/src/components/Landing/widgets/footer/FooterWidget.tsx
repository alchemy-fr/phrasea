import {TFunction} from '@alchemy/i18n';
import {
    RenderWidgetOptionsProps,
    RenderWidgetProps,
    WidgetInterface,
} from '../widgetTypes.ts';
import {Button, IconButton, InputLabel, Stack, TextField} from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import WidgetOptionsDialogWrapper from '../components/WidgetOptionsDialogWrapper.tsx';
import {useTranslation} from 'react-i18next';
import React from 'react';
import {FormRow} from '@alchemy/react-form';
import AddIcon from '@mui/icons-material/Add';
import {FooterWidgetProps} from './types.ts';
import Footer from './Footer.tsx';

const FooterWidget: WidgetInterface<FooterWidgetProps> = {
    name: 'footer',

    getTitle(t: TFunction): string {
        return t('editor.widgets.footer.title', 'Footer');
    },

    component: Component,
    optionsComponent: Options,

    defaultOptions: {
        title: 'App Name',
        links: [],
        backgroundColor: '#333',
        textColor: '#fff',
    },
};

export default FooterWidget;

function Component({options}: RenderWidgetProps<FooterWidgetProps>) {
    return <Footer {...options} />;
}

function Options({
    options,
    updateOptions,
    ...props
}: RenderWidgetOptionsProps<FooterWidgetProps>) {
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
                <TextField
                    label={t(
                        'editor.widgets.footer.options.backgroundColor.label',
                        'Background Color'
                    )}
                    type="color"
                    value={options.backgroundColor}
                    onChange={e => {
                        updateOptions({
                            backgroundColor: e.target.value,
                        });
                    }}
                    sx={{width: 120}}
                />
                <TextField
                    label={t(
                        'editor.widgets.footer.options.textColor.label',
                        'Text Color'
                    )}
                    type="color"
                    value={options.textColor}
                    onChange={e => {
                        updateOptions({
                            textColor: e.target.value,
                        });
                    }}
                    sx={{width: 120, ml: 2}}
                />
            </FormRow>

            <FormRow>
                <InputLabel sx={{mb: 1}}>
                    {t('editor.widgets.footer.options.links.label', 'Links')}
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
                                    'editor.widgets.footer.options.link_label.label',
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
                                    'editor.widgets.footer.options.link_url.label',
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
                                'editor.widgets.footer.options.add_link',
                                'Add Link'
                            )}
                        </Button>
                    </div>
                </Stack>
            </FormRow>
        </WidgetOptionsDialogWrapper>
    );
}
