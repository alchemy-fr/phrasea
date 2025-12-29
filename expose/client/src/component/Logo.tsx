import React from 'react';
import {config} from '../init.ts';
import {parseInlineStyle} from '@alchemy/core';

export function Logo() {
    const title = 'Expose.';
    if (config.logo) {
        return (
            <div className="exp-logo">
                <img
                    src={config.logo.src}
                    alt={title}
                    style={{
                        ...(config.logo!.style
                            ? parseInlineStyle(config.logo.style)
                            : {maxHeight: 32, maxWidth: 150}),
                    }}
                />
            </div>
        );
    }

    return <div className="exp-logo">{title}</div>;
}
