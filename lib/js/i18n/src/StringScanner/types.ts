import {JsxText, Node, NoSubstitutionTemplateLiteral, StringLiteral} from "ts-morph";

export type TextNode = JsxText | StringLiteral | NoSubstitutionTemplateLiteral;

export interface Rule {
    matches(node: Node): boolean;
}
