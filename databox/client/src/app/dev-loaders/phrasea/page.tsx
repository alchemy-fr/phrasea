'use client';

import {useState} from 'react';
import {Button} from '@alchemy/ui/ui/button';
import {FullPageLoader} from '@alchemy/ui/ui/loader';
import {
    PHRASEA_LOADER_VARIANTS,
    PhraseaLoader,
} from '@alchemy/ui/ui/phrasea-loader';

export default function DevPhraseaLoadersPage() {
    const [draw, setDraw] = useState(0);

    return (
        <div className="flex flex-col gap-8 p-6">
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                {PHRASEA_LOADER_VARIANTS.map(variant => (
                    <div
                        key={variant}
                        className="flex flex-col items-center gap-3 rounded-md border bg-card p-4"
                    >
                        <PhraseaLoader
                            variant={variant}
                            label={variant}
                            className="h-24"
                        />
                        <div className="flex items-end gap-4">
                            <PhraseaLoader variant={variant} className="h-10" />
                            <PhraseaLoader variant={variant} className="h-6" />
                        </div>
                        <div className="text-sm text-muted-foreground">
                            {variant}
                        </div>
                    </div>
                ))}
            </div>
            <div className="flex items-center gap-4">
                <PhraseaLoader key={draw} className="h-24" />
                <Button variant="outline" onClick={() => setDraw(d => d + 1)}>
                    Random
                </Button>
            </div>
            <div className="rounded-md border bg-card">
                <FullPageLoader key={draw} label="Loading…" />
            </div>
        </div>
    );
}
