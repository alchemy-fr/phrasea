import {FieldValues} from 'react-hook-form';
import CodeEditorWidget, {CodeEditorWidgetProps} from './CodeEditorWidget.tsx';
import langTools from 'ace-builds/src-noconflict/ext-language_tools';
import {twigCompleter} from './twigCompleter.ts';
import {useMemo} from 'react';
import TwigMode from './twig/twigMode.ts';

langTools.setCompleters([
    langTools.snippetCompleter,
    langTools.keyWordCompleter,
    twigCompleter,
]);

export default function TwigEditorWidget<TFieldValues extends FieldValues>({
    ...rest
}: Omit<CodeEditorWidgetProps<TFieldValues>, 'mode'>) {
    const twigMode = useMemo(() => {
        return new TwigMode();
    }, []);

    return <CodeEditorWidget mode={twigMode} {...rest} />;
}
