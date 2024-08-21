import {IndentationText, JsxText, Node, Project, QuoteKind, SourceFile, StringLiteral} from "ts-morph";

const project = new Project();
project.addSourceFilesAtPaths("src/**/*{.ts,.tsx}");
// project.addSourceFileAtPath("src/TestMorph.tsx");
project.manipulationSettings.set({
    quoteKind: QuoteKind.Single,
    indentationText: IndentationText.FourSpaces,
});

const sourceFiles = project.getSourceFiles();

function normalizeKey(key: string): string {
    return key
        .trim()
        .replace(/([a-z])([A-Z])/g, '$1_$2')
        .toLowerCase()
        .replace(/\W/g, '_')
        .replace(/_{2,}/g, '_')
        .replace(/_$/, '')
        .replace(/^_/, '')
    ;
}

sourceFiles.forEach((sourceFile: SourceFile) => {
    let hasTranslation = false;
    const fns = sourceFile.getFunctions();

    fns.forEach(fn => {
        // console.log(debug(fn, fn.getName() ?? 'anon'));

        const textNodes = findTextNodes(fn);

        let hasTranslationHook = false;
        for (const stmt of fn.getStatements()) {
            if (stmt.print().includes('useTranslation();')) {
                hasTranslationHook = true;
            }
        }

        if (textNodes.length > 0) {
            hasTranslation = true;

            if (!hasTranslationHook) {
                fn.insertStatements(0, `const {t} = useTranslation();`);
            }
        }

        textNodes.forEach((textNode) => {
            const value = textNode.getLiteralText().trim();
            const key = `${normalizeKey(fn.getName() ?? 'anonymous')}.${normalizeKey(value)}`;

            if (Node.isJsxText(textNode)) {
                textNode.replaceWithText(`{t('${key}', \`${value}\`)}`);
            } else {
                textNode.replaceWithText(`t('${key}', \`${value}\`)`);
            }

        });
    });

    if (hasTranslation) {
        let hasImport = false;
        for (const decl of sourceFile.getImportDeclarations()) {
            if (decl.getModuleSpecifier().getLiteralValue() === 'react-i18next') {
                hasImport = true;
            }
        }

        if (!hasImport) {
            sourceFile.addImportDeclaration({
                namedImports: ['useTranslation'],
                moduleSpecifier: 'react-i18next',
            });
        }

        sourceFile.save();
    }
});

type TextNode = JsxText | StringLiteral;

function findTextNodes(node: Node): TextNode[] {
    const textNodes: TextNode[] = [];

    node.getChildren().forEach(c => {
        for (const n of findTextNodes(c)) {
            textNodes.push(n);
        }
    });

    if (Node.isStringLiteral(node) && Node.isJsxExpression(node.getParent())) {
        const v = node.getLiteralText().trim();
        if (v && /^[A-Z]/.test(v)) {
            textNodes.push(node);
        }
    } else if (Node.isJsxText(node)) {
        const v = node.getLiteralText().trim();

        if (v && ![
            '(',
            ')',
            '[',
            ']',
            '-',
            '/',
            '+',
            '•',
            '#',
            '%',
            ':',
        ].includes(v)) {
            const parent = node.getParent();
            if (!Node.isJsxElement(parent) || parent.getStructure().name !== 'Trans') {
                textNodes.push(node);
            }
        }
    }

    return textNodes;
}


function debug(node: Node, componentName: string, depth: number = 0): string {
    let d = '';
    for (let i = 0; i < depth; i++) {
        d += '  ';
    }
    d += node.getKindName();

    node.getChildren().forEach(c => {
        d += "\n" + debug(c, componentName, depth + 1);
    });

    if (Node.isJsxText(node)) {
        d += ` = ${node.print().trim()}`;
    }

    return d;
}
