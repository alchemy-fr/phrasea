'use client';

import {useEffect, useRef, useState} from 'react';
import {useTheme} from 'next-themes';
import type {Ace} from 'ace-builds';
import {cn} from '@/lib/utils/cn';

type Props = {
    'value': string;
    'onChange': (value: string) => void;
    'id'?: string;
    'minLines'?: number;
    'maxLines'?: number;
    'placeholder'?: string;
    'readOnly'?: boolean;
    'className'?: string;
    'aria-invalid'?: boolean;
};

type AceModule = typeof import('ace-builds');

let loading: Promise<AceModule> | undefined;

/**
 * Ace touches `window` as soon as it is imported: loaded on the client only,
 * once, with what the editors need.
 */
function loadAce(): Promise<AceModule> {
    loading ??= (async () => {
        const ace = (await import('ace-builds')).default as AceModule;
        await Promise.all([
            import('ace-builds/src-noconflict/mode-json'),
            import('ace-builds/src-noconflict/ext-language_tools'),
            import('ace-builds/src-noconflict/theme-textmate'),
            import('ace-builds/src-noconflict/theme-tomorrow_night'),
        ]);

        return ace;
    })();

    return loading;
}

/**
 * JSON code field (Ace): syntax highlighting, line numbers, completion,
 * two-space indentation.
 */
export function CodeEditor({
    value,
    onChange,
    id,
    minLines = 16,
    maxLines = 40,
    placeholder,
    readOnly,
    className,
    'aria-invalid': invalid,
}: Props) {
    const container = useRef<HTMLDivElement>(null);
    const [editor, setEditor] = useState<Ace.Editor>();
    const {resolvedTheme} = useTheme();
    const onChangeRef = useRef(onChange);
    useEffect(() => {
        onChangeRef.current = onChange;
    }, [onChange]);
    // Initial options, the effects below keep them in sync
    const initial = useRef({value, placeholder, readOnly, minLines, maxLines});

    useEffect(() => {
        let cancelled = false;
        let instance: Ace.Editor | undefined;
        void loadAce().then(ace => {
            if (cancelled || !container.current) {
                return;
            }
            const {value, placeholder, readOnly, minLines, maxLines} =
                initial.current;
            instance = ace.edit(container.current, {
                value,
                minLines,
                maxLines,
                placeholder,
                readOnly,
                fontSize: 12,
                tabSize: 2,
                useSoftTabs: true,
                showPrintMargin: false,
                useWorker: false,
                enableBasicAutocompletion: true,
                enableLiveAutocompletion: true,
            });
            instance.session.setMode('ace/mode/json');
            instance.clearSelection();
            instance.on('change', () =>
                onChangeRef.current(instance!.getValue())
            );
            if (id) {
                instance.textInput.getElement().id = id;
            }
            setEditor(instance);
        });

        return () => {
            cancelled = true;
            instance?.destroy();
        };
    }, [id]);

    useEffect(() => {
        if (editor && editor.getValue() !== value) {
            editor.session.setValue(value);
        }
    }, [editor, value]);

    useEffect(() => {
        editor?.setReadOnly(!!readOnly);
    }, [editor, readOnly]);

    useEffect(() => {
        editor?.setTheme(
            resolvedTheme === 'dark'
                ? 'ace/theme/tomorrow_night'
                : 'ace/theme/textmate'
        );
    }, [editor, resolvedTheme]);

    return (
        <div
            className={cn(
                'overflow-hidden rounded-md border border-input shadow-xs focus-within:ring-2 focus-within:ring-ring/60',
                invalid && 'border-destructive',
                className
            )}
            data-slot="code-editor"
        >
            <div
                ref={container}
                className="w-full"
                // Until Ace is loaded: the size it will take
                style={{minHeight: `${minLines * 16 + 8}px`}}
            />
        </div>
    );
}
