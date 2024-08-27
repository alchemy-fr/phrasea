import {IndentationText, Node, Project, QuoteKind, SourceFile, SyntaxKind} from "ts-morph";
import {normalizeKey} from "./keyNormalizer";
import {Rule, TextNode} from "./types";
import {
    isAllowedAttribute,
    isAllowedFunctionName,
    isAllowedText,
    isAllowedVariableName,
    resolveName
} from "./nodeUtils";
import {defaultRules} from "./defaultRules";

type Options = {
    debug?: boolean;
    dryRun?: boolean;
    testFile?: string;
    exclusionRules?: Rule[];
}

type ResolvedOptions = {
    exclusionRules: Rule[];
} & Omit<Options, "exclusionRules">;

export default class StringScanner {
    options: Readonly<ResolvedOptions>;
    project: Project;

    constructor(options: Readonly<Options> = {}) {
        this.project = new Project();

        this.options = {
            ...options,
            exclusionRules: options.exclusionRules ?? defaultRules,
        };

        this.project.manipulationSettings.set({
            quoteKind: QuoteKind.Single,
            indentationText: IndentationText.FourSpaces,
        });

        if (this.options.testFile && this.options.debug) {
            this.project.addSourceFileAtPath(this.options.testFile);
        } else {
            const files = ['src/**/*{.ts,.tsx}'];
            if (this.options.testFile) {
                files.push(`!${this.options.testFile}`)
            }
            this.project.addSourceFilesAtPaths(files);
        }
    }

    run(): void {
        const sourceFiles = this.project.getSourceFiles();

        sourceFiles.forEach((sourceFile: SourceFile) => {
            let hasTranslation = false;
            const fns = sourceFile.getFunctions();

            fns.forEach(fn => {
                const textNodes = this.findTextNodes(fn);

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

                if (this.options.dryRun) {
                    console.log(sourceFile.print());
                } else {
                    sourceFile.save();
                }
            }
        });
    }

    findTextNodes(node: Node, depth: number = 0): TextNode[] {
        if (this.options.debug) {
            console.log(`${'  '.repeat(depth)}${node.getKindName()}${Node.isJsxText(node) || Node.isStringLiteral(node) ? ` = ${node.print().trim()}` : ''}`);
        }
        const textNodes: TextNode[] = [];

        let children = node.getChildren();

        if (Node.isPropertyAssignment(node)) {
            children = children.slice(1);
        }

        children.forEach(c => {
            if (this.options.exclusionRules.some(r => r.matches(c))) {
                return;
            }

            for (const n of this.findTextNodes(c, depth + 1)) {
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

}
