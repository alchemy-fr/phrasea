import type {Editor} from '@tiptap/core';
import React, {useMemo} from 'react';
import {menuBarStateSelector} from './menuBarState.ts';
import {useEditorState} from '@tiptap/react';
import {Box, Divider, ToggleButtonGroup} from '@mui/material';
import ToggleButton from '@mui/material/ToggleButton';
import {TextAlignEnum, EditorMenuAction} from './editorTypes.ts';
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

import {toggleButtonGroupClasses} from '@mui/material/ToggleButtonGroup';
import {styled} from '@mui/material/styles';

type Props = {editor: Editor};
export const MenuBar = ({editor}: Props) => {
    const {t} = useTranslation();
    const editorState = useEditorState({
        editor,
        selector: menuBarStateSelector,
    });

    const formats = useMemo<EditorMenuAction[]>(
        () => [
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
                id: 'divider1',
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
        ],
        [editorState, t]
    );

    if (!editor) {
        return null;
    }

    return (
        <Box>
            <StyledToggleButtonGroup>
                {formats.map(format => {
                    if (format.isDivider) {
                        return (
                            <Divider
                                flexItem
                                orientation="vertical"
                                sx={{mx: 0.5, my: 1}}
                            />
                        );
                    }

                    return (
                        <ToggleButton
                            key={format.toggle.toString()}
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
