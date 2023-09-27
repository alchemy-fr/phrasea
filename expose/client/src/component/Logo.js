import React from 'react';
import config from '../lib/config';

export function Logo() {
    const title = config.clientLogoAlt || 'Expose.';

    if (config.clientLogoUrl) {
        return <div className="exp-logo">
                <img
                src={config.clientLogoUrl}
                alt={title}
            />
        </div>
    }

    return <div className="exp-logo">
        {title}
    </div>
}
