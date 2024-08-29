import {JsxText, Node, NoSubstitutionTemplateLiteral, StringLiteral} from "ts-morph";

export type TextNode = JsxText | StringLiteral | NoSubstitutionTemplateLiteral;

export interface RuleMatcher {
    matches(node: Node): boolean;
}

export enum RuleConstraintType {
    Skip = "skip",
    skipChildren = "skip_children",
    skipArguments = "skip_arguments",
    SubRule = "sub_rule",
}

export interface RuleConstraint {
    type: RuleConstraintType;
}

export interface SkipRuleConstraint extends RuleConstraint {
    type: RuleConstraintType.Skip;
}

export interface SkipChildrenRuleConstraint extends RuleConstraint {
    type: RuleConstraintType.skipChildren;
    positions: number[];
}

export interface SkipArgumentsRuleConstraint extends RuleConstraint {
    type: RuleConstraintType.skipArguments;
    arguments: number[];
}

export interface SubRuleRuleConstraint extends RuleConstraint {
    type: RuleConstraintType.SubRule;
    rules: Rule[];
}

export interface Rule {
    getConstraints(node: Node): RuleConstraint[];
}
