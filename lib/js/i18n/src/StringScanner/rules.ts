import {Node, SyntaxKind} from "ts-morph";
import {resolveName} from "./nodeUtils";
import {Rule} from "./types";

abstract class BlacklistRegexRule implements Rule {
    constructor(protected regexp: RegExp[]) {
    }

    matches(node: Node): boolean {
        const name = this.getNodeValue(node);
        if (name) {
            return this.regexp.some(r => name.match(r));
        }

        return false;
    }

    abstract getNodeValue(node: Node): string | undefined;
}

export class FunctionNameRule extends BlacklistRegexRule {
    getNodeValue(node: Node): string | undefined {
        if (Node.isCallExpression(node)) {
            return resolveName(node.getChildAtIndex(0)).replace(/^(.+\.)+/, '');
        }
    }
}

export class PropertyNameRule extends BlacklistRegexRule {
    getNodeValue(node: Node): string | undefined {
        if (Node.isPropertyAssignment(node)) {
            return resolveName(node.getNameNode());
        }
    }
}

export class JsxAttributeNameRule extends BlacklistRegexRule {
    getNodeValue(node: Node): string | undefined {
        if (Node.isJsxAttribute(node)) {
            return resolveName(node.getNameNode());
        }
    }
}

export class JsxAttributeOrPropertyNameRule extends BlacklistRegexRule {
    getNodeValue(node: Node): string | undefined {
        return new JsxAttributeNameRule(this.regexp).getNodeValue(node)
            || new PropertyNameRule(this.regexp).getNodeValue(node);
    }
}

export class VariableNameRule extends BlacklistRegexRule {
    getNodeValue(node: Node): string | undefined {
        if (Node.isVariableDeclaration(node)) {
            return resolveName(node.getNameNode());
        }
    }
}

export class JsxElementNameRule extends BlacklistRegexRule {
    getNodeValue(node: Node): string | undefined {
        if (Node.isJsxElement(node)) {
            return node.getStructure().name;
        }
    }
}

export class OneOfNodeTypeRule implements Rule {
    constructor(private syntaxKinds: SyntaxKind[]) {
    }

    matches(node: Node): boolean {
        return this.syntaxKinds.includes(node.getKind());
    }

}
