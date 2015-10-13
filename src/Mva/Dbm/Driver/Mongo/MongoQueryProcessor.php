<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongo;

use Nette,
	Mva\Dbm\InvalidArgumentException;

/**
 * Prepares query, projection and parameters.
 * ParamBuilder::processModifier is inspired by https://github.com/nextras/dbal by Jan Skrasek
 */
class MongoQueryProcessor extends Nette\Object
{

	private $cmd = '$';

	/** @var array of SQL like operators and mongo equivalents */
	private $operators = [
		'=' => '=',
		'<>' => 'ne',
		'!=' => 'ne',
		'<=' => 'lte',
		'>=' => 'gte',
		'<' => 'lt',
		'>' => 'gt',
		'in' => 'in',
		'not_in' => 'nin'
	];

	public function __construct()
	{
		$this->cmd = ini_get('mongo.cmd') ? : $this->cmd;
	}

	public function formatCmd($cmd)
	{
		return $this->cmd . $cmd;
	}

	public function processSelect(array $items)
	{
		if (array_key_exists(0, $items) === FALSE) {
			return $items;
		}

		$select = [];

		foreach ($items as $item) {
			if (substr($item, 0, 1) === '!') {
				$select[substr($item, 1)] = FALSE;
			} else {
				$select[$item] = TRUE;
			}
		}

		return $select;
	}

	public function processUpdate(array $data)
	{
		$set = $this->formatCmd('set');
		$unset = $this->formatCmd('unset');

		$data[$set] = isset($data[$set]) ? $data[$set] : [];

		foreach ($data as $index => $value) {
			if (substr($index, 0, 1) !== $this->cmd) {
				$data[$set][$index] = $value;
				unset($data[$index]);
			} elseif ($index === $unset) {
				$data[$unset] = array_fill_keys(array_values((array) $data[$unset]), '');
			}
		}

		if (empty($data[$set])) {
			unset($data[$set]);
		} else {
			$data[$set] = $this->processData($data[$set]);
		}

		return $data;
	}

	/**
	 * 	@param array in format [['key1' => 'val'], ['item2 IN' => [1, 2, 4, 5]]] or ['key1' => 'val', 'key2 IN' => [1, 2, 4, 5]]
	 * 	@param int 
	 */
	public function processCondition(array $conditions, $depth = 0)
	{
		$parsed = [];

		foreach ($conditions as $key => $condition) {
			if (is_int($key)) {
				if ($depth > 0 && is_array($condition)) {
					throw new InvalidArgumentException('Too deep sets of condition!');
				}
				if (is_array($condition)) {
					$parsed = array_merge($parsed, $this->processCondition($condition, $depth + 1));
				} else {
					$parsed[] = $this->parseCondition($condition);
				}
			} else {
				$parsed[] = $this->parseCondition($key, $condition);
			}
		}

		return $depth > 0 ? $parsed : (count($parsed) > 1 ? [$this->formatCmd('and') => $parsed] : $parsed[0]);
	}

	private function parseCondition($condition, $parameters = [])
	{
		if (strpos($condition, ' ')) {
			$match = preg_match('~^
				(.+)\s							## identifier 
				(
					(?:\$\w+) |					## $mongoOperator
					(?:[A-Z]+(?:_[A-Z]+)*) |	## NAMED_OPERATOR or 
					(?:[\<\>\!]?\=|\>|\<\>?)	## logical operator
				)	
				(?:
					\s%(\w+(?:\[\])?) |			## modifier or
					\s(.+)						## value
				)?$~xs', $condition, $cond);

			//['cond IN' => 'param'], ['cond != ?' => 'param']
			if (!empty($match)) {

				if (substr($cond[1], 0, 1) === $this->cmd) {
					throw new InvalidArgumentException("Field name cannot start with '{$this->cmd}'");
				}

				return $this->formatCondition($cond[1], trim($cond[2], $this->cmd), isset($cond[4]) ? $cond[4] : $parameters, isset($cond[3]) ? $cond[3] : NULL);
			}
		}

		if (is_array($parameters) && ($value = reset($parameters))) {

			//['$cond' => $param[]]
			if (substr($condition, 0, 1) === $this->cmd) {
				return [$condition => $this->parseDeepCondition($parameters, TRUE)];
			}
			//['cond' => ['param', ...]]
			if (substr($key = key($parameters), 0, 1) !== $this->cmd) {
				return $this->formatCondition($condition, 'IN', $parameters);
			}
			//['cond' => ['$param' => [...]]]
			if (is_array($value)) {
				return [$condition => [$key => $this->parseDeepCondition($value)]];
			}
		}

		// [cond => param]
		return [$condition => $parameters];
	}

	private function formatCondition($key, $op, $val, $modifier = NULL)
	{
		$operator = strtolower($op);

		$value = $modifier ? $this->processModifier($modifier, $val) : $val;

		//tries to translate operator
		if (array_key_exists($operator, $this->operators)) {
			$operator = $this->operators[$operator];
		}

		if ($operator === '=') {
			return [$key => $value];
		}

		//$in and $nin need to reset keys
		if ($operator === 'in' || $operator === 'nin') {
			$value = array_values((array) $value);
		}

		//parses inner condition in $elemMatch
		if ($operator === 'elem_match' && is_array($value)) {
			$value = $this->parseDeepCondition($value);
		}

		//translates SQL like operator to mongo format ELEM_MATCH => $elemMatch
		if (strpos($operator, '_') !== FALSE) {
			$operator = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $operator))));
		}

		return [(string) $key => [$this->formatCmd($operator) => $value]];
	}

	private function parseDeepCondition(array $parameters, $toArray = FALSE)
	{
		$opcond = [];

		foreach ($parameters as $key => $param) {
			$ccond = is_int($key) ? $this->parseCondition($param) : $this->parseCondition($key, $param);

			if ($toArray) {
				$opcond[] = $ccond;
			} else {
				reset($ccond);
				$opcond[key($ccond)] = current($ccond);
			}
		}

		return $opcond;
	}

	/**
	 * @param array ['name' => 'roman', 'age%i' => '27', 'numbers%i[]' => ['1', 2, 2.3]] 
	 */
	public function processData(array $data)
	{
		$return = [];

		foreach ($data as $key => $item) {
			if (($count = substr_count($key, '%')) > 0) {
				$key = $count > 1 ? str_replace('%%', '%', $key) : $key;
				if (($count % 2) > 0 && preg_match('#^(.*)%(\w+(?:\[\])?)$#', $key, $parts)) {
					$key = $parts[1];
					$item = $this->processModifier($parts[2], $item);
				}
			} elseif ($item instanceof \DateTime || $item instanceof \DateTimeImmutable) {
				$item = $this->processModifier('dt', $item);
			}
			$return[$key] = $item;
		}

		return $return;
	}

	/**
	 * @param  string $type
	 * @param  mixed  $value
	 * @return string
	 */
	public function processModifier($type, $value)
	{
		switch (gettype($value)) {
			case 'string':
				switch ($type) {
					case 'b':
						if (in_array($value, ['TRUE', 'FALSE'])) {
							return $value === 'TRUE';
						}
						break;
					case 're':
						return new \MongoRegex($value);
				}
			case 'integer':
			case 'double':
			case 'boolean':
				switch ($type) {
					case 'any':
					case 's':
						return (string) $value;
					case 'i':
						return (int) ($value + 0);
					case 'f':
						return (float) $value;
					case 'dt':
						return new \MongoDate(is_numeric($value) ? (int) $value : strtotime((string) $value));
					case 'ts':
						return new \MongoTimestamp(is_numeric($value) ? (int) $value : strtotime((string) $value));
					case 'b':
						return (bool) $value;
					case 'oid':
						return new \MongoId((string) $value);
				}
				break;

			case 'NULL':
				return NULL;

			case 'object':
				if ($value instanceof \DateTimeImmutable || $value instanceof \DateTime) {
					switch ($type) {
						case 'dt':
							return new \MongoDate($value->format('U'));
						case 'ts':
							return new \MongoTimestamp($value->format('U'));
						case 'any':
						case 's':
							return $value->format('Y-m-d H:i:s');
						case 'i':
							return (int) $value->format('U');
						case 'f':
							return (float) $value->format('U');
					}
				} elseif ($value instanceof \MongoId && $type === 'oid') {
					return $value;
				} elseif ($value instanceof \MongoRegex && $type === 're') {
					return $value;
				} elseif (method_exists($value, '__toString')) {
					$str_value = (string) $value;
					switch ($type) {
						case 'any':
						case 's':
							return $str_value;
						case 'i':
							return (int) ($str_value + 0);
						case 'f':
							return (float) $str_value;
						case 'b':
							return (bool) $str_value;
					}
				}
				break;

			case 'array':
				if (substr($type, -1) === ']' || $type === 'any') {
					$this->processArray(trim($type, '[]'), $value);
					return $value;
				}
				break;

			default:
				return $value;
		}
	}

	protected function processArray($type, array &$values)
	{
		foreach ($values as &$item) {
			$item = $this->processModifier($type, $item);
		}
	}

}
