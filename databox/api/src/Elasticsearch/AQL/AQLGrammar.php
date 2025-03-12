<?php

namespace App\Elasticsearch\AQL;

use hafriedlander\Peg\Parser;

class AQLGrammar extends Parser\Basic
{
/* main: e:expression */
protected $match_main_typestack = ['main'];
function match_main($stack = []) {
	$matchrule = 'main';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$key = 'match_'.'expression'; $pos = $this->pos;
	$subres = $this->packhas($key, $pos)
		? $this->packread($key, $pos)
		: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
	if ($subres !== \false) {
		$this->store($result, $subres, "e");
		return $this->finalise($result);
	}
	else { return \false; }
}

public function main__finalise (&$result) {
        $result['data'] = $result['e']['data'];
        unset($result['e']);
    }

/* expression: left:and_expression (] "OR" ] right:and_expression ) * */
protected $match_expression_typestack = ['expression'];
function match_expression($stack = []) {
	$matchrule = 'expression';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5000 = \null;
	do {
		$key = 'match_'.'and_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_5000 = \false; break; }
		while (\true) {
			$res_4999 = $result;
			$pos_4999 = $this->pos;
			$_4998 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_4998 = \false; break; }
				if (($subres = $this->literal('OR')) !== \false) { $result["text"] .= $subres; }
				else { $_4998 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_4998 = \false; break; }
				$key = 'match_'.'and_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_4998 = \false; break; }
				$_4998 = \true; break;
			}
			while(\false);
			if($_4998 === \false) {
				$result = $res_4999;
				$this->setPos($pos_4999);
				unset($res_4999, $pos_4999);
				break;
			}
		}
		$_5000 = \true; break;
	}
	while(\false);
	if($_5000 === \true) { return $this->finalise($result); }
	if($_5000 === \false) { return \false; }
}

public function expression__finalise (&$result) {
        $result['operator'] = 'OR';
        $conditions = [$result['left']['data']];
        if (isset($result['right']['_matchrule'])) {
            $conditions[] = $result['right']['data'];
        } else {
            foreach ($result['right'] ?? [] as $right) {
                $conditions[] = $right['data'];
            }
        }
        unset($result['left'], $result['right']);
        if (count($conditions) === 1) {
            $result['data'] = $conditions[0];
            return;
        }
        $result['data'] = [
            'type' => 'expression',
            'operator' => 'OR',
            'conditions' => $conditions,
        ];
    }

/* and_expression: left:condition (] "AND" ] right:condition ) * */
protected $match_and_expression_typestack = ['and_expression'];
function match_and_expression($stack = []) {
	$matchrule = 'and_expression';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5009 = \null;
	do {
		$key = 'match_'.'condition'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_5009 = \false; break; }
		while (\true) {
			$res_5008 = $result;
			$pos_5008 = $this->pos;
			$_5007 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_5007 = \false; break; }
				if (($subres = $this->literal('AND')) !== \false) { $result["text"] .= $subres; }
				else { $_5007 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_5007 = \false; break; }
				$key = 'match_'.'condition'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_5007 = \false; break; }
				$_5007 = \true; break;
			}
			while(\false);
			if($_5007 === \false) {
				$result = $res_5008;
				$this->setPos($pos_5008);
				unset($res_5008, $pos_5008);
				break;
			}
		}
		$_5009 = \true; break;
	}
	while(\false);
	if($_5009 === \true) { return $this->finalise($result); }
	if($_5009 === \false) { return \false; }
}

public function and_expression__finalise (&$result) {
        $conditions = [$result['left']['data']];
        if (isset($result['right']['_matchrule'])) {
            $conditions[] = $result['right']['data'];
        } else {
            foreach ($result['right'] ?? [] as $right) {
                $conditions[] = $right['data'];
            }
        }
        unset($result['left'], $result['right']);
        if (count($conditions) === 1) {
            $result['data'] = $conditions[0];
            return;
        }
        $result['data'] = [
            'type' => 'expression',
            'operator' => 'AND',
            'conditions' => $conditions,
        ];
    }

/* condition: "(" > e:expression > ")"
    | e:not_expression
    | e:criteria */
protected $match_condition_typestack = ['condition'];
function match_condition($stack = []) {
	$matchrule = 'condition';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5024 = \null;
	do {
		$res_5011 = $result;
		$pos_5011 = $this->pos;
		$_5017 = \null;
		do {
			if (\substr($this->string, $this->pos, 1) === '(') {
				$this->addPos(1);
				$result["text"] .= '(';
			}
			else { $_5017 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$key = 'match_'.'expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
			}
			else { $_5017 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			if (\substr($this->string, $this->pos, 1) === ')') {
				$this->addPos(1);
				$result["text"] .= ')';
			}
			else { $_5017 = \false; break; }
			$_5017 = \true; break;
		}
		while(\false);
		if($_5017 === \true) { $_5024 = \true; break; }
		$result = $res_5011;
		$this->setPos($pos_5011);
		$_5022 = \null;
		do {
			$res_5019 = $result;
			$pos_5019 = $this->pos;
			$key = 'match_'.'not_expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
				$_5022 = \true; break;
			}
			$result = $res_5019;
			$this->setPos($pos_5019);
			$key = 'match_'.'criteria'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
				$_5022 = \true; break;
			}
			$result = $res_5019;
			$this->setPos($pos_5019);
			$_5022 = \false; break;
		}
		while(\false);
		if($_5022 === \true) { $_5024 = \true; break; }
		$result = $res_5011;
		$this->setPos($pos_5011);
		$_5024 = \false; break;
	}
	while(\false);
	if($_5024 === \true) { return $this->finalise($result); }
	if($_5024 === \false) { return \false; }
}

public function condition__finalise (&$result) {
        $result['data'] = $result['e']['data'];
        unset($result['e']);
    }

/* not_expression: "NOT" __ e:expression */
protected $match_not_expression_typestack = ['not_expression'];
function match_not_expression($stack = []) {
	$matchrule = 'not_expression';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5029 = \null;
	do {
		if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
		else { $_5029 = \false; break; }
		$key = 'match_'.'__'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_5029 = \false; break; }
		$key = 'match_'.'expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "e"); }
		else { $_5029 = \false; break; }
		$_5029 = \true; break;
	}
	while(\false);
	if($_5029 === \true) { return $this->finalise($result); }
	if($_5029 === \false) { return \false; }
}

public function not_expression__finalise (&$result) {
        $result['data'] = [
            'type' => 'expression',
            'operator' => 'NOT',
            'conditions' => [$result['e']['data']],
        ];
        unset($result['e']);
    }

/* criteria: field:field op:operator */
protected $match_criteria_typestack = ['criteria'];
function match_criteria($stack = []) {
	$matchrule = 'criteria';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5033 = \null;
	do {
		$key = 'match_'.'field'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "field");
		}
		else { $_5033 = \false; break; }
		$key = 'match_'.'operator'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "op");
		}
		else { $_5033 = \false; break; }
		$_5033 = \true; break;
	}
	while(\false);
	if($_5033 === \true) { return $this->finalise($result); }
	if($_5033 === \false) { return \false; }
}

public function criteria__finalise (&$result) {
        $result['data'] = [
            'type' => 'criteria',
            'leftOperand' => $result['field']['data'],
            ...$result['op']['data'],
        ];
        unset($result['field'], $result['op']);
    }

/* builtin_field: "@" keyword */
protected $match_builtin_field_typestack = ['builtin_field'];
function match_builtin_field($stack = []) {
	$matchrule = 'builtin_field';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5037 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '@') {
			$this->addPos(1);
			$result["text"] .= '@';
		}
		else { $_5037 = \false; break; }
		$key = 'match_'.'keyword'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_5037 = \false; break; }
		$_5037 = \true; break;
	}
	while(\false);
	if($_5037 === \true) { return $this->finalise($result); }
	if($_5037 === \false) { return \false; }
}

public function builtin_field__finalise (&$result) {
        $result['data'] = ['field' => $result['text']];
    }

/* field_name: keyword */
protected $match_field_name_typestack = ['field_name'];
function match_field_name($stack = []) {
	$matchrule = 'field_name';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$key = 'match_'.'keyword'; $pos = $this->pos;
	$subres = $this->packhas($key, $pos)
		? $this->packread($key, $pos)
		: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
	if ($subres !== \false) {
		$this->store($result, $subres);
		return $this->finalise($result);
	}
	else { return \false; }
}

public function field_name__finalise (&$result) {
        $result['data'] = ['field' => $result['text']];
    }

/* field: f:builtin_field | f:field_name */
protected $match_field_typestack = ['field'];
function match_field($stack = []) {
	$matchrule = 'field';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5043 = \null;
	do {
		$res_5040 = $result;
		$pos_5040 = $this->pos;
		$key = 'match_'.'builtin_field'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "f");
			$_5043 = \true; break;
		}
		$result = $res_5040;
		$this->setPos($pos_5040);
		$key = 'match_'.'field_name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "f");
			$_5043 = \true; break;
		}
		$result = $res_5040;
		$this->setPos($pos_5040);
		$_5043 = \false; break;
	}
	while(\false);
	if($_5043 === \true) { return $this->finalise($result); }
	if($_5043 === \false) { return \false; }
}

public function field__finalise (&$result) {
        $result['data'] = $result['f']['data'];
        unset($result['f']);
    }

/* boolean: "true" | "false" */
protected $match_boolean_typestack = ['boolean'];
function match_boolean($stack = []) {
	$matchrule = 'boolean';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5048 = \null;
	do {
		$res_5045 = $result;
		$pos_5045 = $this->pos;
		if (($subres = $this->literal('true')) !== \false) {
			$result["text"] .= $subres;
			$_5048 = \true; break;
		}
		$result = $res_5045;
		$this->setPos($pos_5045);
		if (($subres = $this->literal('false')) !== \false) {
			$result["text"] .= $subres;
			$_5048 = \true; break;
		}
		$result = $res_5045;
		$this->setPos($pos_5045);
		$_5048 = \false; break;
	}
	while(\false);
	if($_5048 === \true) { return $this->finalise($result); }
	if($_5048 === \false) { return \false; }
}

public function boolean__finalise (&$result) {
        $result['data'] = $result['text'] === 'true';
    }

/* operator: ] op:between_operator | ] op:in_operator | ] op:ending_operator | > op:simple_operator */
protected $match_operator_typestack = ['operator'];
function match_operator($stack = []) {
	$matchrule = 'operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5073 = \null;
	do {
		$res_5050 = $result;
		$pos_5050 = $this->pos;
		$_5053 = \null;
		do {
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_5053 = \false; break; }
			$key = 'match_'.'between_operator'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "op");
			}
			else { $_5053 = \false; break; }
			$_5053 = \true; break;
		}
		while(\false);
		if($_5053 === \true) { $_5073 = \true; break; }
		$result = $res_5050;
		$this->setPos($pos_5050);
		$_5071 = \null;
		do {
			$res_5055 = $result;
			$pos_5055 = $this->pos;
			$_5058 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_5058 = \false; break; }
				$key = 'match_'.'in_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "op");
				}
				else { $_5058 = \false; break; }
				$_5058 = \true; break;
			}
			while(\false);
			if($_5058 === \true) { $_5071 = \true; break; }
			$result = $res_5055;
			$this->setPos($pos_5055);
			$_5069 = \null;
			do {
				$res_5060 = $result;
				$pos_5060 = $this->pos;
				$_5063 = \null;
				do {
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					else { $_5063 = \false; break; }
					$key = 'match_'.'ending_operator'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres, "op");
					}
					else { $_5063 = \false; break; }
					$_5063 = \true; break;
				}
				while(\false);
				if($_5063 === \true) { $_5069 = \true; break; }
				$result = $res_5060;
				$this->setPos($pos_5060);
				$_5067 = \null;
				do {
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$key = 'match_'.'simple_operator'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres, "op");
					}
					else { $_5067 = \false; break; }
					$_5067 = \true; break;
				}
				while(\false);
				if($_5067 === \true) { $_5069 = \true; break; }
				$result = $res_5060;
				$this->setPos($pos_5060);
				$_5069 = \false; break;
			}
			while(\false);
			if($_5069 === \true) { $_5071 = \true; break; }
			$result = $res_5055;
			$this->setPos($pos_5055);
			$_5071 = \false; break;
		}
		while(\false);
		if($_5071 === \true) { $_5073 = \true; break; }
		$result = $res_5050;
		$this->setPos($pos_5050);
		$_5073 = \false; break;
	}
	while(\false);
	if($_5073 === \true) { return $this->finalise($result); }
	if($_5073 === \false) { return \false; }
}

public function operator__finalise (&$result) {
        $result['data'] = $result['op']['data'];
        unset($result['op']);
    }

/* between_operator: "BETWEEN" ] left:value ] "AND" ] right:value */
protected $match_between_operator_typestack = ['between_operator'];
function match_between_operator($stack = []) {
	$matchrule = 'between_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5082 = \null;
	do {
		if (($subres = $this->literal('BETWEEN')) !== \false) { $result["text"] .= $subres; }
		else { $_5082 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_5082 = \false; break; }
		$key = 'match_'.'value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_5082 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_5082 = \false; break; }
		if (($subres = $this->literal('AND')) !== \false) { $result["text"] .= $subres; }
		else { $_5082 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_5082 = \false; break; }
		$key = 'match_'.'value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "right");
		}
		else { $_5082 = \false; break; }
		$_5082 = \true; break;
	}
	while(\false);
	if($_5082 === \true) { return $this->finalise($result); }
	if($_5082 === \false) { return \false; }
}

public function between_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => 'BETWEEN',
            'rightOperand' => [$result['left']['data'], $result['right']['data']],
        ];
        unset($result['left'], $result['right']);
    }

/* ending_operator: ("IS" ] "MISSING") | "EXISTS" */
protected $match_ending_operator_typestack = ['ending_operator'];
function match_ending_operator($stack = []) {
	$matchrule = 'ending_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5091 = \null;
	do {
		$res_5084 = $result;
		$pos_5084 = $this->pos;
		$_5088 = \null;
		do {
			if (($subres = $this->literal('IS')) !== \false) { $result["text"] .= $subres; }
			else { $_5088 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_5088 = \false; break; }
			if (($subres = $this->literal('MISSING')) !== \false) { $result["text"] .= $subres; }
			else { $_5088 = \false; break; }
			$_5088 = \true; break;
		}
		while(\false);
		if($_5088 === \true) { $_5091 = \true; break; }
		$result = $res_5084;
		$this->setPos($pos_5084);
		if (($subres = $this->literal('EXISTS')) !== \false) {
			$result["text"] .= $subres;
			$_5091 = \true; break;
		}
		$result = $res_5084;
		$this->setPos($pos_5084);
		$_5091 = \false; break;
	}
	while(\false);
	if($_5091 === \true) { return $this->finalise($result); }
	if($_5091 === \false) { return \false; }
}

public function ending_operator__finalise (&$result) {
        $assoc = [
            'IS_MISSING' => 'MISSING',
            'EXISTS' => 'EXISTS',
        ];
        $result['data'] = [
            'operator' => $assoc[preg_replace('#\s+#', '_', $result['text'])],
        ];
    }

/* in_operator: not:("NOT" ] )? "IN" > "(" > first:value (> "," > others:value)* > ")" */
protected $match_in_operator_typestack = ['in_operator'];
function match_in_operator($stack = []) {
	$matchrule = 'in_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5110 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_5096 = $result;
		$pos_5096 = $this->pos;
		$_5095 = \null;
		do {
			if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
			else { $_5095 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_5095 = \false; break; }
			$_5095 = \true; break;
		}
		while(\false);
		if($_5095 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		if($_5095 === \false) {
			$result = $res_5096;
			$this->setPos($pos_5096);
			unset($res_5096, $pos_5096);
		}
		if (($subres = $this->literal('IN')) !== \false) { $result["text"] .= $subres; }
		else { $_5110 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_5110 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "first");
		}
		else { $_5110 = \false; break; }
		while (\true) {
			$res_5107 = $result;
			$pos_5107 = $this->pos;
			$_5106 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else { $_5106 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "others");
				}
				else { $_5106 = \false; break; }
				$_5106 = \true; break;
			}
			while(\false);
			if($_5106 === \false) {
				$result = $res_5107;
				$this->setPos($pos_5107);
				unset($res_5107, $pos_5107);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_5110 = \false; break; }
		$_5110 = \true; break;
	}
	while(\false);
	if($_5110 === \true) { return $this->finalise($result); }
	if($_5110 === \false) { return \false; }
}

public function in_operator__finalise (&$result) {
        $values = [$result['first']['data']];
        if (isset($result['others']['_matchrule'])) {
            $values[] = $result['others']['data'];
        } else {
            foreach ($result['others'] ?? [] as $v) {
                $values[] = $v['data'];
            }
        }
        $result['data'] = [
            'operator' => isset($result['not']) ? 'NOT_IN' : 'IN',
            'rightOperand' => $values,
        ];
        unset($result['first'], $result['others']);
    }

/* simple_operator: op:/([<>]?=|!=|[<>]|CONTAINS|MATCHES|STARTS\s+WITH)/ > v:value */
protected $match_simple_operator_typestack = ['simple_operator'];
function match_simple_operator($stack = []) {
	$matchrule = 'simple_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5115 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		if (($subres = $this->rx('/([<>]?=|!=|[<>]|CONTAINS|MATCHES|STARTS\s+WITH)/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		else {
			$result = \array_pop($stack);
			$_5115 = \false; break;
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_5115 = \false; break; }
		$_5115 = \true; break;
	}
	while(\false);
	if($_5115 === \true) { return $this->finalise($result); }
	if($_5115 === \false) { return \false; }
}

public function simple_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => preg_replace('#\s+#', '_', $result['op']['text']),
            'rightOperand' => $result['v']['data'],
        ];
        unset($result['op'], $result['v']);
    }

/* value: v:number | v:quoted_string | v:boolean | v:field */
protected $match_value_typestack = ['value'];
function match_value($stack = []) {
	$matchrule = 'value';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5128 = \null;
	do {
		$res_5117 = $result;
		$pos_5117 = $this->pos;
		$key = 'match_'.'number'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_5128 = \true; break;
		}
		$result = $res_5117;
		$this->setPos($pos_5117);
		$_5126 = \null;
		do {
			$res_5119 = $result;
			$pos_5119 = $this->pos;
			$key = 'match_'.'quoted_string'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "v");
				$_5126 = \true; break;
			}
			$result = $res_5119;
			$this->setPos($pos_5119);
			$_5124 = \null;
			do {
				$res_5121 = $result;
				$pos_5121 = $this->pos;
				$key = 'match_'.'boolean'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_5124 = \true; break;
				}
				$result = $res_5121;
				$this->setPos($pos_5121);
				$key = 'match_'.'field'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_5124 = \true; break;
				}
				$result = $res_5121;
				$this->setPos($pos_5121);
				$_5124 = \false; break;
			}
			while(\false);
			if($_5124 === \true) { $_5126 = \true; break; }
			$result = $res_5119;
			$this->setPos($pos_5119);
			$_5126 = \false; break;
		}
		while(\false);
		if($_5126 === \true) { $_5128 = \true; break; }
		$result = $res_5117;
		$this->setPos($pos_5117);
		$_5128 = \false; break;
	}
	while(\false);
	if($_5128 === \true) { return $this->finalise($result); }
	if($_5128 === \false) { return \false; }
}

public function value__finalise (&$result) {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

/* int: /[0-9]+/ */
protected $match_int_typestack = ['int'];
function match_int($stack = []) {
	$matchrule = 'int';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/[0-9]+/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}

public function int__finalise (&$result) {
        $result['data'] = (int) $result['text'];
    }

/* decimal: int? "." int */
protected $match_decimal_typestack = ['decimal'];
function match_decimal($stack = []) {
	$matchrule = 'decimal';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5134 = \null;
	do {
		$res_5131 = $result;
		$pos_5131 = $this->pos;
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else {
			$result = $res_5131;
			$this->setPos($pos_5131);
			unset($res_5131, $pos_5131);
		}
		if (\substr($this->string, $this->pos, 1) === '.') {
			$this->addPos(1);
			$result["text"] .= '.';
		}
		else { $_5134 = \false; break; }
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_5134 = \false; break; }
		$_5134 = \true; break;
	}
	while(\false);
	if($_5134 === \true) { return $this->finalise($result); }
	if($_5134 === \false) { return \false; }
}

public function decimal__finalise (&$result) {
        $result['data'] = (float) $result['text'];
    }

/* quoted_string: /"[^"]*"/ */
protected $match_quoted_string_typestack = ['quoted_string'];
function match_quoted_string($stack = []) {
	$matchrule = 'quoted_string';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/"[^"]*"/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}

public function quoted_string__finalise (&$result) {
        $result['data'] = ['literal' => substr($result['text'], 1, -1)];
    }

/* number: v:int | v:decimal */
protected $match_number_typestack = ['number'];
function match_number($stack = []) {
	$matchrule = 'number';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5140 = \null;
	do {
		$res_5137 = $result;
		$pos_5137 = $this->pos;
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_5140 = \true; break;
		}
		$result = $res_5137;
		$this->setPos($pos_5137);
		$key = 'match_'.'decimal'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_5140 = \true; break;
		}
		$result = $res_5137;
		$this->setPos($pos_5137);
		$_5140 = \false; break;
	}
	while(\false);
	if($_5140 === \true) { return $this->finalise($result); }
	if($_5140 === \false) { return \false; }
}

public function number__finalise (&$result) {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

/* alpha: /[a-zA-Z_]/ */
protected $match_alpha_typestack = ['alpha'];
function match_alpha($stack = []) {
	$matchrule = 'alpha';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/[a-zA-Z_]/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}


/* alphanum: /[a-zA-Z_0-9-]/ */
protected $match_alphanum_typestack = ['alphanum'];
function match_alphanum($stack = []) {
	$matchrule = 'alphanum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/[a-zA-Z_0-9-]/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}


/* keyword: alpha alphanum* */
protected $match_keyword_typestack = ['keyword'];
function match_keyword($stack = []) {
	$matchrule = 'keyword';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_5146 = \null;
	do {
		$key = 'match_'.'alpha'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_5146 = \false; break; }
		while (\true) {
			$res_5145 = $result;
			$pos_5145 = $this->pos;
			$key = 'match_'.'alphanum'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else {
				$result = $res_5145;
				$this->setPos($pos_5145);
				unset($res_5145, $pos_5145);
				break;
			}
		}
		$_5146 = \true; break;
	}
	while(\false);
	if($_5146 === \true) { return $this->finalise($result); }
	if($_5146 === \false) { return \false; }
}



}