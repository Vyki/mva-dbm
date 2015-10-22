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
		if (!array_key_exists(0, $items)) {
			return $items;
		}

		$select = [];

		foreach ($items as $item) {
			list($modified, $item) = $this->doubledModifier($item, '!');

			if ($modified && substr($item, 0, 1) === '!') {
				$select[substr($item, 1)] = FALSE;
			} else {
				$select[$item] = TRUE;
			}
		}

		return $select;
	}

	/**
	 * Process update data 
	 * @param array ['a' => 1, 'b%s' => 2, '$set' => ['c%i' => '3'], '$unset' => ['d', 'e'], ...]
	 * @return array ['$set' => ['a' => 1, 'b' => '2', 'c' => 3], '$unset' => ['d' => '', 'e' => ''], ...]
	 */
	public function processUpdate(array $data)
	{
		$set = $this->formatCmd('set');
		$unset = $this->formatCmd('unset');
		$setoninsert = $this->formatCmd('setOnInsert');

		$data[$set] = isset($data[$set]) ? $data[$set] : [];

		foreach ($data as $index => $value) {
			if (substr($index, 0, 1) !== $this->cmd) {
				$data[$set][$index] = $value;
				unset($data[$index]);
			} elseif ($index === $unset) {
				$data[$unset] = array_fill_keys(array_values((array) $data[$unset]), '');
			}
		}

		foreach ([$set, $setoninsert] as $cmd) {
			if (empty($data[$cmd])) {
				unset($data[$cmd]);
			} else {
				$data[$cmd] = $this->processData($data[$cmd]);
			}
		}

		return $data;
	}

	/**
	 *  Process sets of conditions and merges them by AND operator
	 * 	@param array in format [['a' => 1], ['b IN' => [1, 2]], [...]] or ['a' => 1, 'b IN' => [1, 2], ...]
	 * 	@param array single condition ['a' => 1] or multiple condition ['$and' => ['a' => 1, 'b' => ['$in' => [1, 2]]], ...]
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

	/**
	 * Parses single condition
	 * @param $condition string
	 * @param $parameters mixed
	 * @throws InvalidArgumentException
	 */
	private function parseCondition($condition)
	{
		list($parameters, $noparam) = func_num_args() < 2 ? [[], TRUE] : [func_get_arg(1), FALSE];

		if (strpos($condition, ' ')) {
			$match = preg_match('~^
				(.+)\s                          ## identifier 
				(
					(?:\$\w+) |                 ## $mongoOperator
					(?:[A-Z]+(?:_[A-Z]+)*) |    ## NAMED_OPERATOR or 
					(?:[\<\>\!]?\=|\>|\<\>?)    ## logical operator
				)	
				(?:
					\s%(\w+(?:\[\])?) |         ## modifier or
					\s(.+)                      ## value
				)?$~xs', $condition, $cond);

			//['cond IN' => [...]], ['cond = %s' => 'param'], ['cond $gt' => 20]
			if (!empty($match)) {

				if (substr($cond[1], 0, 1) === $this->cmd) {
					throw new InvalidArgumentException("Field name cannot start with '{$this->cmd}'");
				}

				if ($noparam && !isset($cond[4])) {
					throw new InvalidArgumentException("Missing value for item '{$cond[1]}'");
				}

				return $this->formatCondition($cond[1], trim($cond[2], $this->cmd), isset($cond[4]) ? $cond[4] : $parameters, isset($cond[3]) ? $cond[3] : NULL);
			}
		}

		if ($noparam) {
			throw new InvalidArgumentException("Missing value for item '{$condition}'");
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

	/**
	 * Parses inner conditions
	 * @param array 
	 * @param bool indicates that output should be list	 
	 */
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
	 * Formats condition
	 * @param string item identifier
	 * @param string operator
	 * @param mixed  value
	 * @param string modifier
	 */
	private function formatCondition($identifier, $operator, $value, $modifier = NULL)
	{
		$operator = strtolower($operator);

		$value = $modifier ? $this->processModifier($modifier, $value) : $value;

		//tries to translate operator
		if (array_key_exists($operator, $this->operators)) {
			$operator = $this->operators[$operator];
		}

		if ($operator === '=') {
			return [$identifier => $value];
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

		return [(string) $identifier => [$this->formatCmd($operator) => $value]];
	}

	/**
	 * Formats data types by modifiers
	 * @param array ['name' => 'roman', 'age%i' => '27', 'numbers%i[]' => ['1', 2, 2.3]] 
	 * @return array
	 */
	public function processData(array $data)
	{
		$return = [];

		foreach ($data as $key => $item) {
			list($modified, $key) = $this->doubledModifier($key, '%');

			if ($modified && preg_match('#^(.*)%(\w+(?:\[\])?)$#', $key, $parts)) {
				$key = $parts[1];
				$item = $this->processModifier($parts[2], $item);
			} elseif ($item instanceof \DateTime || $item instanceof \DateTimeImmutable) {
				$item = $this->processModifier('dt', $item);
			} elseif (is_array($item)) {
				$item = $this->processData($item);
			}

			$return[$key] = $item;
		}

		return $return;
	}

	/**
	 * Tries to find modifier and returns value in needed type
	 * @param  string s, i, dt, oid...
	 * @param  mixed  object, string, number, bool...
	 * @return mixed
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

	############### internal ##############

	/**
	 * Applies modifier to the inner array via reference 
	 * 
	 * @param string modifier
	 * @param array  sets of values
	 */
	protected function processArray($modifier, array &$values)
	{
		foreach ($values as &$item) {
			$item = $this->processModifier($modifier, $item);
		}
	}

	/**
	 *  Evaluates that modifier could be applied
	 *  %%test%% -> [FALSE, '%test%'], %test -> [TRUE, '%test'], %%%test -> [TRUE, '%%test']
	 * 
	 *  @param string value with modifier %%test%% 
	 *  @param string modifier - %, ...
	 *  @return array [bool, string]
	 */
	private function doubledModifier($value, $modifier)
	{
		if (($count = substr_count($value, $modifier)) > 0) {
			$value = $count > 1 ? str_replace($modifier . $modifier, $modifier, $value) : $value;
			if (($count % 2) > 0) {
				return [TRUE, $value];
			}
		}

		return [FALSE, $value];
	}

}
