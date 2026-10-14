'use client';

import {useEffect, useRef, useState} from 'react';
import {useTheme} from 'next-themes';
import type {Ace} from 'ace-builds';
import {cn} from '@/lib/utils/cn';
import {useDefinitionsStore} from '@/features/attributes/definitionsStore';

export type CodeEditorMode = 'yaml' | 'twig';

type Props = {
    'value': string;
    'onChange': (value: string) => void;
    /** `twig`: YAML with Twig tags, and completion of the Twig context */
    'mode'?: CodeEditorMode;
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
            import('ace-builds/src-noconflict/mode-yaml'),
            import('ace-builds/src-noconflict/ext-language_tools'),
            import('ace-builds/src-noconflict/theme-textmate'),
            import('ace-builds/src-noconflict/theme-tomorrow_night'),
        ]);
        const langTools = ace.require('ace/ext/language_tools');
        const {twigCompleter} = await import('./twigYamlMode');
        langTools.setCompleters([
            langTools.keyWordCompleter,
            langTools.textCompleter,
            twigCompleter(() =>
                useDefinitionsStore.getState().definitions.map(d => d.slug)
            ),
        ]);

        return ace;
    })();

    return loading;
}

/**
 * Code field (Ace): YAML, or YAML holding Twig templates.
 */
export function CodeEditor({
    value,
    onChange,
    mode = 'yaml',
    id,
    minLines = 8,
    maxLines = 30,
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
        void loadAce().then(async ace => {
            if (cancelled || !container.current) {
                return;
            }
            const {createTwigYamlMode} = await import('./twigYamlMode');
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
            instance.session.setMode(
                mode === 'twig' ? createTwigYamlMode(ace) : 'ace/mode/yaml'
            );
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
    }, [mode, id]);

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
