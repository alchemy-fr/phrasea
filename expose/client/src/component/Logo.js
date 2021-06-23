import React from 'react';
import config from '../lib/config';

export function Logo() {
    if (config.get('clientLogoUrl')) {
        return <div className="exp-logo">
                <img
                src={config.get('clientLogoUrl')}
                alt={config.get('clientLogoAlt') || 'Expose.'}
            />
        </div>
    }

    return <div className="exp-logo">
        Expose.
    </div>
}
