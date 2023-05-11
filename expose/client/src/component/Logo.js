import React from 'react';
import config from '../lib/config';

export function Logo() {
    const title = config.get('clientLogoAlt') || 'Expose.';

    if (config.get('clientLogoUrl')) {
        return <div className="exp-logo">
                <img
                src={config.get('clientLogoUrl')}
                alt={title}
            />
        </div>
    }

    return <div className="exp-logo">
        {title}
    </div>
}
