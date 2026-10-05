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
	$_19467 = \null;
	do {
		$key = 'match_'.'and_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_19467 = \false; break; }
		while (\true) {
			$res_19466 = $result;
			$pos_19466 = $this->pos;
			$_19465 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_19465 = \false; break; }
				if (($subres = $this->literal('OR')) !== \false) { $result["text"] .= $subres; }
				else { $_19465 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_19465 = \false; break; }
				$key = 'match_'.'and_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_19465 = \false; break; }
				$_19465 = \true; break;
			}
			while(\false);
			if($_19465 === \false) {
				$result = $res_19466;
				$this->setPos($pos_19466);
				unset($res_19466, $pos_19466);
				break;
			}
		}
		$_19467 = \true; break;
	}
	while(\false);
	if($_19467 === \true) { return $this->finalise($result); }
	if($_19467 === \false) { return \false; }
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
	$_19476 = \null;
	do {
		$key = 'match_'.'condition'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_19476 = \false; break; }
		while (\true) {
			$res_19475 = $result;
			$pos_19475 = $this->pos;
			$_19474 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_19474 = \false; break; }
				if (($subres = $this->literal('AND')) !== \false) { $result["text"] .= $subres; }
				else { $_19474 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_19474 = \false; break; }
				$key = 'match_'.'condition'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_19474 = \false; break; }
				$_19474 = \true; break;
			}
			while(\false);
			if($_19474 === \false) {
				$result = $res_19475;
				$this->setPos($pos_19475);
				unset($res_19475, $pos_19475);
				break;
			}
		}
		$_19476 = \true; break;
	}
	while(\false);
	if($_19476 === \true) { return $this->finalise($result); }
	if($_19476 === \false) { return \false; }
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

/* condition: '(' > e:expression > ')'
    | e:not_expression
    | e:criteria */
protected $match_condition_typestack = ['condition'];
function match_condition($stack = []) {
	$matchrule = 'condition';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19491 = \null;
	do {
		$res_19478 = $result;
		$pos_19478 = $this->pos;
		$_19484 = \null;
		do {
			if (\substr($this->string, $this->pos, 1) === '(') {
				$this->addPos(1);
				$result["text"] .= '(';
			}
			else { $_19484 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$key = 'match_'.'expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
			}
			else { $_19484 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			if (\substr($this->string, $this->pos, 1) === ')') {
				$this->addPos(1);
				$result["text"] .= ')';
			}
			else { $_19484 = \false; break; }
			$_19484 = \true; break;
		}
		while(\false);
		if($_19484 === \true) { $_19491 = \true; break; }
		$result = $res_19478;
		$this->setPos($pos_19478);
		$_19489 = \null;
		do {
			$res_19486 = $result;
			$pos_19486 = $this->pos;
			$key = 'match_'.'not_expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
				$_19489 = \true; break;
			}
			$result = $res_19486;
			$this->setPos($pos_19486);
			$key = 'match_'.'criteria'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
				$_19489 = \true; break;
			}
			$result = $res_19486;
			$this->setPos($pos_19486);
			$_19489 = \false; break;
		}
		while(\false);
		if($_19489 === \true) { $_19491 = \true; break; }
		$result = $res_19478;
		$this->setPos($pos_19478);
		$_19491 = \false; break;
	}
	while(\false);
	if($_19491 === \true) { return $this->finalise($result); }
	if($_19491 === \false) { return \false; }
}

public function condition__finalise (&$result) {
        $result['data'] = $result['e']['data'];
        unset($result['e']);
    }

/* not_expression: "NOT" ] e:expression */
protected $match_not_expression_typestack = ['not_expression'];
function match_not_expression($stack = []) {
	$matchrule = 'not_expression';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19496 = \null;
	do {
		if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
		else { $_19496 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_19496 = \false; break; }
		$key = 'match_'.'expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "e"); }
		else { $_19496 = \false; break; }
		$_19496 = \true; break;
	}
	while(\false);
	if($_19496 === \true) { return $this->finalise($result); }
	if($_19496 === \false) { return \false; }
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
	$_19500 = \null;
	do {
		$key = 'match_'.'field'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "field");
		}
		else { $_19500 = \false; break; }
		$key = 'match_'.'operator'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "op");
		}
		else { $_19500 = \false; break; }
		$_19500 = \true; break;
	}
	while(\false);
	if($_19500 === \true) { return $this->finalise($result); }
	if($_19500 === \false) { return \false; }
}

public function criteria__finalise (&$result) {
        $result['data'] = [
            'type' => 'criteria',
            'leftOperand' => $result['field']['data'],
            ...$result['op']['data'],
        ];
        unset($result['field'], $result['op']);
    }

/* builtin_field: "@" identifier */
protected $match_builtin_field_typestack = ['builtin_field'];
function match_builtin_field($stack = []) {
	$matchrule = 'builtin_field';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19504 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '@') {
			$this->addPos(1);
			$result["text"] .= '@';
		}
		else { $_19504 = \false; break; }
		$key = 'match_'.'identifier'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_19504 = \false; break; }
		$_19504 = \true; break;
	}
	while(\false);
	if($_19504 === \true) { return $this->finalise($result); }
	if($_19504 === \false) { return \false; }
}

public function builtin_field__finalise (&$result) {
        $result['data'] = ['field' => $result['text']];
    }

/* field_name: identifier */
protected $match_field_name_typestack = ['field_name'];
function match_field_name($stack = []) {
	$matchrule = 'field_name';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$key = 'match_'.'identifier'; $pos = $this->pos;
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
	$_19510 = \null;
	do {
		$res_19507 = $result;
		$pos_19507 = $this->pos;
		$key = 'match_'.'builtin_field'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "f");
			$_19510 = \true; break;
		}
		$result = $res_19507;
		$this->setPos($pos_19507);
		$key = 'match_'.'field_name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "f");
			$_19510 = \true; break;
		}
		$result = $res_19507;
		$this->setPos($pos_19507);
		$_19510 = \false; break;
	}
	while(\false);
	if($_19510 === \true) { return $this->finalise($result); }
	if($_19510 === \false) { return \false; }
}

public function field__finalise (&$result) {
        $result['data'] = $result['f']['data'];
        unset($result['f']);
    }

/* boolean: "true" | "false" | "TRUE" | "FALSE" */
protected $match_boolean_typestack = ['boolean'];
function match_boolean($stack = []) {
	$matchrule = 'boolean';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19523 = \null;
	do {
		$res_19512 = $result;
		$pos_19512 = $this->pos;
		if (($subres = $this->literal('true')) !== \false) {
			$result["text"] .= $subres;
			$_19523 = \true; break;
		}
		$result = $res_19512;
		$this->setPos($pos_19512);
		$_19521 = \null;
		do {
			$res_19514 = $result;
			$pos_19514 = $this->pos;
			if (($subres = $this->literal('false')) !== \false) {
				$result["text"] .= $subres;
				$_19521 = \true; break;
			}
			$result = $res_19514;
			$this->setPos($pos_19514);
			$_19519 = \null;
			do {
				$res_19516 = $result;
				$pos_19516 = $this->pos;
				if (($subres = $this->literal('TRUE')) !== \false) {
					$result["text"] .= $subres;
					$_19519 = \true; break;
				}
				$result = $res_19516;
				$this->setPos($pos_19516);
				if (($subres = $this->literal('FALSE')) !== \false) {
					$result["text"] .= $subres;
					$_19519 = \true; break;
				}
				$result = $res_19516;
				$this->setPos($pos_19516);
				$_19519 = \false; break;
			}
			while(\false);
			if($_19519 === \true) { $_19521 = \true; break; }
			$result = $res_19514;
			$this->setPos($pos_19514);
			$_19521 = \false; break;
		}
		while(\false);
		if($_19521 === \true) { $_19523 = \true; break; }
		$result = $res_19512;
		$this->setPos($pos_19512);
		$_19523 = \false; break;
	}
	while(\false);
	if($_19523 === \true) { return $this->finalise($result); }
	if($_19523 === \false) { return \false; }
}

public function boolean__finalise (&$result) {
        $result['data'] = strtolower($result['text']) === 'true';
    }

/* const_null: "null" | "NULL" */
protected $match_const_null_typestack = ['const_null'];
function match_const_null($stack = []) {
	$matchrule = 'const_null';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19528 = \null;
	do {
		$res_19525 = $result;
		$pos_19525 = $this->pos;
		if (($subres = $this->literal('null')) !== \false) {
			$result["text"] .= $subres;
			$_19528 = \true; break;
		}
		$result = $res_19525;
		$this->setPos($pos_19525);
		if (($subres = $this->literal('NULL')) !== \false) {
			$result["text"] .= $subres;
			$_19528 = \true; break;
		}
		$result = $res_19525;
		$this->setPos($pos_19525);
		$_19528 = \false; break;
	}
	while(\false);
	if($_19528 === \true) { return $this->finalise($result); }
	if($_19528 === \false) { return \false; }
}

public function const_null__finalise (&$result) {
        $result['data'] = null;
    }

/* operator: ] op:between_operator | ] op:in_operator | ] op:geo_operator | ] op:ending_operator | > op:simple_operator | > op:keyword_operator */
protected $match_operator_typestack = ['operator'];
function match_operator($stack = []) {
	$matchrule = 'operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19567 = \null;
	do {
		$res_19530 = $result;
		$pos_19530 = $this->pos;
		$_19533 = \null;
		do {
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_19533 = \false; break; }
			$key = 'match_'.'between_operator'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "op");
			}
			else { $_19533 = \false; break; }
			$_19533 = \true; break;
		}
		while(\false);
		if($_19533 === \true) { $_19567 = \true; break; }
		$result = $res_19530;
		$this->setPos($pos_19530);
		$_19565 = \null;
		do {
			$res_19535 = $result;
			$pos_19535 = $this->pos;
			$_19538 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_19538 = \false; break; }
				$key = 'match_'.'in_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "op");
				}
				else { $_19538 = \false; break; }
				$_19538 = \true; break;
			}
			while(\false);
			if($_19538 === \true) { $_19565 = \true; break; }
			$result = $res_19535;
			$this->setPos($pos_19535);
			$_19563 = \null;
			do {
				$res_19540 = $result;
				$pos_19540 = $this->pos;
				$_19543 = \null;
				do {
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					else { $_19543 = \false; break; }
					$key = 'match_'.'geo_operator'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres, "op");
					}
					else { $_19543 = \false; break; }
					$_19543 = \true; break;
				}
				while(\false);
				if($_19543 === \true) { $_19563 = \true; break; }
				$result = $res_19540;
				$this->setPos($pos_19540);
				$_19561 = \null;
				do {
					$res_19545 = $result;
					$pos_19545 = $this->pos;
					$_19548 = \null;
					do {
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						else { $_19548 = \false; break; }
						$key = 'match_'.'ending_operator'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "op");
						}
						else { $_19548 = \false; break; }
						$_19548 = \true; break;
					}
					while(\false);
					if($_19548 === \true) { $_19561 = \true; break; }
					$result = $res_19545;
					$this->setPos($pos_19545);
					$_19559 = \null;
					do {
						$res_19550 = $result;
						$pos_19550 = $this->pos;
						$_19553 = \null;
						do {
							if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
							$key = 'match_'.'simple_operator'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres, "op");
							}
							else { $_19553 = \false; break; }
							$_19553 = \true; break;
						}
						while(\false);
						if($_19553 === \true) { $_19559 = \true; break; }
						$result = $res_19550;
						$this->setPos($pos_19550);
						$_19557 = \null;
						do {
							if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
							$key = 'match_'.'keyword_operator'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres, "op");
							}
							else { $_19557 = \false; break; }
							$_19557 = \true; break;
						}
						while(\false);
						if($_19557 === \true) { $_19559 = \true; break; }
						$result = $res_19550;
						$this->setPos($pos_19550);
						$_19559 = \false; break;
					}
					while(\false);
					if($_19559 === \true) { $_19561 = \true; break; }
					$result = $res_19545;
					$this->setPos($pos_19545);
					$_19561 = \false; break;
				}
				while(\false);
				if($_19561 === \true) { $_19563 = \true; break; }
				$result = $res_19540;
				$this->setPos($pos_19540);
				$_19563 = \false; break;
			}
			while(\false);
			if($_19563 === \true) { $_19565 = \true; break; }
			$result = $res_19535;
			$this->setPos($pos_19535);
			$_19565 = \false; break;
		}
		while(\false);
		if($_19565 === \true) { $_19567 = \true; break; }
		$result = $res_19530;
		$this->setPos($pos_19530);
		$_19567 = \false; break;
	}
	while(\false);
	if($_19567 === \true) { return $this->finalise($result); }
	if($_19567 === \false) { return \false; }
}

public function operator__finalise (&$result) {
        $result['data'] = $result['op']['data'];
        unset($result['op']);
    }

/* geo_operator: "WITHIN" ] (v:within_circle_operator | v:within_rectangle_operator) */
protected $match_geo_operator_typestack = ['geo_operator'];
function match_geo_operator($stack = []) {
	$matchrule = 'geo_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19578 = \null;
	do {
		if (($subres = $this->literal('WITHIN')) !== \false) { $result["text"] .= $subres; }
		else { $_19578 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_19578 = \false; break; }
		$_19576 = \null;
		do {
			$_19574 = \null;
			do {
				$res_19571 = $result;
				$pos_19571 = $this->pos;
				$key = 'match_'.'within_circle_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_19574 = \true; break;
				}
				$result = $res_19571;
				$this->setPos($pos_19571);
				$key = 'match_'.'within_rectangle_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_19574 = \true; break;
				}
				$result = $res_19571;
				$this->setPos($pos_19571);
				$_19574 = \false; break;
			}
			while(\false);
			if($_19574 === \false) { $_19576 = \false; break; }
			$_19576 = \true; break;
		}
		while(\false);
		if($_19576 === \false) { $_19578 = \false; break; }
		$_19578 = \true; break;
	}
	while(\false);
	if($_19578 === \true) { return $this->finalise($result); }
	if($_19578 === \false) { return \false; }
}

public function geo_operator__finalise (&$result) {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

/* within_circle_operator: "CIRCLE" > "(" > lat:value_expression > "," > lng:value_expression > "," > radius:value_expression > ")" */
protected $match_within_circle_operator_typestack = ['within_circle_operator'];
function match_within_circle_operator($stack = []) {
	$matchrule = 'within_circle_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19595 = \null;
	do {
		if (($subres = $this->literal('CIRCLE')) !== \false) { $result["text"] .= $subres; }
		else { $_19595 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_19595 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "lat");
		}
		else { $_19595 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_19595 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "lng");
		}
		else { $_19595 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_19595 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "radius");
		}
		else { $_19595 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_19595 = \false; break; }
		$_19595 = \true; break;
	}
	while(\false);
	if($_19595 === \true) { return $this->finalise($result); }
	if($_19595 === \false) { return \false; }
}

public function within_circle_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => 'WITHIN_CIRCLE',
            'rightOperand' => [
                $result['lat']['data'],
                $result['lng']['data'],
                $result['radius']['data'],
            ],
        ];
        unset($result['lat'], $result['lng'], $result['radius']);
    }

/* within_rectangle_operator: "RECTANGLE" > "(" > topLeftLat:value_expression > "," > topLeftLng:value_expression > "," > bottomRightLat:value_expression > "," > bottomRightLng:value_expression > ")" */
protected $match_within_rectangle_operator_typestack = ['within_rectangle_operator'];
function match_within_rectangle_operator($stack = []) {
	$matchrule = 'within_rectangle_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19616 = \null;
	do {
		if (($subres = $this->literal('RECTANGLE')) !== \false) { $result["text"] .= $subres; }
		else { $_19616 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_19616 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "topLeftLat");
		}
		else { $_19616 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_19616 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "topLeftLng");
		}
		else { $_19616 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_19616 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "bottomRightLat");
		}
		else { $_19616 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_19616 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "bottomRightLng");
		}
		else { $_19616 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_19616 = \false; break; }
		$_19616 = \true; break;
	}
	while(\false);
	if($_19616 === \true) { return $this->finalise($result); }
	if($_19616 === \false) { return \false; }
}

public function within_rectangle_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => 'WITHIN_RECTANGLE',
            'rightOperand' => [
                $result['topLeftLat']['data'],
                $result['topLeftLng']['data'],
                $result['bottomRightLat']['data'],
                $result['bottomRightLng']['data'],
            ],
        ];
        unset($result['topLeftLat'], $result['topLeftLng'], $result['bottomRightLat'], $result['bottomRightLng']);
    }

/* between_operator: not:("NOT" ])? "BETWEEN" ] left:value_expression ] "AND" ] right:value_expression */
protected $match_between_operator_typestack = ['between_operator'];
function match_between_operator($stack = []) {
	$matchrule = 'between_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19629 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_19621 = $result;
		$pos_19621 = $this->pos;
		$_19620 = \null;
		do {
			if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
			else { $_19620 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_19620 = \false; break; }
			$_19620 = \true; break;
		}
		while(\false);
		if($_19620 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		if($_19620 === \false) {
			$result = $res_19621;
			$this->setPos($pos_19621);
			unset($res_19621, $pos_19621);
		}
		if (($subres = $this->literal('BETWEEN')) !== \false) { $result["text"] .= $subres; }
		else { $_19629 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_19629 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_19629 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_19629 = \false; break; }
		if (($subres = $this->literal('AND')) !== \false) { $result["text"] .= $subres; }
		else { $_19629 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_19629 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "right");
		}
		else { $_19629 = \false; break; }
		$_19629 = \true; break;
	}
	while(\false);
	if($_19629 === \true) { return $this->finalise($result); }
	if($_19629 === \false) { return \false; }
}

public function between_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => isset($result['not']) ? 'NOT_BETWEEN' : 'BETWEEN',
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
	$_19638 = \null;
	do {
		$res_19631 = $result;
		$pos_19631 = $this->pos;
		$_19635 = \null;
		do {
			if (($subres = $this->literal('IS')) !== \false) { $result["text"] .= $subres; }
			else { $_19635 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_19635 = \false; break; }
			if (($subres = $this->literal('MISSING')) !== \false) { $result["text"] .= $subres; }
			else { $_19635 = \false; break; }
			$_19635 = \true; break;
		}
		while(\false);
		if($_19635 === \true) { $_19638 = \true; break; }
		$result = $res_19631;
		$this->setPos($pos_19631);
		if (($subres = $this->literal('EXISTS')) !== \false) {
			$result["text"] .= $subres;
			$_19638 = \true; break;
		}
		$result = $res_19631;
		$this->setPos($pos_19631);
		$_19638 = \false; break;
	}
	while(\false);
	if($_19638 === \true) { return $this->finalise($result); }
	if($_19638 === \false) { return \false; }
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

/* in_operator: not:("NOT" ] )? "IN" > '(' > first:value_expression (> ',' > others:value_expression)* > ')' */
protected $match_in_operator_typestack = ['in_operator'];
function match_in_operator($stack = []) {
	$matchrule = 'in_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19657 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_19643 = $result;
		$pos_19643 = $this->pos;
		$_19642 = \null;
		do {
			if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
			else { $_19642 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_19642 = \false; break; }
			$_19642 = \true; break;
		}
		while(\false);
		if($_19642 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		if($_19642 === \false) {
			$result = $res_19643;
			$this->setPos($pos_19643);
			unset($res_19643, $pos_19643);
		}
		if (($subres = $this->literal('IN')) !== \false) { $result["text"] .= $subres; }
		else { $_19657 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_19657 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "first");
		}
		else { $_19657 = \false; break; }
		while (\true) {
			$res_19654 = $result;
			$pos_19654 = $this->pos;
			$_19653 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else { $_19653 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "others");
				}
				else { $_19653 = \false; break; }
				$_19653 = \true; break;
			}
			while(\false);
			if($_19653 === \false) {
				$result = $res_19654;
				$this->setPos($pos_19654);
				unset($res_19654, $pos_19654);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_19657 = \false; break; }
		$_19657 = \true; break;
	}
	while(\false);
	if($_19657 === \true) { return $this->finalise($result); }
	if($_19657 === \false) { return \false; }
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

/* simple_operator: op:/([<>]?=|!=|[<>])/ > v:value_expression */
protected $match_simple_operator_typestack = ['simple_operator'];
function match_simple_operator($stack = []) {
	$matchrule = 'simple_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19662 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		if (($subres = $this->rx('/([<>]?=|!=|[<>])/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		else {
			$result = \array_pop($stack);
			$_19662 = \false; break;
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_19662 = \false; break; }
		$_19662 = \true; break;
	}
	while(\false);
	if($_19662 === \true) { return $this->finalise($result); }
	if($_19662 === \false) { return \false; }
}

public function simple_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => preg_replace('#\s+#', '_', $result['op']['text']),
            'rightOperand' => $result['v']['data'],
        ];
        unset($result['op'], $result['v']);
    }

/* keyword_operator: op:op_keyword ] v:value_expression (] cs:case_sensitive)? */
protected $match_keyword_operator_typestack = ['keyword_operator'];
function match_keyword_operator($stack = []) {
	$matchrule = 'keyword_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19671 = \null;
	do {
		$key = 'match_'.'op_keyword'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "op");
		}
		else { $_19671 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_19671 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_19671 = \false; break; }
		$res_19670 = $result;
		$pos_19670 = $this->pos;
		$_19669 = \null;
		do {
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_19669 = \false; break; }
			$key = 'match_'.'case_sensitive'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "cs");
			}
			else { $_19669 = \false; break; }
			$_19669 = \true; break;
		}
		while(\false);
		if($_19669 === \false) {
			$result = $res_19670;
			$this->setPos($pos_19670);
			unset($res_19670, $pos_19670);
		}
		$_19671 = \true; break;
	}
	while(\false);
	if($_19671 === \true) { return $this->finalise($result); }
	if($_19671 === \false) { return \false; }
}

public function keyword_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => $result['op']['data'],
            'rightOperand' => $result['v']['data'],
        ];
        if (isset($result['cs'])) {
            $result['data']['caseSensitive'] = true;
        }
        unset($result['op'], $result['v'], $result['cs']);
    }

/* case_sensitive: "CASE" ] "SENSITIVE" */
protected $match_case_sensitive_typestack = ['case_sensitive'];
function match_case_sensitive($stack = []) {
	$matchrule = 'case_sensitive';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19676 = \null;
	do {
		if (($subres = $this->literal('CASE')) !== \false) { $result["text"] .= $subres; }
		else { $_19676 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_19676 = \false; break; }
		if (($subres = $this->literal('SENSITIVE')) !== \false) { $result["text"] .= $subres; }
		else { $_19676 = \false; break; }
		$_19676 = \true; break;
	}
	while(\false);
	if($_19676 === \true) { return $this->finalise($result); }
	if($_19676 === \false) { return \false; }
}


/* op_keyword: not:/(DO(ES)?\s+NOT\s+)/? key:/(CONTAINS?|MATCH(ES)?|STARTS?\s+WITH)/ */
protected $match_op_keyword_typestack = ['op_keyword'];
function match_op_keyword($stack = []) {
	$matchrule = 'op_keyword';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19680 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_19678 = $result;
		$pos_19678 = $this->pos;
		if (($subres = $this->rx('/(DO(ES)?\s+NOT\s+)/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		else {
			$result = $res_19678;
			$this->setPos($pos_19678);
			unset($res_19678, $pos_19678);
		}
		$stack[] = $result; $result = $this->construct($matchrule, "key");
		if (($subres = $this->rx('/(CONTAINS?|MATCH(ES)?|STARTS?\s+WITH)/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'key');
		}
		else {
			$result = \array_pop($stack);
			$_19680 = \false; break;
		}
		$_19680 = \true; break;
	}
	while(\false);
	if($_19680 === \true) { return $this->finalise($result); }
	if($_19680 === \false) { return \false; }
}

public function op_keyword__finalise (&$result) {
        $key = preg_replace('#\s+#', '_', $result['key']['text']);
        $result['data'] = (isset($result['not']) ? 'NOT_' : '').match ($key) {
            'CONTAINS', 'CONTAIN' => 'CONTAINS',
            'MATCHES', 'MATCH' => 'MATCHES',
            'STARTS_WITH', 'START_WITH' => 'STARTS_WITH',
        };
        unset($result['not'], $result['key']);
    }

/* function_call: f:identifier > "(" > first:value_expression? (> "," > others:value_expression)* > ")" */
protected $match_function_call_typestack = ['function_call'];
function match_function_call($stack = []) {
	$matchrule = 'function_call';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19695 = \null;
	do {
		$key = 'match_'.'identifier'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "f"); }
		else { $_19695 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_19695 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$res_19686 = $result;
		$pos_19686 = $this->pos;
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "first");
		}
		else {
			$result = $res_19686;
			$this->setPos($pos_19686);
			unset($res_19686, $pos_19686);
		}
		while (\true) {
			$res_19692 = $result;
			$pos_19692 = $this->pos;
			$_19691 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else { $_19691 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "others");
				}
				else { $_19691 = \false; break; }
				$_19691 = \true; break;
			}
			while(\false);
			if($_19691 === \false) {
				$result = $res_19692;
				$this->setPos($pos_19692);
				unset($res_19692, $pos_19692);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_19695 = \false; break; }
		$_19695 = \true; break;
	}
	while(\false);
	if($_19695 === \true) { return $this->finalise($result); }
	if($_19695 === \false) { return \false; }
}

public function function_call__finalise (&$result) {
        \App\Elasticsearch\AQL\AQLFunctionHandler::parseFunction($result);
    }

/* value_expression: v:value_sum */
protected $match_value_expression_typestack = ['value_expression'];
function match_value_expression($stack = []) {
	$matchrule = 'value_expression';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$key = 'match_'.'value_sum'; $pos = $this->pos;
	$subres = $this->packhas($key, $pos)
		? $this->packread($key, $pos)
		: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
	if ($subres !== \false) {
		$this->store($result, $subres, "v");
		return $this->finalise($result);
	}
	else { return \false; }
}

public function value_expression__finalise (&$result) {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

/* value_product: v:value_or_expr ( > sign:('/' | '*') > right:value_or_expr ) * */
protected $match_value_product_typestack = ['value_product'];
function match_value_product($stack = []) {
	$matchrule = 'value_product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19711 = \null;
	do {
		$key = 'match_'.'value_or_expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_19711 = \false; break; }
		while (\true) {
			$res_19710 = $result;
			$pos_19710 = $this->pos;
			$_19709 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$stack[] = $result; $result = $this->construct($matchrule, "sign");
				$_19705 = \null;
				do {
					$_19703 = \null;
					do {
						$res_19700 = $result;
						$pos_19700 = $this->pos;
						if (\substr($this->string, $this->pos, 1) === '/') {
							$this->addPos(1);
							$result["text"] .= '/';
							$_19703 = \true; break;
						}
						$result = $res_19700;
						$this->setPos($pos_19700);
						if (\substr($this->string, $this->pos, 1) === '*') {
							$this->addPos(1);
							$result["text"] .= '*';
							$_19703 = \true; break;
						}
						$result = $res_19700;
						$this->setPos($pos_19700);
						$_19703 = \false; break;
					}
					while(\false);
					if($_19703 === \false) { $_19705 = \false; break; }
					$_19705 = \true; break;
				}
				while(\false);
				if($_19705 === \true) {
					$subres = $result; $result = \array_pop($stack);
					$this->store($result, $subres, 'sign');
				}
				if($_19705 === \false) {
					$result = \array_pop($stack);
					$_19709 = \false; break;
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_or_expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_19709 = \false; break; }
				$_19709 = \true; break;
			}
			while(\false);
			if($_19709 === \false) {
				$result = $res_19710;
				$this->setPos($pos_19710);
				unset($res_19710, $pos_19710);
				break;
			}
		}
		$_19711 = \true; break;
	}
	while(\false);
	if($_19711 === \true) { return $this->finalise($result); }
	if($_19711 === \false) { return \false; }
}

public function value_product_handleOperator (mixed $l, mixed $r, string $operator): array|int|float {
        return [
            'type' => 'value_expression',
            'operator' => $operator,
            'leftOperand' => $l,
            'rightOperand' => $r,
        ];
    }

public function value_product__finalise (&$result) {
        $l = $result['v']['data'];
        if (isset($result['sign'])) {
            if (isset($result['right']['_matchrule'])) {
                $result['data'] = $this->value_product_handleOperator($l, $result['right']['data'], $result['sign']['text']);
            } else {
                foreach ($result['right'] ?? [] as $k => $right) {
                    $l = $this->value_product_handleOperator($l, $right['data'], $result['sign'][$k]['text']);
                }
                $result['data'] = $l;
            }
            unset($result['sign'], $result['v'], $result['right']);
            return;
        }
        $result['data'] = $l;
        unset($result['v']);
    }

/* value_sum: v:value_product ( > sign:('+' | '-') > right:value_product ) * */
protected $match_value_sum_typestack = ['value_sum'];
function match_value_sum($stack = []) {
	$matchrule = 'value_sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19726 = \null;
	do {
		$key = 'match_'.'value_product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_19726 = \false; break; }
		while (\true) {
			$res_19725 = $result;
			$pos_19725 = $this->pos;
			$_19724 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$stack[] = $result; $result = $this->construct($matchrule, "sign");
				$_19720 = \null;
				do {
					$_19718 = \null;
					do {
						$res_19715 = $result;
						$pos_19715 = $this->pos;
						if (\substr($this->string, $this->pos, 1) === '+') {
							$this->addPos(1);
							$result["text"] .= '+';
							$_19718 = \true; break;
						}
						$result = $res_19715;
						$this->setPos($pos_19715);
						if (\substr($this->string, $this->pos, 1) === '-') {
							$this->addPos(1);
							$result["text"] .= '-';
							$_19718 = \true; break;
						}
						$result = $res_19715;
						$this->setPos($pos_19715);
						$_19718 = \false; break;
					}
					while(\false);
					if($_19718 === \false) { $_19720 = \false; break; }
					$_19720 = \true; break;
				}
				while(\false);
				if($_19720 === \true) {
					$subres = $result; $result = \array_pop($stack);
					$this->store($result, $subres, 'sign');
				}
				if($_19720 === \false) {
					$result = \array_pop($stack);
					$_19724 = \false; break;
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_product'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_19724 = \false; break; }
				$_19724 = \true; break;
			}
			while(\false);
			if($_19724 === \false) {
				$result = $res_19725;
				$this->setPos($pos_19725);
				unset($res_19725, $pos_19725);
				break;
			}
		}
		$_19726 = \true; break;
	}
	while(\false);
	if($_19726 === \true) { return $this->finalise($result); }
	if($_19726 === \false) { return \false; }
}

public function value_sum_handleOperator (mixed $l, mixed $r, string $operator): array|int|float {
        return [
            'type' => 'value_expression',
            'operator' => $operator,
            'leftOperand' => $l,
            'rightOperand' => $r,
        ];
    }

public function value_sum__finalise (&$result) {
        $l = $result['v']['data'];
        if (isset($result['sign'])) {
            if (isset($result['right']['_matchrule'])) {
                $result['data'] = $this->value_sum_handleOperator($l, $result['right']['data'], $result['sign']['text']);
            } else {
                foreach ($result['right'] ?? [] as $k => $right) {
                    $l = $this->value_sum_handleOperator($l, $right['data'], $result['sign'][$k]['text']);
                }
                $result['data'] = $l;
            }
            unset($result['sign'], $result['v'], $result['right']);
            return;
        }
        $result['data'] = $l;
        unset($result['v']);
    }

/* value_or_expr: v:value | ('(' > p:value_expression > ')') */
protected $match_value_or_expr_typestack = ['value_or_expr'];
function match_value_or_expr($stack = []) {
	$matchrule = 'value_or_expr';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19737 = \null;
	do {
		$res_19728 = $result;
		$pos_19728 = $this->pos;
		$key = 'match_'.'value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_19737 = \true; break;
		}
		$result = $res_19728;
		$this->setPos($pos_19728);
		$_19735 = \null;
		do {
			if (\substr($this->string, $this->pos, 1) === '(') {
				$this->addPos(1);
				$result["text"] .= '(';
			}
			else { $_19735 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$key = 'match_'.'value_expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "p");
			}
			else { $_19735 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			if (\substr($this->string, $this->pos, 1) === ')') {
				$this->addPos(1);
				$result["text"] .= ')';
			}
			else { $_19735 = \false; break; }
			$_19735 = \true; break;
		}
		while(\false);
		if($_19735 === \true) { $_19737 = \true; break; }
		$result = $res_19728;
		$this->setPos($pos_19728);
		$_19737 = \false; break;
	}
	while(\false);
	if($_19737 === \true) { return $this->finalise($result); }
	if($_19737 === \false) { return \false; }
}

public function value_or_expr__finalise (&$result) {
        if (isset($result['p'])) {
            $result['data'] = [
                'type' => 'parentheses',
                'expression' => $result['p']['data'],
            ];
            unset($result['p']);
            return;
        }
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

/* value: v:function_call | v:number | v:quoted_string | v:boolean | v:const_null | v:field */
protected $match_value_typestack = ['value'];
function match_value($stack = []) {
	$matchrule = 'value';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19758 = \null;
	do {
		$res_19739 = $result;
		$pos_19739 = $this->pos;
		$key = 'match_'.'function_call'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_19758 = \true; break;
		}
		$result = $res_19739;
		$this->setPos($pos_19739);
		$_19756 = \null;
		do {
			$res_19741 = $result;
			$pos_19741 = $this->pos;
			$key = 'match_'.'number'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "v");
				$_19756 = \true; break;
			}
			$result = $res_19741;
			$this->setPos($pos_19741);
			$_19754 = \null;
			do {
				$res_19743 = $result;
				$pos_19743 = $this->pos;
				$key = 'match_'.'quoted_string'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_19754 = \true; break;
				}
				$result = $res_19743;
				$this->setPos($pos_19743);
				$_19752 = \null;
				do {
					$res_19745 = $result;
					$pos_19745 = $this->pos;
					$key = 'match_'.'boolean'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres, "v");
						$_19752 = \true; break;
					}
					$result = $res_19745;
					$this->setPos($pos_19745);
					$_19750 = \null;
					do {
						$res_19747 = $result;
						$pos_19747 = $this->pos;
						$key = 'match_'.'const_null'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "v");
							$_19750 = \true; break;
						}
						$result = $res_19747;
						$this->setPos($pos_19747);
						$key = 'match_'.'field'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "v");
							$_19750 = \true; break;
						}
						$result = $res_19747;
						$this->setPos($pos_19747);
						$_19750 = \false; break;
					}
					while(\false);
					if($_19750 === \true) { $_19752 = \true; break; }
					$result = $res_19745;
					$this->setPos($pos_19745);
					$_19752 = \false; break;
				}
				while(\false);
				if($_19752 === \true) { $_19754 = \true; break; }
				$result = $res_19743;
				$this->setPos($pos_19743);
				$_19754 = \false; break;
			}
			while(\false);
			if($_19754 === \true) { $_19756 = \true; break; }
			$result = $res_19741;
			$this->setPos($pos_19741);
			$_19756 = \false; break;
		}
		while(\false);
		if($_19756 === \true) { $_19758 = \true; break; }
		$result = $res_19739;
		$this->setPos($pos_19739);
		$_19758 = \false; break;
	}
	while(\false);
	if($_19758 === \true) { return $this->finalise($result); }
	if($_19758 === \false) { return \false; }
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
	$_19764 = \null;
	do {
		$res_19761 = $result;
		$pos_19761 = $this->pos;
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else {
			$result = $res_19761;
			$this->setPos($pos_19761);
			unset($res_19761, $pos_19761);
		}
		if (\substr($this->string, $this->pos, 1) === '.') {
			$this->addPos(1);
			$result["text"] .= '.';
		}
		else { $_19764 = \false; break; }
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_19764 = \false; break; }
		$_19764 = \true; break;
	}
	while(\false);
	if($_19764 === \true) { return $this->finalise($result); }
	if($_19764 === \false) { return \false; }
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

/* number: v:decimal | v:int */
protected $match_number_typestack = ['number'];
function match_number($stack = []) {
	$matchrule = 'number';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19770 = \null;
	do {
		$res_19767 = $result;
		$pos_19767 = $this->pos;
		$key = 'match_'.'decimal'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_19770 = \true; break;
		}
		$result = $res_19767;
		$this->setPos($pos_19767);
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_19770 = \true; break;
		}
		$result = $res_19767;
		$this->setPos($pos_19767);
		$_19770 = \false; break;
	}
	while(\false);
	if($_19770 === \true) { return $this->finalise($result); }
	if($_19770 === \false) { return \false; }
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


/* identifier: alpha alphanum* */
protected $match_identifier_typestack = ['identifier'];
function match_identifier($stack = []) {
	$matchrule = 'identifier';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19776 = \null;
	do {
		$key = 'match_'.'alpha'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_19776 = \false; break; }
		while (\true) {
			$res_19775 = $result;
			$pos_19775 = $this->pos;
			$key = 'match_'.'alphanum'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else {
				$result = $res_19775;
				$this->setPos($pos_19775);
				unset($res_19775, $pos_19775);
				break;
			}
		}
		$_19776 = \true; break;
	}
	while(\false);
	if($_19776 === \true) { return $this->finalise($result); }
	if($_19776 === \false) { return \false; }
}



}