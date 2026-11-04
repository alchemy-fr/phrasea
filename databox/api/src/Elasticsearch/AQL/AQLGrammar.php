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
	$_27098 = \null;
	do {
		$key = 'match_'.'and_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_27098 = \false; break; }
		while (\true) {
			$res_27097 = $result;
			$pos_27097 = $this->pos;
			$_27096 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_27096 = \false; break; }
				if (($subres = $this->literal('OR')) !== \false) { $result["text"] .= $subres; }
				else { $_27096 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_27096 = \false; break; }
				$key = 'match_'.'and_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_27096 = \false; break; }
				$_27096 = \true; break;
			}
			while(\false);
			if($_27096 === \false) {
				$result = $res_27097;
				$this->setPos($pos_27097);
				unset($res_27097, $pos_27097);
				break;
			}
		}
		$_27098 = \true; break;
	}
	while(\false);
	if($_27098 === \true) { return $this->finalise($result); }
	if($_27098 === \false) { return \false; }
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
	$_27107 = \null;
	do {
		$key = 'match_'.'condition'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_27107 = \false; break; }
		while (\true) {
			$res_27106 = $result;
			$pos_27106 = $this->pos;
			$_27105 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_27105 = \false; break; }
				if (($subres = $this->literal('AND')) !== \false) { $result["text"] .= $subres; }
				else { $_27105 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_27105 = \false; break; }
				$key = 'match_'.'condition'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_27105 = \false; break; }
				$_27105 = \true; break;
			}
			while(\false);
			if($_27105 === \false) {
				$result = $res_27106;
				$this->setPos($pos_27106);
				unset($res_27106, $pos_27106);
				break;
			}
		}
		$_27107 = \true; break;
	}
	while(\false);
	if($_27107 === \true) { return $this->finalise($result); }
	if($_27107 === \false) { return \false; }
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
	$_27122 = \null;
	do {
		$res_27109 = $result;
		$pos_27109 = $this->pos;
		$_27115 = \null;
		do {
			if (\substr($this->string, $this->pos, 1) === '(') {
				$this->addPos(1);
				$result["text"] .= '(';
			}
			else { $_27115 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$key = 'match_'.'expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
			}
			else { $_27115 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			if (\substr($this->string, $this->pos, 1) === ')') {
				$this->addPos(1);
				$result["text"] .= ')';
			}
			else { $_27115 = \false; break; }
			$_27115 = \true; break;
		}
		while(\false);
		if($_27115 === \true) { $_27122 = \true; break; }
		$result = $res_27109;
		$this->setPos($pos_27109);
		$_27120 = \null;
		do {
			$res_27117 = $result;
			$pos_27117 = $this->pos;
			$key = 'match_'.'not_expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
				$_27120 = \true; break;
			}
			$result = $res_27117;
			$this->setPos($pos_27117);
			$key = 'match_'.'criteria'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
				$_27120 = \true; break;
			}
			$result = $res_27117;
			$this->setPos($pos_27117);
			$_27120 = \false; break;
		}
		while(\false);
		if($_27120 === \true) { $_27122 = \true; break; }
		$result = $res_27109;
		$this->setPos($pos_27109);
		$_27122 = \false; break;
	}
	while(\false);
	if($_27122 === \true) { return $this->finalise($result); }
	if($_27122 === \false) { return \false; }
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
	$_27127 = \null;
	do {
		if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
		else { $_27127 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27127 = \false; break; }
		$key = 'match_'.'expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "e"); }
		else { $_27127 = \false; break; }
		$_27127 = \true; break;
	}
	while(\false);
	if($_27127 === \true) { return $this->finalise($result); }
	if($_27127 === \false) { return \false; }
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
	$_27131 = \null;
	do {
		$key = 'match_'.'field'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "field");
		}
		else { $_27131 = \false; break; }
		$key = 'match_'.'operator'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "op");
		}
		else { $_27131 = \false; break; }
		$_27131 = \true; break;
	}
	while(\false);
	if($_27131 === \true) { return $this->finalise($result); }
	if($_27131 === \false) { return \false; }
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
	$_27135 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '@') {
			$this->addPos(1);
			$result["text"] .= '@';
		}
		else { $_27135 = \false; break; }
		$key = 'match_'.'identifier'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_27135 = \false; break; }
		$_27135 = \true; break;
	}
	while(\false);
	if($_27135 === \true) { return $this->finalise($result); }
	if($_27135 === \false) { return \false; }
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
	$_27141 = \null;
	do {
		$res_27138 = $result;
		$pos_27138 = $this->pos;
		$key = 'match_'.'builtin_field'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "f");
			$_27141 = \true; break;
		}
		$result = $res_27138;
		$this->setPos($pos_27138);
		$key = 'match_'.'field_name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "f");
			$_27141 = \true; break;
		}
		$result = $res_27138;
		$this->setPos($pos_27138);
		$_27141 = \false; break;
	}
	while(\false);
	if($_27141 === \true) { return $this->finalise($result); }
	if($_27141 === \false) { return \false; }
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
	$_27154 = \null;
	do {
		$res_27143 = $result;
		$pos_27143 = $this->pos;
		if (($subres = $this->literal('true')) !== \false) {
			$result["text"] .= $subres;
			$_27154 = \true; break;
		}
		$result = $res_27143;
		$this->setPos($pos_27143);
		$_27152 = \null;
		do {
			$res_27145 = $result;
			$pos_27145 = $this->pos;
			if (($subres = $this->literal('false')) !== \false) {
				$result["text"] .= $subres;
				$_27152 = \true; break;
			}
			$result = $res_27145;
			$this->setPos($pos_27145);
			$_27150 = \null;
			do {
				$res_27147 = $result;
				$pos_27147 = $this->pos;
				if (($subres = $this->literal('TRUE')) !== \false) {
					$result["text"] .= $subres;
					$_27150 = \true; break;
				}
				$result = $res_27147;
				$this->setPos($pos_27147);
				if (($subres = $this->literal('FALSE')) !== \false) {
					$result["text"] .= $subres;
					$_27150 = \true; break;
				}
				$result = $res_27147;
				$this->setPos($pos_27147);
				$_27150 = \false; break;
			}
			while(\false);
			if($_27150 === \true) { $_27152 = \true; break; }
			$result = $res_27145;
			$this->setPos($pos_27145);
			$_27152 = \false; break;
		}
		while(\false);
		if($_27152 === \true) { $_27154 = \true; break; }
		$result = $res_27143;
		$this->setPos($pos_27143);
		$_27154 = \false; break;
	}
	while(\false);
	if($_27154 === \true) { return $this->finalise($result); }
	if($_27154 === \false) { return \false; }
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
	$_27159 = \null;
	do {
		$res_27156 = $result;
		$pos_27156 = $this->pos;
		if (($subres = $this->literal('null')) !== \false) {
			$result["text"] .= $subres;
			$_27159 = \true; break;
		}
		$result = $res_27156;
		$this->setPos($pos_27156);
		if (($subres = $this->literal('NULL')) !== \false) {
			$result["text"] .= $subres;
			$_27159 = \true; break;
		}
		$result = $res_27156;
		$this->setPos($pos_27156);
		$_27159 = \false; break;
	}
	while(\false);
	if($_27159 === \true) { return $this->finalise($result); }
	if($_27159 === \false) { return \false; }
}

public function const_null__finalise (&$result) {
        $result['data'] = null;
    }

/* operator: ] op:between_operator | ] op:in_operator | ] op:geo_operator | ] op:ending_operator | ] op:is_operator | > op:simple_operator | > op:keyword_operator */
protected $match_operator_typestack = ['operator'];
function match_operator($stack = []) {
	$matchrule = 'operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27205 = \null;
	do {
		$res_27161 = $result;
		$pos_27161 = $this->pos;
		$_27164 = \null;
		do {
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_27164 = \false; break; }
			$key = 'match_'.'between_operator'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "op");
			}
			else { $_27164 = \false; break; }
			$_27164 = \true; break;
		}
		while(\false);
		if($_27164 === \true) { $_27205 = \true; break; }
		$result = $res_27161;
		$this->setPos($pos_27161);
		$_27203 = \null;
		do {
			$res_27166 = $result;
			$pos_27166 = $this->pos;
			$_27169 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_27169 = \false; break; }
				$key = 'match_'.'in_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "op");
				}
				else { $_27169 = \false; break; }
				$_27169 = \true; break;
			}
			while(\false);
			if($_27169 === \true) { $_27203 = \true; break; }
			$result = $res_27166;
			$this->setPos($pos_27166);
			$_27201 = \null;
			do {
				$res_27171 = $result;
				$pos_27171 = $this->pos;
				$_27174 = \null;
				do {
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					else { $_27174 = \false; break; }
					$key = 'match_'.'geo_operator'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres, "op");
					}
					else { $_27174 = \false; break; }
					$_27174 = \true; break;
				}
				while(\false);
				if($_27174 === \true) { $_27201 = \true; break; }
				$result = $res_27171;
				$this->setPos($pos_27171);
				$_27199 = \null;
				do {
					$res_27176 = $result;
					$pos_27176 = $this->pos;
					$_27179 = \null;
					do {
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						else { $_27179 = \false; break; }
						$key = 'match_'.'ending_operator'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "op");
						}
						else { $_27179 = \false; break; }
						$_27179 = \true; break;
					}
					while(\false);
					if($_27179 === \true) { $_27199 = \true; break; }
					$result = $res_27176;
					$this->setPos($pos_27176);
					$_27197 = \null;
					do {
						$res_27181 = $result;
						$pos_27181 = $this->pos;
						$_27184 = \null;
						do {
							if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
							else { $_27184 = \false; break; }
							$key = 'match_'.'is_operator'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres, "op");
							}
							else { $_27184 = \false; break; }
							$_27184 = \true; break;
						}
						while(\false);
						if($_27184 === \true) { $_27197 = \true; break; }
						$result = $res_27181;
						$this->setPos($pos_27181);
						$_27195 = \null;
						do {
							$res_27186 = $result;
							$pos_27186 = $this->pos;
							$_27189 = \null;
							do {
								if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
								$key = 'match_'.'simple_operator'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres, "op");
								}
								else { $_27189 = \false; break; }
								$_27189 = \true; break;
							}
							while(\false);
							if($_27189 === \true) { $_27195 = \true; break; }
							$result = $res_27186;
							$this->setPos($pos_27186);
							$_27193 = \null;
							do {
								if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
								$key = 'match_'.'keyword_operator'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres, "op");
								}
								else { $_27193 = \false; break; }
								$_27193 = \true; break;
							}
							while(\false);
							if($_27193 === \true) { $_27195 = \true; break; }
							$result = $res_27186;
							$this->setPos($pos_27186);
							$_27195 = \false; break;
						}
						while(\false);
						if($_27195 === \true) { $_27197 = \true; break; }
						$result = $res_27181;
						$this->setPos($pos_27181);
						$_27197 = \false; break;
					}
					while(\false);
					if($_27197 === \true) { $_27199 = \true; break; }
					$result = $res_27176;
					$this->setPos($pos_27176);
					$_27199 = \false; break;
				}
				while(\false);
				if($_27199 === \true) { $_27201 = \true; break; }
				$result = $res_27171;
				$this->setPos($pos_27171);
				$_27201 = \false; break;
			}
			while(\false);
			if($_27201 === \true) { $_27203 = \true; break; }
			$result = $res_27166;
			$this->setPos($pos_27166);
			$_27203 = \false; break;
		}
		while(\false);
		if($_27203 === \true) { $_27205 = \true; break; }
		$result = $res_27161;
		$this->setPos($pos_27161);
		$_27205 = \false; break;
	}
	while(\false);
	if($_27205 === \true) { return $this->finalise($result); }
	if($_27205 === \false) { return \false; }
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
	$_27216 = \null;
	do {
		if (($subres = $this->literal('WITHIN')) !== \false) { $result["text"] .= $subres; }
		else { $_27216 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27216 = \false; break; }
		$_27214 = \null;
		do {
			$_27212 = \null;
			do {
				$res_27209 = $result;
				$pos_27209 = $this->pos;
				$key = 'match_'.'within_circle_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_27212 = \true; break;
				}
				$result = $res_27209;
				$this->setPos($pos_27209);
				$key = 'match_'.'within_rectangle_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_27212 = \true; break;
				}
				$result = $res_27209;
				$this->setPos($pos_27209);
				$_27212 = \false; break;
			}
			while(\false);
			if($_27212 === \false) { $_27214 = \false; break; }
			$_27214 = \true; break;
		}
		while(\false);
		if($_27214 === \false) { $_27216 = \false; break; }
		$_27216 = \true; break;
	}
	while(\false);
	if($_27216 === \true) { return $this->finalise($result); }
	if($_27216 === \false) { return \false; }
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
	$_27233 = \null;
	do {
		if (($subres = $this->literal('CIRCLE')) !== \false) { $result["text"] .= $subres; }
		else { $_27233 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_27233 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "lat");
		}
		else { $_27233 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_27233 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "lng");
		}
		else { $_27233 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_27233 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "radius");
		}
		else { $_27233 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_27233 = \false; break; }
		$_27233 = \true; break;
	}
	while(\false);
	if($_27233 === \true) { return $this->finalise($result); }
	if($_27233 === \false) { return \false; }
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
	$_27254 = \null;
	do {
		if (($subres = $this->literal('RECTANGLE')) !== \false) { $result["text"] .= $subres; }
		else { $_27254 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_27254 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "topLeftLat");
		}
		else { $_27254 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_27254 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "topLeftLng");
		}
		else { $_27254 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_27254 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "bottomRightLat");
		}
		else { $_27254 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_27254 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "bottomRightLng");
		}
		else { $_27254 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_27254 = \false; break; }
		$_27254 = \true; break;
	}
	while(\false);
	if($_27254 === \true) { return $this->finalise($result); }
	if($_27254 === \false) { return \false; }
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
	$_27267 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_27259 = $result;
		$pos_27259 = $this->pos;
		$_27258 = \null;
		do {
			if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
			else { $_27258 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_27258 = \false; break; }
			$_27258 = \true; break;
		}
		while(\false);
		if($_27258 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		if($_27258 === \false) {
			$result = $res_27259;
			$this->setPos($pos_27259);
			unset($res_27259, $pos_27259);
		}
		if (($subres = $this->literal('BETWEEN')) !== \false) { $result["text"] .= $subres; }
		else { $_27267 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27267 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_27267 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27267 = \false; break; }
		if (($subres = $this->literal('AND')) !== \false) { $result["text"] .= $subres; }
		else { $_27267 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27267 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "right");
		}
		else { $_27267 = \false; break; }
		$_27267 = \true; break;
	}
	while(\false);
	if($_27267 === \true) { return $this->finalise($result); }
	if($_27267 === \false) { return \false; }
}

public function between_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => isset($result['not']) ? 'NOT_BETWEEN' : 'BETWEEN',
            'rightOperand' => [$result['left']['data'], $result['right']['data']],
        ];
        unset($result['left'], $result['right']);
    }

/* ending_operator: op:/(IS\s+NOT\s+EMPTY|IS\s+EMPTY|IS\s+MISSING|EXISTS)\b/ */
protected $match_ending_operator_typestack = ['ending_operator'];
function match_ending_operator($stack = []) {
	$matchrule = 'ending_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$stack[] = $result; $result = $this->construct($matchrule, "op");
	if (($subres = $this->rx('/(IS\s+NOT\s+EMPTY|IS\s+EMPTY|IS\s+MISSING|EXISTS)\b/')) !== \false) {
		$result["text"] .= $subres;
		$subres = $result; $result = \array_pop($stack);
		$this->store($result, $subres, 'op');
		return $this->finalise($result);
	}
	else {
		$result = \array_pop($stack);
		return \false;
	}
}

public function ending_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => match (preg_replace('#\s+#', '_', $result['op']['text'])) {
                'IS_MISSING', 'IS_EMPTY' => 'MISSING',
                'EXISTS', 'IS_NOT_EMPTY' => 'EXISTS',
            },
        ];
        unset($result['op']);
    }

/* is_operator: "IS" ] not:("NOT" ])? !is_reserved v:value_expression */
protected $match_is_operator_typestack = ['is_operator'];
function match_is_operator($stack = []) {
	$matchrule = 'is_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27278 = \null;
	do {
		if (($subres = $this->literal('IS')) !== \false) { $result["text"] .= $subres; }
		else { $_27278 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27278 = \false; break; }
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_27275 = $result;
		$pos_27275 = $this->pos;
		$_27274 = \null;
		do {
			if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
			else { $_27274 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_27274 = \false; break; }
			$_27274 = \true; break;
		}
		while(\false);
		if($_27274 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		if($_27274 === \false) {
			$result = $res_27275;
			$this->setPos($pos_27275);
			unset($res_27275, $pos_27275);
		}
		$res_27276 = $result;
		$pos_27276 = $this->pos;
		$key = 'match_'.'is_reserved'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$result = $res_27276;
			$this->setPos($pos_27276);
			$_27278 = \false; break;
		}
		else {
			$result = $res_27276;
			$this->setPos($pos_27276);
		}
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_27278 = \false; break; }
		$_27278 = \true; break;
	}
	while(\false);
	if($_27278 === \true) { return $this->finalise($result); }
	if($_27278 === \false) { return \false; }
}

public function is_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => isset($result['not']) ? '!=' : '=',
            'rightOperand' => $result['v']['data'],
        ];
        unset($result['not'], $result['v']);
    }

/* is_reserved: /(EMPTY|MISSING|ANY|NONE)\b/ */
protected $match_is_reserved_typestack = ['is_reserved'];
function match_is_reserved($stack = []) {
	$matchrule = 'is_reserved';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/(EMPTY|MISSING|ANY|NONE)\b/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}


/* in_operator: op:/(NOT\s+IN|IN|IS\s+ANY\s+OF|IS\s+NONE\s+OF|HAS\s+ANY\s+OF|HAS\s+NONE\s+OF|HAS\s+ALL\s+OF)\b/ > '(' > first:value_expression (> ',' > others:value_expression)* > ')' */
protected $match_in_operator_typestack = ['in_operator'];
function match_in_operator($stack = []) {
	$matchrule = 'in_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27294 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		if (($subres = $this->rx('/(NOT\s+IN|IN|IS\s+ANY\s+OF|IS\s+NONE\s+OF|HAS\s+ANY\s+OF|HAS\s+NONE\s+OF|HAS\s+ALL\s+OF)\b/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		else {
			$result = \array_pop($stack);
			$_27294 = \false; break;
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_27294 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "first");
		}
		else { $_27294 = \false; break; }
		while (\true) {
			$res_27291 = $result;
			$pos_27291 = $this->pos;
			$_27290 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else { $_27290 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "others");
				}
				else { $_27290 = \false; break; }
				$_27290 = \true; break;
			}
			while(\false);
			if($_27290 === \false) {
				$result = $res_27291;
				$this->setPos($pos_27291);
				unset($res_27291, $pos_27291);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_27294 = \false; break; }
		$_27294 = \true; break;
	}
	while(\false);
	if($_27294 === \true) { return $this->finalise($result); }
	if($_27294 === \false) { return \false; }
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
            'operator' => match (preg_replace('#\s+#', '_', $result['op']['text'])) {
                'IN', 'IS_ANY_OF', 'HAS_ANY_OF' => 'IN',
                'NOT_IN', 'IS_NONE_OF', 'HAS_NONE_OF' => 'NOT_IN',
                'HAS_ALL_OF' => 'HAS_ALL_OF',
            },
            'rightOperand' => $values,
        ];
        unset($result['op'], $result['first'], $result['others']);
    }

/* simple_operator: op:/([<>]?=|!=|[<>])/ > v:value_expression */
protected $match_simple_operator_typestack = ['simple_operator'];
function match_simple_operator($stack = []) {
	$matchrule = 'simple_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27299 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		if (($subres = $this->rx('/([<>]?=|!=|[<>])/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		else {
			$result = \array_pop($stack);
			$_27299 = \false; break;
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_27299 = \false; break; }
		$_27299 = \true; break;
	}
	while(\false);
	if($_27299 === \true) { return $this->finalise($result); }
	if($_27299 === \false) { return \false; }
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
	$_27308 = \null;
	do {
		$key = 'match_'.'op_keyword'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "op");
		}
		else { $_27308 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27308 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_27308 = \false; break; }
		$res_27307 = $result;
		$pos_27307 = $this->pos;
		$_27306 = \null;
		do {
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_27306 = \false; break; }
			$key = 'match_'.'case_sensitive'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "cs");
			}
			else { $_27306 = \false; break; }
			$_27306 = \true; break;
		}
		while(\false);
		if($_27306 === \false) {
			$result = $res_27307;
			$this->setPos($pos_27307);
			unset($res_27307, $pos_27307);
		}
		$_27308 = \true; break;
	}
	while(\false);
	if($_27308 === \true) { return $this->finalise($result); }
	if($_27308 === \false) { return \false; }
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
	$_27313 = \null;
	do {
		if (($subres = $this->literal('CASE')) !== \false) { $result["text"] .= $subres; }
		else { $_27313 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27313 = \false; break; }
		if (($subres = $this->literal('SENSITIVE')) !== \false) { $result["text"] .= $subres; }
		else { $_27313 = \false; break; }
		$_27313 = \true; break;
	}
	while(\false);
	if($_27313 === \true) { return $this->finalise($result); }
	if($_27313 === \false) { return \false; }
}


/* op_keyword: not:/(DO(ES)?\s+NOT\s+)/? key:/(CONTAINS?|MATCH(ES)?|STARTS?\s+WITH|ENDS?\s+WITH)/ */
protected $match_op_keyword_typestack = ['op_keyword'];
function match_op_keyword($stack = []) {
	$matchrule = 'op_keyword';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27317 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_27315 = $result;
		$pos_27315 = $this->pos;
		if (($subres = $this->rx('/(DO(ES)?\s+NOT\s+)/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		else {
			$result = $res_27315;
			$this->setPos($pos_27315);
			unset($res_27315, $pos_27315);
		}
		$stack[] = $result; $result = $this->construct($matchrule, "key");
		if (($subres = $this->rx('/(CONTAINS?|MATCH(ES)?|STARTS?\s+WITH|ENDS?\s+WITH)/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'key');
		}
		else {
			$result = \array_pop($stack);
			$_27317 = \false; break;
		}
		$_27317 = \true; break;
	}
	while(\false);
	if($_27317 === \true) { return $this->finalise($result); }
	if($_27317 === \false) { return \false; }
}

public function op_keyword__finalise (&$result) {
        $key = preg_replace('#\s+#', '_', $result['key']['text']);
        $result['data'] = (isset($result['not']) ? 'NOT_' : '').match ($key) {
            'CONTAINS', 'CONTAIN' => 'CONTAINS',
            'MATCHES', 'MATCH' => 'MATCHES',
            'STARTS_WITH', 'START_WITH' => 'STARTS_WITH',
            'ENDS_WITH', 'END_WITH' => 'ENDS_WITH',
        };
        unset($result['not'], $result['key']);
    }

/* function_call: f:identifier > "(" > first:value_expression? (> "," > others:value_expression)* > ")" */
protected $match_function_call_typestack = ['function_call'];
function match_function_call($stack = []) {
	$matchrule = 'function_call';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27332 = \null;
	do {
		$key = 'match_'.'identifier'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "f"); }
		else { $_27332 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_27332 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$res_27323 = $result;
		$pos_27323 = $this->pos;
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "first");
		}
		else {
			$result = $res_27323;
			$this->setPos($pos_27323);
			unset($res_27323, $pos_27323);
		}
		while (\true) {
			$res_27329 = $result;
			$pos_27329 = $this->pos;
			$_27328 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else { $_27328 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "others");
				}
				else { $_27328 = \false; break; }
				$_27328 = \true; break;
			}
			while(\false);
			if($_27328 === \false) {
				$result = $res_27329;
				$this->setPos($pos_27329);
				unset($res_27329, $pos_27329);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_27332 = \false; break; }
		$_27332 = \true; break;
	}
	while(\false);
	if($_27332 === \true) { return $this->finalise($result); }
	if($_27332 === \false) { return \false; }
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
	$_27348 = \null;
	do {
		$key = 'match_'.'value_or_expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_27348 = \false; break; }
		while (\true) {
			$res_27347 = $result;
			$pos_27347 = $this->pos;
			$_27346 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$stack[] = $result; $result = $this->construct($matchrule, "sign");
				$_27342 = \null;
				do {
					$_27340 = \null;
					do {
						$res_27337 = $result;
						$pos_27337 = $this->pos;
						if (\substr($this->string, $this->pos, 1) === '/') {
							$this->addPos(1);
							$result["text"] .= '/';
							$_27340 = \true; break;
						}
						$result = $res_27337;
						$this->setPos($pos_27337);
						if (\substr($this->string, $this->pos, 1) === '*') {
							$this->addPos(1);
							$result["text"] .= '*';
							$_27340 = \true; break;
						}
						$result = $res_27337;
						$this->setPos($pos_27337);
						$_27340 = \false; break;
					}
					while(\false);
					if($_27340 === \false) { $_27342 = \false; break; }
					$_27342 = \true; break;
				}
				while(\false);
				if($_27342 === \true) {
					$subres = $result; $result = \array_pop($stack);
					$this->store($result, $subres, 'sign');
				}
				if($_27342 === \false) {
					$result = \array_pop($stack);
					$_27346 = \false; break;
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_or_expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_27346 = \false; break; }
				$_27346 = \true; break;
			}
			while(\false);
			if($_27346 === \false) {
				$result = $res_27347;
				$this->setPos($pos_27347);
				unset($res_27347, $pos_27347);
				break;
			}
		}
		$_27348 = \true; break;
	}
	while(\false);
	if($_27348 === \true) { return $this->finalise($result); }
	if($_27348 === \false) { return \false; }
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
	$_27363 = \null;
	do {
		$key = 'match_'.'value_product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_27363 = \false; break; }
		while (\true) {
			$res_27362 = $result;
			$pos_27362 = $this->pos;
			$_27361 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$stack[] = $result; $result = $this->construct($matchrule, "sign");
				$_27357 = \null;
				do {
					$_27355 = \null;
					do {
						$res_27352 = $result;
						$pos_27352 = $this->pos;
						if (\substr($this->string, $this->pos, 1) === '+') {
							$this->addPos(1);
							$result["text"] .= '+';
							$_27355 = \true; break;
						}
						$result = $res_27352;
						$this->setPos($pos_27352);
						if (\substr($this->string, $this->pos, 1) === '-') {
							$this->addPos(1);
							$result["text"] .= '-';
							$_27355 = \true; break;
						}
						$result = $res_27352;
						$this->setPos($pos_27352);
						$_27355 = \false; break;
					}
					while(\false);
					if($_27355 === \false) { $_27357 = \false; break; }
					$_27357 = \true; break;
				}
				while(\false);
				if($_27357 === \true) {
					$subres = $result; $result = \array_pop($stack);
					$this->store($result, $subres, 'sign');
				}
				if($_27357 === \false) {
					$result = \array_pop($stack);
					$_27361 = \false; break;
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_product'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_27361 = \false; break; }
				$_27361 = \true; break;
			}
			while(\false);
			if($_27361 === \false) {
				$result = $res_27362;
				$this->setPos($pos_27362);
				unset($res_27362, $pos_27362);
				break;
			}
		}
		$_27363 = \true; break;
	}
	while(\false);
	if($_27363 === \true) { return $this->finalise($result); }
	if($_27363 === \false) { return \false; }
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
	$_27374 = \null;
	do {
		$res_27365 = $result;
		$pos_27365 = $this->pos;
		$key = 'match_'.'value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_27374 = \true; break;
		}
		$result = $res_27365;
		$this->setPos($pos_27365);
		$_27372 = \null;
		do {
			if (\substr($this->string, $this->pos, 1) === '(') {
				$this->addPos(1);
				$result["text"] .= '(';
			}
			else { $_27372 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$key = 'match_'.'value_expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "p");
			}
			else { $_27372 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			if (\substr($this->string, $this->pos, 1) === ')') {
				$this->addPos(1);
				$result["text"] .= ')';
			}
			else { $_27372 = \false; break; }
			$_27372 = \true; break;
		}
		while(\false);
		if($_27372 === \true) { $_27374 = \true; break; }
		$result = $res_27365;
		$this->setPos($pos_27365);
		$_27374 = \false; break;
	}
	while(\false);
	if($_27374 === \true) { return $this->finalise($result); }
	if($_27374 === \false) { return \false; }
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
	$_27395 = \null;
	do {
		$res_27376 = $result;
		$pos_27376 = $this->pos;
		$key = 'match_'.'function_call'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_27395 = \true; break;
		}
		$result = $res_27376;
		$this->setPos($pos_27376);
		$_27393 = \null;
		do {
			$res_27378 = $result;
			$pos_27378 = $this->pos;
			$key = 'match_'.'number'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "v");
				$_27393 = \true; break;
			}
			$result = $res_27378;
			$this->setPos($pos_27378);
			$_27391 = \null;
			do {
				$res_27380 = $result;
				$pos_27380 = $this->pos;
				$key = 'match_'.'quoted_string'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_27391 = \true; break;
				}
				$result = $res_27380;
				$this->setPos($pos_27380);
				$_27389 = \null;
				do {
					$res_27382 = $result;
					$pos_27382 = $this->pos;
					$key = 'match_'.'boolean'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres, "v");
						$_27389 = \true; break;
					}
					$result = $res_27382;
					$this->setPos($pos_27382);
					$_27387 = \null;
					do {
						$res_27384 = $result;
						$pos_27384 = $this->pos;
						$key = 'match_'.'const_null'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "v");
							$_27387 = \true; break;
						}
						$result = $res_27384;
						$this->setPos($pos_27384);
						$key = 'match_'.'field'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "v");
							$_27387 = \true; break;
						}
						$result = $res_27384;
						$this->setPos($pos_27384);
						$_27387 = \false; break;
					}
					while(\false);
					if($_27387 === \true) { $_27389 = \true; break; }
					$result = $res_27382;
					$this->setPos($pos_27382);
					$_27389 = \false; break;
				}
				while(\false);
				if($_27389 === \true) { $_27391 = \true; break; }
				$result = $res_27380;
				$this->setPos($pos_27380);
				$_27391 = \false; break;
			}
			while(\false);
			if($_27391 === \true) { $_27393 = \true; break; }
			$result = $res_27378;
			$this->setPos($pos_27378);
			$_27393 = \false; break;
		}
		while(\false);
		if($_27393 === \true) { $_27395 = \true; break; }
		$result = $res_27376;
		$this->setPos($pos_27376);
		$_27395 = \false; break;
	}
	while(\false);
	if($_27395 === \true) { return $this->finalise($result); }
	if($_27395 === \false) { return \false; }
}

public function value__finalise (&$result) {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
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

/* number: /-?([0-9]*\.[0-9]+|[0-9]+)/ */
protected $match_number_typestack = ['number'];
function match_number($stack = []) {
	$matchrule = 'number';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/-?([0-9]*\.[0-9]+|[0-9]+)/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}

public function number__finalise (&$result) {
        $result['data'] = str_contains($result['text'], '.') ? (float) $result['text'] : (int) $result['text'];
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
	$_27403 = \null;
	do {
		$key = 'match_'.'alpha'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_27403 = \false; break; }
		while (\true) {
			$res_27402 = $result;
			$pos_27402 = $this->pos;
			$key = 'match_'.'alphanum'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else {
				$result = $res_27402;
				$this->setPos($pos_27402);
				unset($res_27402, $pos_27402);
				break;
			}
		}
		$_27403 = \true; break;
	}
	while(\false);
	if($_27403 === \true) { return $this->finalise($result); }
	if($_27403 === \false) { return \false; }
}



}