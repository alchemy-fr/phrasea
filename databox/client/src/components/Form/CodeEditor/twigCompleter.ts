import {Ace} from 'ace-builds';
import {useAttributeDefinitionStore} from '../../../store/attributeDefinitionStore.ts';

export const twigCompleter: Ace.Completer = {
    getCompletions: function (_editor, session, pos, _prefix, callback) {
        if (session.getMode() && session.getMode().path === 'ace/mode/twig') {
            useAttributeDefinitionStore.getState().load();
            const definitions =
                useAttributeDefinitionStore.getState().definitions;

            const props: Record<string, string[]> = {
                file: ['getFilename()', 'getId()', 'getSize()'],
                attr: ['name', ...definitions.map(def => def.slug)],
                asset: ['getId()', 'getSource()', 'getCollections()'],
            };

            let token = session.getTokenAt(pos.row, pos.column);
            if (token.type === 'identifier' && undefined !== token.start) {
                token = session.getTokenAt(pos.row, token.start);
            }
            if (token.type === 'punctuation.operator' && token.value === '.') {
                token = session.getTokenAt(pos.row, pos.column - 2);
            }

            // Check that the current token is an identifier
            if (!token || token.type !== 'identifier') {
                return callback(null, []);
            }

            const identifier = token.value;
            const completions: Ace.Completion[] = [];

            Object.entries(props).forEach(([key, values]) => {
                if (identifier.endsWith(key)) {
                    values.forEach((value: string) => {
                        completions.push({
                            value,
                            meta: key,
                            score: 1,
                            completerId: 'twig',
                        });
                    });
                }
            });

            callback(null, completions);
        } else {
            callback(null, []);
        }
    },
};
