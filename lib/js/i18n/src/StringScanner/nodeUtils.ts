import {CallExpression, Node, SyntaxKind, SyntaxList} from "ts-morph";
import {removeElementsAtPositions} from "./arrayUtil";

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

export function getFilteredFunctionCallArguments(node: CallExpression, args: number[]): Node[] {
    const newChildren: Node[] = [];

    node.getChildren()
        .forEach(c => {
            if (c.getKind() === SyntaxKind.SyntaxList) {
                removeElementsAtPositions(args, c.getChildren().filter(
                    c => c.getKind() !== SyntaxKind.CommaToken
                )).forEach(c => newChildren.push(c));
            } else {
                newChildren.push(c);
            }
        });

    return newChildren;
}

export function getCallExpressionSyntaxList(node: CallExpression): SyntaxList {
    return node.getChildren().find(c => c.getKind() === SyntaxKind.SyntaxList) as SyntaxList;
}
