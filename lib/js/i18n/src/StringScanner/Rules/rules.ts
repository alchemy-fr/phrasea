import {
    Rule,
    RuleConstraint,
    RuleConstraintType,
    RuleMatcher,
    SkipRuleConstraint,
    SubRuleRuleConstraint
} from "../types";
import {Node} from "ts-morph";

export class MatcherRule implements Rule {
    constructor(
        public readonly name: string,
        private readonly matcher: RuleMatcher,
        private readonly constraints: RuleConstraint[] = [{type: RuleConstraintType.Skip}] as SkipRuleConstraint[],
    ) {
    }

    getConstraints(node: Node): RuleConstraint[] {
        if (this.matcher.matches(node)) {
            return this.constraints;
        }

        return [];
    }
}

export class ChainedMatcherRule implements Rule {
    constructor(
        public readonly name: string,
        private readonly matchers: RuleMatcher[],
        private readonly constraints: RuleConstraint[] = [{type: RuleConstraintType.Skip}] as SkipRuleConstraint[],
    ) {
    }

    getConstraints(node: Node): RuleConstraint[] {
        if (this.matchers[0].matches(node)) {
            if (this.matchers.length > 1) {
                return [
                    {
                        type: RuleConstraintType.SubRule,
                        rules: [
                            new ChainedMatcherRule(
                                this.name,
                                this.matchers.slice(1),
                                this.constraints,
                            )
                        ]
                    } as SubRuleRuleConstraint
                ];
            }

            return this.constraints;
        }

        return [];
    }
}
