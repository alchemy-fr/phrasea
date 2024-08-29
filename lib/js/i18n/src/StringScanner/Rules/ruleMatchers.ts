import {FunctionDeclarationStructure, Node, SyntaxKind} from "ts-morph";
import {resolveName} from "../nodeUtils";
import {RuleMatcher} from "../types";

abstract class BlacklistRegexRuleMatcher implements RuleMatcher {
    constructor(protected regexp: RegExp[]) {
    }

    matches(node: Node): boolean {
        const name = this.getNodeValue(node);
        if (name) {
            return this.regexp.some(r => name.match(r));
        }

        return false;
    }

    protected abstract getNodeValue(node: Node): string | undefined;
}

export class FunctionCallNameRuleMatcher extends BlacklistRegexRuleMatcher {
    getNodeValue(node: Node): string | undefined {
        if (Node.isCallExpression(node)) {
            return resolveName(node.getChildAtIndex(0)).replace(/^(.+\.)+/, '');
        }
    }
}
export class ClassInstantiationNameRuleMatcher extends BlacklistRegexRuleMatcher {
    getNodeValue(node: Node): string | undefined {
        if (Node.isNewExpression(node)) {
            return node.getChildAtIndex(1).getText();
        }
    }
}

export class FunctionCallOrClassNameRuleMatcher extends BlacklistRegexRuleMatcher {
    getNodeValue(node: Node): string | undefined {
        return new FunctionCallNameRuleMatcher(this.regexp).getNodeValue(node)
            || new ClassInstantiationNameRuleMatcher(this.regexp).getNodeValue(node);
    }
}

export class FunctionDeclarationNameRuleMatcher extends BlacklistRegexRuleMatcher {
    getNodeValue(node: Node): string | undefined {
        if (Node.isFunctionDeclaration(node)) {
            return (node.getStructure() as FunctionDeclarationStructure).name;
        }
    }
}

export class FullFunctionCallNameRuleMatcher extends BlacklistRegexRuleMatcher {
    getNodeValue(node: Node): string | undefined {
        if (Node.isCallExpression(node)) {
            return resolveName(node.getChildAtIndex(0));
        }
    }
}

export class PropertyNameRuleMatcher extends BlacklistRegexRuleMatcher {
    getNodeValue(node: Node): string | undefined {
        if (Node.isPropertyAssignment(node)) {
            return resolveName(node.getNameNode());
        }
    }
}

export class JsxAttributeNameRuleMatcher extends BlacklistRegexRuleMatcher {
    getNodeValue(node: Node): string | undefined {
        if (Node.isJsxAttribute(node)) {
            return resolveName(node.getNameNode());
        }
    }
}

export class BindingNameRuleMatcher extends BlacklistRegexRuleMatcher {
    getNodeValue(node: Node): string | undefined {
        if (Node.isBindingNamed(node)) {
            const name = node.getName();
            if (!name.startsWith('{')) {
                console.log('name', name);
                return name;
            }
        }
    }
}

export class JsxAttributeOrPropertyNameRuleMatcher extends BlacklistRegexRuleMatcher {
    getNodeValue(node: Node): string | undefined {
        return new JsxAttributeNameRuleMatcher(this.regexp).getNodeValue(node)
            || new PropertyNameRuleMatcher(this.regexp).getNodeValue(node);
    }
}

export class VariableOrJsxAttributeOrPropertyNameRuleMatcher extends BlacklistRegexRuleMatcher {
    getNodeValue(node: Node): string | undefined {
        return new VariableNameRuleMatcher(this.regexp).getNodeValue(node)
            || new JsxAttributeOrPropertyNameRuleMatcher(this.regexp).getNodeValue(node)
            || new BindingNameRuleMatcher(this.regexp).getNodeValue(node)
        ;
    }
}

export class VariableNameRuleMatcher extends BlacklistRegexRuleMatcher {
    getNodeValue(node: Node): string | undefined {
        if (Node.isVariableDeclaration(node)) {
            return resolveName(node.getNameNode());
        }
    }
}

export class LiteralValueRuleMatcher extends BlacklistRegexRuleMatcher {
    getNodeValue(node: Node): string | undefined {
        if (Node.isNoSubstitutionTemplateLiteral(node)
            || Node.isStringLiteral(node)
            || Node.isJsxText(node)) {
            return node.getLiteralText().trim();
        }
    }
}

export class JsxElementNameRuleMatcher extends BlacklistRegexRuleMatcher {
    getNodeValue(node: Node): string | undefined {
        if (Node.isJsxElement(node) || Node.isJsxSelfClosingElement(node)) {
            return node.getStructure().name;
        }
    }
}

export class OneOfNodeTypeRuleMatcher implements RuleMatcher {
    constructor(private syntaxKinds: SyntaxKind[]) {
    }

    matches(node: Node): boolean {
        return this.syntaxKinds.includes(node.getKind());
    }
}
