'use client';

import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {annotationZIndex, selectionColor} from './controls';
import {
    measureTextWidth,
    textAscent,
    textFontFamily,
    textLineHeight,
    textLines,
    type MediaSize,
} from './geometry';
import {textFontSize} from './shapes';
import type {TextEdit} from './useAnnotationEditor';

/**
 * The text of a shape typed in place, at its size: Enter or leaving the
 * field keeps it, Shift+Enter breaks the line, Escape gives up (an emptied
 * text removes the shape).
 */
export function TextEditor({
    edit,
    media,
    zoom,
    onCommit,
    onCancel,
}: {
    edit: TextEdit;
    media: MediaSize;
    zoom: number;
    onCommit: (text: string) => void;
    onCancel: () => void;
}) {
    const {t} = useTranslation();
    const ref = useRef<HTMLTextAreaElement>(null);
    const [text, setText] = useState(edit.annotation.text);
    const done = useRef(false);
    const fontSize = textFontSize(edit.annotation, media);
    const placeholder = t('annotation.text_placeholder', 'Type a text…');
    const padding = Math.max(2, fontSize * 0.15);
    const lines = textLines(text || placeholder);
    const width =
        Math.max(...lines.map(l => measureTextWidth(l || ' ', fontSize))) +
        padding * 2 +
        4;
    const height =
        fontSize * (1 + (lines.length - 1) * textLineHeight) + padding * 2;

    useEffect(() => {
        const el = ref.current;
        if (el) {
            el.focus();
            el.select();
        }
    }, []);

    const finish = (commit: boolean) => {
        if (done.current) {
            return;
        }
        done.current = true;
        if (commit) {
            onCommit(text);
        } else {
            onCancel();
        }
    };

    const stop = (e: React.SyntheticEvent) => e.stopPropagation();

    return (
        <textarea
            ref={ref}
            data-testid="annotation-text-editor"
            value={text}
            rows={lines.length}
            placeholder={placeholder}
            aria-label={t('annotation.type.text', 'Text')}
            spellCheck={false}
            style={{
                position: 'absolute',
                left: edit.annotation.x * media.width - padding,
                // The first baseline where the text is drawn: the extra
                // line height is below it
                top:
                    edit.annotation.y * media.height -
                    fontSize * textAscent -
                    padding -
                    (fontSize * (textLineHeight - 1)) / 2,
                width,
                height,
                padding: `${padding}px`,
                margin: 0,
                zIndex: annotationZIndex + 2,
                font: `${fontSize}px ${textFontFamily}`,
                lineHeight: textLineHeight,
                color: edit.annotation.c,
                background: 'rgba(255, 255, 255, 0.6)',
                border: `${Math.max(1, 1 / zoom)}px dashed ${selectionColor}`,
                borderRadius: 2,
                outline: 'none',
                overflow: 'hidden',
                resize: 'none',
                whiteSpace: 'pre',
            }}
            onChange={e => setText(e.target.value)}
            onKeyDown={e => {
                e.stopPropagation();
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    finish(true);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    finish(false);
                }
            }}
            onBlur={() => finish(true)}
            onPointerDown={stop}
            onMouseDown={stop}
            onClick={stop}
            onDoubleClick={stop}
        />
    );
}
