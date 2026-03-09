import type {Editor} from '@tiptap/core';
import React, {useMemo} from 'react';
import {BlockType, menuBarStateSelector} from './menuBarState.ts';
import {useEditorState} from '@tiptap/react';
import {
    Box,
    Divider,
    FormControl,
    InputLabel,
    MenuItem,
    Select,
    TextField,
    ToggleButtonGroup,
} from '@mui/material';
import ToggleButton from '@mui/material/ToggleButton';
import {EditorMenuAction, TextAlignEnum} from './editorTypes.ts';
import FormatBoldIcon from '@mui/icons-material/FormatBold';
import FormatItalicIcon from '@mui/icons-material/FormatItalic';
import FormatUnderlinedIcon from '@mui/icons-material/FormatUnderlined';
import {useTranslation} from 'react-i18next';
import UndoIcon from '@mui/icons-material/Undo';
import RedoIcon from '@mui/icons-material/Redo';
import FormatStrikethroughIcon from '@mui/icons-material/FormatStrikethrough';
import CodeIcon from '@mui/icons-material/Code';
import FormatListBulletedIcon from '@mui/icons-material/FormatListBulleted';
import FormatListNumberedIcon from '@mui/icons-material/FormatListNumbered';
import FormatQuoteIcon from '@mui/icons-material/FormatQuote';
import FormatClearIcon from '@mui/icons-material/FormatClear';
import IntegrationInstructionsIcon from '@mui/icons-material/IntegrationInstructions';
import FormatAlignLeftIcon from '@mui/icons-material/FormatAlignLeft';
import FormatAlignRightIcon from '@mui/icons-material/FormatAlignRight';
import FormatAlignCenterIcon from '@mui/icons-material/FormatAlignCenter';
import FormatAlignJustifyIcon from '@mui/icons-material/FormatAlignJustify';
import VisibilityIcon from '@mui/icons-material/Visibility';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import {toggleButtonGroupClasses} from '@mui/material/ToggleButtonGroup';
import {styled} from '@mui/material/styles';
import {OnPageSave} from './PageEditor.tsx';
import SaveIcon from '@mui/icons-material/Save';
import {Page} from '../../../types.ts';
import IconButton from '@mui/material/IconButton';
import {getPath, Link, useModals} from '@alchemy/navigation';
import {routes} from '../../../routes.ts';
import SettingsIcon from '@mui/icons-material/Settings';
import {DropdownActions} from '@alchemy/phrasea-ui';
import ColorPalette from './menu/colors/ColorPalette.tsx';
import LinkIcon from '@mui/icons-material/Link';
import LinkDialog from './extensions/link/LinkDialog.tsx';
import {Level} from '@tiptap/extension-heading';

export type MenuBarOptions = {
    onPreview?: () => void;
    onEdit?: () => void;
    data: Page;
};

type Props = {
    hasChanged: boolean;
    editor: Editor;
    onSave: OnPageSave;
} & MenuBarOptions;

export const MenuBar = ({
    hasChanged,
    data,
    editor,
    onEdit,
    onSave,
    onPreview,
}: Props) => {
    const {t} = useTranslation();

    const {openModal} = useModals();

    const fontFamilies = useMemo(() => {
        return [
            {label: 'Default', value: 'default'},
            {label: 'Arial', value: 'Arial, sans-serif'},
            {label: 'Georgia', value: 'Georgia, serif'},
            {label: 'Impact', value: 'Impact, sans-serif'},
            {label: 'Tahoma', value: 'Tahoma, sans-serif'},
            {label: 'Times New Roman', value: '"Times New Roman", serif'},
            {label: 'Verdana', value: 'Verdana, sans-serif'},
        ];
    }, []);

    const editorState = useEditorState({
        editor,
        selector: menuBarStateSelector,
    });

    const formats = useMemo<
        (EditorMenuAction<typeof editorState> | null)[]
    >(() => {
        const increaseFontSize = (
            by: number,
            editor: Editor,
            eState: typeof editorState
        ) => {
            const currentSize = parseInt(eState.currentFontSize ?? '15');
            editor
                .chain()
                .focus()
                .setFontSize(`${currentSize + by}px`)
                .run();
        };

        return [
            {
                id: 'save',
                label: t('editor.format.save', 'Save'),
                icon: <SaveIcon />,
                isActive: false,
                toggle: editor => onSave(editor.getJSON()),
                can: hasChanged,
            },
            onPreview
                ? {
                      id: 'view',
                      label: t('editor.format.view', 'View'),
                      icon: <VisibilityIcon />,
                      isActive: false,
                      can: true,
                      toggle: () => onPreview(),
                  }
                : null,
            {
                id: 'divSave',
                isDivider: true,
            },
            {
                id: 'undo',
                label: t('editor.format.undo', 'Undo'),
                icon: <UndoIcon />,
                isActive: false,
                can: editorState.canUndo,
                toggle: editor => editor.chain().focus().undo().run(),
            },
            {
                id: 'redo',
                label: t('editor.format.redo', 'Redo'),
                icon: <RedoIcon />,
                isActive: false,
                can: editorState.canRedo,
                toggle: editor => editor.chain().focus().redo().run(),
            },
            {
                id: 'font',
                isDivider: true,
            },
            {
                id: 'heading',
                render: ({editor, editorState}) => (
                    <FormControl>
                        <Select
                            style={{
                                width: 150,
                            }}
                            labelId="heading-select-label"
                            value={editorState.currentBlockType || ''}
                            disabled={!editorState.canSetParagraph}
                            onChange={e => {
                                const value = e.target.value as BlockType | '';
                                if (value === 'paragraph') {
                                    editor.chain().focus().setParagraph().run();
                                    return;
                                }

                                const level = (
                                    value
                                        ? parseInt(value.replace('heading', ''))
                                        : null
                                ) as Level | null;
                                if (level) {
                                    editor
                                        .chain()
                                        .focus()
                                        .setHeading({level})
                                        .run();
                                } else {
                                    editor.chain().focus().setParagraph().run();
                                }
                            }}
                        >
                            <MenuItem value={`paragraph`}>
                                {t(
                                    'editor.format.block_type.paragraph.label',
                                    `Normal`
                                )}
                            </MenuItem>
                            {[1, 2, 3, 4, 5, 6].map(heading => (
                                <MenuItem
                                    key={heading}
                                    value={`heading${heading}`}
                                >
                                    {t(
                                        'editor.format.block_type.heading.label',
                                        {
                                            defaultValue: 'Heading {{number}}',
                                            number: heading,
                                        }
                                    )}
                                </MenuItem>
                            ))}
                        </Select>
                    </FormControl>
                ),
            },
            {
                id: 'fontFamily',
                render: ({editor, editorState}) => (
                    <FormControl>
                        <InputLabel id="font-family-select-label">
                            {t('editor.format.fontFamily.label', 'Font Family')}
                        </InputLabel>
                        <Select
                            style={{
                                width: 150,
                            }}
                            labelId="font-family-select-label"
                            value={editorState.currentFontFamily || 'default'}
                            label={t(
                                'editor.format.fontFamily.label',
                                'Font Family'
                            )}
                            disabled={!editorState.canSetFontFamily}
                            onChange={e => {
                                const value = e.target.value;
                                editor
                                    .chain()
                                    .setFontFamily(value || null)
                                    .run();
                            }}
                        >
                            {fontFamilies.map(font => (
                                <MenuItem key={font.value} value={font.value}>
                                    {font.label}
                                </MenuItem>
                            ))}
                        </Select>
                    </FormControl>
                ),
            },
            {
                id: 'fontSize',
                render: ({editor, editorState}) => (
                    <>
                        <ToggleButton
                            value="fontSizeLess"
                            aria-label={'Decrease Font Size'}
                            disabled={!editorState.canSetFontSize}
                            onClick={() =>
                                increaseFontSize(-1, editor, editorState)
                            }
                        >
                            A-
                        </ToggleButton>
                        <TextField
                            value={
                                editorState.currentFontSize?.replace(
                                    'px',
                                    ''
                                ) ?? ''
                            }
                            type={'number'}
                            onChange={e => {
                                const value = e.target.value;
                                editor
                                    .chain()
                                    .setFontSize(value ? `${value}px` : '16px')
                                    .run();
                            }}
                            disabled={!editorState.canSetFontSize}
                            variant={'outlined'}
                            InputProps={{
                                disableUnderline: true,
                                sx: {
                                    width: 100,
                                    textAlign: 'center',
                                },
                            }}
                        />
                        <ToggleButton
                            value="fontSizeMore"
                            aria-label={'Increase Font Size'}
                            disabled={!editorState.canSetFontSize}
                            onClick={() =>
                                increaseFontSize(1, editor, editorState)
                            }
                        >
                            A+
                        </ToggleButton>
                    </>
                ),
            },
            {
                id: 'textColor',
                render: ({editor, editorState}) => (
                    <>
                        <DropdownActions
                            mainButton={({onClick}) => (
                                <ToggleButton
                                    value="textColor"
                                    aria-label={'Text Color'}
                                    disabled={!editorState.canSetColor}
                                    onClick={onClick}
                                >
                                    <div
                                        style={{
                                            border: '1px solid #ccc',
                                            width: 16,
                                            height: 16,
                                            backgroundColor:
                                                editorState.currentColor,
                                            borderRadius: 2,
                                        }}
                                    />
                                </ToggleButton>
                            )}
                        >
                            {close => [
                                <ColorPalette
                                    onTextColorChange={color => {
                                        editor
                                            .chain()
                                            .focus()
                                            .setColor(color)
                                            .run();
                                        close();
                                    }}
                                    onBackgroundColorChange={color => {
                                        editor
                                            .chain()
                                            .focus()
                                            .setHighlight({color})
                                            .run();
                                        close();
                                    }}
                                />,
                            ]}
                        </DropdownActions>
                    </>
                ),
            },
            {
                id: 'bold',
                label: t('editor.format.bold', 'Bold'),
                icon: <FormatBoldIcon />,
                isActive: editorState.isBold,
                can: editorState.canBold,
                toggle: editor => editor.chain().focus().toggleBold().run(),
            },
            {
                id: 'italic',
                label: t('editor.format.italic', 'Italic'),
                icon: <FormatItalicIcon />,
                isActive: editorState.isItalic,
                can: editorState.canItalic,
                toggle: editor => editor.chain().focus().toggleItalic().run(),
            },
            {
                id: 'underline',
                label: t('editor.format.underline', 'Underline'),
                icon: <FormatUnderlinedIcon />,
                isActive: editorState.isUnderline,
                can: true,
                toggle: editor =>
                    editor.chain().focus().toggleUnderline().run(),
            },
            {
                id: 'strikethrough',
                label: t('editor.format.strikethrough', 'Strikethrough'),
                icon: <FormatStrikethroughIcon />,
                isActive: editorState.isStrike,
                can: editorState.canStrike,
                toggle: editor => editor.chain().focus().toggleStrike().run(),
            },
            {
                id: 'divBlocks',
                isDivider: true,
            },
            {
                id: 'bulletList',
                label: t('editor.format.bulletList', 'Bullet List'),
                icon: <FormatListBulletedIcon />,
                isActive: editorState.isBulletList,
                can: true,
                toggle: editor =>
                    editor.chain().focus().toggleBulletList().run(),
            },
            {
                id: 'orderedList',
                label: t('editor.format.orderedList', 'Ordered List'),
                icon: <FormatListNumberedIcon />,
                isActive: editorState.isOrderedList,
                can: true,
                toggle: editor =>
                    editor.chain().focus().toggleOrderedList().run(),
            },
            {
                id: 'blockQuote',
                label: t('editor.format.blockQuote', 'Block Quote'),
                icon: <FormatQuoteIcon />,
                isActive: editorState.isBlockquote,
                can: true,
                toggle: editor =>
                    editor.chain().focus().toggleBlockquote().run(),
            },
            {
                id: 'codeBlock',
                label: t('editor.format.codeBlock', 'Code Block'),
                icon: <IntegrationInstructionsIcon />,
                isActive: editorState.isCodeBlock,
                can: true,
                toggle: editor =>
                    editor.chain().focus().toggleCodeBlock().run(),
            },
            {
                id: 'divider2',
                isDivider: true,
            },
            {
                id: 'code',
                label: t('editor.format.code', 'Code'),
                icon: <CodeIcon />,
                isActive: editorState.isCode,
                can: editorState.canCode,
                toggle: editor => editor.chain().focus().toggleCode().run(),
            },
            {
                id: 'divider3',
                isDivider: true,
            },
            {
                id: 'clear',
                label: t('editor.format.clear', 'Clear Formatting'),
                icon: <FormatClearIcon />,
                isActive: false,
                can: editorState.canClearMarks || editorState.canClearMarks,
                toggle: editor => editor.chain().focus().unsetAllMarks().run(),
            },
            {
                id: 'divider4',
                isDivider: true,
            },
            {
                id: 'link',
                label: t('editor.format.link', 'Link'),
                icon: <LinkIcon />,
                isActive: editorState.isLink,
                can: editorState.canSetLink,
                toggle: editor => {
                    openModal(LinkDialog, {
                        editor,
                        currentLinkSpec: editorState.currentLinkSpec,
                    });
                },
            },
            {
                id: 'dividerLinks',
                isDivider: true,
            },
            {
                id: 'alignLeft',
                label: t('editor.format.alignLeft', 'Align Left'),
                icon: <FormatAlignLeftIcon />,
                isActive: editorState.isTextAlign(TextAlignEnum.Left),
                can: editorState.canSetTextAlign(TextAlignEnum.Left),
                toggle: editor =>
                    editor
                        .chain()
                        .focus()
                        .setTextAlign(TextAlignEnum.Left)
                        .run(),
            },
            {
                id: 'alignCenter',
                label: t('editor.format.alignCenter', 'Align Center'),
                icon: <FormatAlignCenterIcon />,
                isActive: editorState.isTextAlign(TextAlignEnum.Center),
                can: editorState.canSetTextAlign(TextAlignEnum.Center),
                toggle: editor =>
                    editor
                        .chain()
                        .focus()
                        .setTextAlign(TextAlignEnum.Center)
                        .run(),
            },
            {
                id: 'alignRight',
                label: t('editor.format.alignRight', 'Align Right'),
                icon: <FormatAlignRightIcon />,
                isActive: editorState.isTextAlign(TextAlignEnum.Right),
                can: editorState.canSetTextAlign(TextAlignEnum.Right),
                toggle: editor =>
                    editor
                        .chain()
                        .focus()
                        .setTextAlign(TextAlignEnum.Right)
                        .run(),
            },
            {
                id: 'alignJustify',
                label: t('editor.format.alignJustify', 'Align Justify'),
                icon: <FormatAlignJustifyIcon />,
                isActive: editorState.isTextAlign(TextAlignEnum.Justify),
                can: editorState.canSetTextAlign(TextAlignEnum.Justify),
                toggle: editor =>
                    editor
                        .chain()
                        .focus()
                        .setTextAlign(TextAlignEnum.Justify)
                        .run(),
            },
        ];
    }, [editorState, t, hasChanged]);

    if (!editor) {
        return null;
    }

    return (
        <>
            <Box
                sx={{
                    display: 'flex',
                    flexDirection: 'row',
                    gap: 1,
                    alignItems: 'center',
                }}
            >
                <div>
                    <IconButton
                        component={Link}
                        to={getPath(routes.pageAdmin.routes.index)}
                    >
                        <ArrowBackIcon />
                    </IconButton>
                </div>
                <div>
                    <strong>{data.title}</strong>
                </div>
                {onEdit ? (
                    <div>
                        <IconButton onClick={onEdit}>
                            <SettingsIcon />
                        </IconButton>
                    </div>
                ) : null}
            </Box>
            <Box
                sx={theme => ({
                    borderBottom: `1px solid ${theme.palette.divider}`,
                })}
            >
                <StyledToggleButtonGroup>
                    {formats
                        .filter(f => null !== f)
                        .map(format => {
                            if (format.isDivider) {
                                return (
                                    <Divider
                                        key={format.id}
                                        flexItem
                                        orientation="vertical"
                                        sx={{mx: 0.5, my: 1}}
                                    />
                                );
                            }
                            if (format.render) {
                                return (
                                    <React.Fragment key={format.id}>
                                        {format.render({editor, editorState})}
                                    </React.Fragment>
                                );
                            }

                            return (
                                <ToggleButton
                                    key={format.id}
                                    value={format.id}
                                    aria-label={format.label}
                                    disabled={!format.can}
                                    onClick={() => format.toggle(editor)}
                                    selected={format.isActive}
                                >
                                    {format.icon}
                                </ToggleButton>
                            );
                        })}
                </StyledToggleButtonGroup>
            </Box>
        </>
    );
};

const StyledToggleButtonGroup = styled(ToggleButtonGroup)(({theme}) => ({
    [`& .${toggleButtonGroupClasses.grouped}`]: {
        margin: theme.spacing(0.5),
        border: 0,
        borderRadius: theme.shape.borderRadius,
        [`&.${toggleButtonGroupClasses.disabled}`]: {
            border: 0,
        },
    },
    [`& .${toggleButtonGroupClasses.middleButton},& .${toggleButtonGroupClasses.lastButton}`]:
        {
            borderLeft: '1px solid transparent',
        },
}));
