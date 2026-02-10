import {useTranslation} from 'react-i18next';
import {Button} from '@mui/material';
import {Editor} from '@tiptap/core';
import {widgets} from '../widgets';

type Props = {
    editor: Editor;
};

export default function InsertMenu({editor}: Props) {
    const {t} = useTranslation();

    return (
        <>
            {widgets.map(w => (
                <Button
                    key={w.name}
                    onClick={() => {
                        editor
                            .chain()
                            .focus()
                            .setWidget({
                                widget: w.name,
                            })
                            .run();
                    }}
                >
                    {w.getTitle(t)}
                </Button>
            ))}
        </>
    );
}
