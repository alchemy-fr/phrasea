import {
    IndentationText, JsxAttribute, JsxAttributeName, JsxNamespacedNameStructure,
    JsxText,
    Node,
    NoSubstitutionTemplateLiteral,
    Project, PropertyAssignment, PropertyName,
    QuoteKind,
    SourceFile,
    StringLiteral, SyntaxKind
} from "ts-morph";

const debugEnabled = false;
const testFile = 'src/TestMorph.tsx';
// const testFile = 'src/api/clearAssociation.ts';

const project = new Project();

if (debugEnabled) {
    project.addSourceFileAtPath(testFile);
} else {
    project.addSourceFilesAtPaths([
        'src/**/*{.ts,.tsx}',
        `!${testFile}`,
    ]);
}

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

            if (Node.isJsxText(textNode) || Node.isJsxAttribute(textNode.getParent())) {
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

        if (debugEnabled) {
            console.log(sourceFile.print());
        } else {
            sourceFile.save();
        }
    }
});

type TextNode = JsxText | StringLiteral | NoSubstitutionTemplateLiteral;

function findTextNodes(node: Node, depth: number = 0): TextNode[] {
    if (debugEnabled) {
        console.log(`${'  '.repeat(depth)}${node.getKindName()}${Node.isJsxText(node) || Node.isStringLiteral(node) ? ` = ${node.print().trim()}` : ''}`);
    }
    const textNodes: TextNode[] = [];

    let children = node.getChildren();

    if (Node.isPropertyAssignment(node)) {
        children = children.slice(1);
    }

    children.forEach(c => {
        if (Node.isJsxElement(c) && c.getStructure().name === 'Trans') {
            return;
        }

        if ([
            SyntaxKind.TypeReference,
            SyntaxKind.IndexedAccessType,
            SyntaxKind.BinaryExpression,
            SyntaxKind.ElementAccessExpression,
        ].includes(node.getKind())) {
            return;
        }

        if (Node.isVariableDeclaration(node) && [
            'data',
            'd',
        ].includes(resolveName(node.getNameNode()))) {
            return;
        }

        if (Node.isCallExpression(node) && [
            't',
        ].includes(resolveName(node.getChildAtIndex(0)))) {
            return;
        }

        if (
            (Node.isJsxAttribute(node) || Node.isPropertyAssignment(node))
            && !isAllowedAttribute(node.getNameNode())) {
            return;
        }

        for (const n of findTextNodes(c, depth + 1)) {
            textNodes.push(n);
        }
    });

    if (
        Node.isNoSubstitutionTemplateLiteral(node)
        || Node.isStringLiteral(node)
        || Node.isJsxText(node)
    ) {
        const v = node.getLiteralText().trim();
        if (isAllowedText(v)) {
            textNodes.push(node);
        }
    }

    return textNodes;
}

function resolveName(node: Node): string {
    if (Node.isIdentifier(node)) {
        return node.getText();
    }
    if (Node.isStringLiteral(node)) {
        return node.getLiteralText();
    }
    if (Node.isNoSubstitutionTemplateLiteral(node)) {
        return node.getLiteralText();
    }
    if (Node.isComputedPropertyName(node)) {
        return resolveName(node.getChildAtIndex(1));
    }

    return node.getText();
}

function isAllowedAttribute(node: JsxAttributeName | PropertyName): boolean {
    const name = resolveName(node);

    return ![
        'class',
        'className',
        'key',
        'direction',
        'sx',
        'style',
        'transform',
        'mouseEvent',
        'id',
        'anchorReference',
        'Content-Type',
        'content-type',
        'accept',
        'Accept',
    ].includes(name);
}

function isAllowedText(txt: string): boolean {
    if (!txt) {
        return false;
    }

    const blacklist: RegExp[] = [
        /^h[1-6]$/,
        /^(primary|secondary|default|warning|error|info)$/,
        /^(small|large)$/,
        /^(string|object|number)$/,
        /^(contained|outlined)$/,
        /^(lg|md|sm|xs)$/,
        /^(nowrap|inherit)$/,
        /^(item|key|row|column|left|right|top|bottom|text)$/,
        /^onMouse(Down|Up|Click)$/,
        /^onKey(Down|Up|Press)$/,
        /^anchor(Position|El)$/,
        /^[()\[\]\-|/+•#%:]$/,
        /^\//,
        /^debug/,
    ];

    for (const reg of blacklist) {
        if (reg.test(txt)) {
            return false;
        }
    }

    return true;
}
