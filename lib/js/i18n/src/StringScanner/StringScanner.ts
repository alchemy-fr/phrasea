import {IndentationText, Node, Project, QuoteKind, SourceFile, SyntaxKind, ts} from "ts-morph";
import {normalizeKey} from "./keyNormalizer";
import {
    Rule,
    RuleConstraintType,
    SkipArgumentsRuleConstraint,
    SkipChildrenRuleConstraint,
    SubRuleRuleConstraint,
    TextNode
} from "./types";
import {defaultRules} from "./ruleSet/default";
import {removeElementsAtPositions} from "./arrayUtil";

type Options = {
    debug?: boolean;
    dryRun?: boolean;
    testFile?: string;
    rules?: Rule[];
}

type ResolvedOptions = {
    rules: Rule[];
} & Omit<Options, "rules">;

export default class StringScanner {
    options: Readonly<ResolvedOptions>;
    project: Project;

    constructor(options: Readonly<Options> = {}) {
        this.project = new Project();

        this.options = {
            ...options,
            rules: options.rules ?? defaultRules,
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

    findTextNodes(node: Node, depth: number = 0, contextRules: Rule[] = []): TextNode[] {
        if (this.options.debug) {
            console.log(`${'  '.repeat(depth)}${node.getKindName()}${(Node.isJsxElement(node) || Node.isJsxSelfClosingElement(node)) ? ` <${node.getStructure().name}>` : ''}${Node.isJsxText(node) || Node.isStringLiteral(node) ? ` = ${node.print().trim()}` : ''}`);
        }
        const textNodes: TextNode[] = [];
        const children = node.getChildren();

        const rules = this.options.rules.concat(contextRules);

        const constraints = rules.map(r => r.getConstraints(node)).flat();
        if (constraints.some(c => c.type === RuleConstraintType.Skip)) {
            return textNodes;
        }

        const childrenToSkip: number[] = (constraints.filter(c => c.type === RuleConstraintType.skipChildren) as SkipChildrenRuleConstraint[])
            .map((c) => c.positions).flat();
        if (childrenToSkip.length > 0) {
            if (this.options.debug) {
                console.log(`Skipping children: ${childrenToSkip.join(', ')}`);
            }
        }
        let filteredChildren = removeElementsAtPositions(childrenToSkip, children);

        if (Node.isCallExpression(node)) {
            const argsToSkip: number[] = (constraints.filter(c => c.type === RuleConstraintType.skipArguments) as SkipArgumentsRuleConstraint[])
                .map((c) => c.arguments).flat();
            if (argsToSkip.length > 0) {
                if (this.options.debug) {
                    console.log(`Skipping arguments: ${argsToSkip.join(', ')}`);
                }
            }
            filteredChildren = removeElementsAtPositions(argsToSkip, node
                .getChildAtIndex(2)
                .getChildren()
                .filter(c => c.getKind() !== SyntaxKind.CommaToken)
            );
        }

        const subRules = contextRules.concat((constraints
            .filter(c => c.type === RuleConstraintType.SubRule) as SubRuleRuleConstraint[])
            .map(c => c.rules).flat());

        filteredChildren.forEach(c => {
            for (const n of this.findTextNodes(c, depth + 1, subRules)) {
                textNodes.push(n);
            }
        });

        if (
            Node.isNoSubstitutionTemplateLiteral(node)
            || Node.isStringLiteral(node)
            || Node.isJsxText(node)
        ) {
            const v = node.getLiteralText().trim();
            if (v) {
                textNodes.push(node);
            }
        }

        return textNodes;
    }

}
