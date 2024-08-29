import {Node} from "ts-morph";

export function resolveName(node: Node): string {
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
