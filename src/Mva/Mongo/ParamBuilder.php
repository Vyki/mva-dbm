<?php

namespace Mva\Mongo;

use Nette,
	Nette\Utils\Strings;

/**
 * Prepares query, projection and parameters.
 * ParamBuilder is inspired by Nette\Database http://doc.nette.org/en/2.3/database by Jakub Vrana, Jan Skrasek, David Grudl
 *
 * @author Roman Vykuka
 * 
 * @property array $group
 * @property-read array $order
 * @property-read array $select
 * @property-read array $where
 * @property-read array $having
 * 
 */
class ParamBuilder extends Nette\Object
{

	/** @var string command prefix */
	private $cmd = '$';

	/** @var array of projection items */
	private $select = array();

	/** @var array of conditions */
	private $where = array();

	/** @var array of columns to order by */
	private $order = array();

	/** @var array columns to grouping */
	private $group = array();

	/** @var array of grouping conditions */
	private $having = array();

	/** @var array of aggregation functions */
	private $aggregate = array();

	/** @var array of $SQL like operators and mongo equivalents */
	private $operators = array(
		'=' => '=',
		'<>' => 'ne',
		'!=' => 'ne',
		'<=' => 'lte',
		'>=' => 'gte',
		'<' => 'lt',
		'>' => 'gt',
		'in' => 'in',
		'not in' => 'nin'
	);

	public function __construct()
	{
		$this->cmd = ini_get('mongo.cmd') ?: '$';
	}

	public function getGroup()
	{
		return $this->group;
	}

	public function setGroup($items)
	{
		$group = array();

		foreach ((array) $items as $item) {
			$group[$item] = $this->formatCmd($item);
		}

		$this->group = $group;
	}

	public function addAggregate($type, $item, $name = NULL)
	{
		$ltype = Nette\Utils\Strings::lower($type);
		$this->aggregate[$name ?: '_' . $item . '_' . $ltype] = array($this->formatCmd($ltype) => ($item == '*') ? 1 : $this->formatCmd($item));
	}

	public function getAggregate()
	{
		if (empty($this->group) && empty($this->aggregate)) {
			return FALSE;
		}

		return array('_id' => empty($this->group) ? NULL : $this->group) + (array) $this->aggregate;
	}

	public function addOrder($items)
	{
		foreach ((array) $items as $item) {
			$match = Nette\Utils\Strings::match($item, '#^(\w+(?:\.\w+)*)\s+(ASC|DESC)$#i');

			if (!empty($match)) {
				$this->order[$match[1]] = $match[2] === 'ASC' ? 1 : -1;
			}
		}
	}

	public function getOrder()
	{
		return $this->order;
	}

	public function addUnselect($items)
	{
		foreach ((array) $items as $item) {
			$this->select[$item] = FALSE;
		}
	}

	public function addSelect($items)
	{
		foreach ((array) $items as $item) {
			$match = Nette\Utils\Strings::match($item, '#^(\w+)\(([\w_]+|\*)\)(?:\s+AS\s+([\w_]+))?$#i');

			if (!empty($match)) {
				$this->addAggregate($match[1], $match[2], isset($match[3]) ? $match[3] : NULL);
			} else {
				$this->select[$item] = TRUE;
			}
		}
	}

	public function getSelect()
	{
		return $this->select;
	}

	public function addWhere($condition, $parameters = array())
	{
		$this->where[] = $this->parseCondition($condition, $parameters);
	}

	public function getWhere()
	{
		if (count($this->where) === 1) {
			return $this->where[0];
		} else {
			return empty($this->where) ? array() : array($this->formatCmd('and') => $this->where);
		}
	}

	public function addHaving($condition, $parameter = array())
	{
		$this->having[] = $this->parseCondition($condition, $parameter);
	}

	public function getHaving()
	{
		if (count($this->having) === 1) {
			return $this->having[0];
		} else {
			return empty($this->having) ? array() : array($this->formatCmd('and') => $this->having);
		}
	}

	################## Builders ##################

	public function buildSelectQuery()
	{
		return array($this->getSelect(), $this->getWhere());
	}

	public function buildAggreregateQuery()
	{
		$query = array();

		if (($select = $this->getSelect()) && !empty($select)) {
			$query[] = array($this->formatCmd('project') => $select);
		}

		if (($where = $this->getWhere()) && !empty($where)) {
			$query[] = array($this->formatCmd('match') => $where);
		}

		if (($aggregate = $this->getAggregate()) && !empty($aggregate)) {
			$query[] = array($this->formatCmd('group') => $aggregate);
		}

		if (($sort = $this->getOrder()) && !empty($sort)) {
			$query[] = array($this->formatCmd('sort') => $sort);
		}

		if (($having = $this->getHaving()) && !empty($having)) {
			$query[] = array($this->formatCmd('match') => $having);
		}

		return $query;
	}

	public function importConditions(ParamBuilder $builder)
	{
		$this->where = $builder->where;
	}

	################## Internals ##################

	private function formatCmd($cmd)
	{
		return $this->cmd . $cmd;
	}

	private function formatCondition($key, $op, $value)
	{
		$operator = Strings::lower($op);

		//tries to translate operator
		if (array_key_exists($operator, $this->operators)) {
			$operator = $this->operators[$operator];
		}

		if ($operator === '=') {
			return array($key => $value);
		}

		//$in and $nin need to reset keys
		if ($operator === 'in' || $operator === 'nin') {
			$value = array_values((array) $value);
		}

		//parses inner condition in $elemMatch
		if ($operator === 'elem match' && is_array($value)) {
			$value = $this->parseDeepCondition($value);
		}

		//translates SQL like operator to mongo format ELEM MATCH => $elemMatch
		if (strpos($operator, ' ') !== FALSE) {
			$operator = lcfirst(str_replace(' ', '', ucwords($operator)));
		}

		return array((string) $key => array($this->formatCmd($operator) => $value));
	}

	private function parseDeepCondition(array $parameters)
	{
		$opcond = array();

		foreach ($parameters as $key => $param) {
			$ccond = is_int($key) ? $this->parseCondition($param) : $this->parseCondition($key, $param);
			reset($ccond);
			$opcond[key($ccond)] = current($ccond);
		}

		return $opcond;
	}

	private function parseCondition($condition, $parameters = array())
	{
		$cond = Strings::match($condition, '#^'
						. '(\w+(?:\.\w+)*)\s' //item.subitem.subitem
						. '('
						. '(?:[A-Z]+(?:\s[A-Z]+)*)' // IN, NOT IN, EXISTS, CURRENT DATE ...
						. '|'
						. '(?:\<\>|\!?\=|\>\=|\<\=|\>|\<)' //comparison =, <>, !=, >, <
						. ')\s?'
						. '(\?|(?:\w+\s?)+)?' //value || ?
						. '$#');

		//comparison operators or mongo operator in SQL like format
		if (!empty($cond)) {
			return $this->formatCondition($cond[1], $cond[2], (!isset($cond[3]) || $cond[3] === '?') ? $parameters : $cond[3]);
		}

		//logical operator [$or => ['a > ?' => 5, 'b <= ?' => 10]]
		if (!empty($parameters) && ($condition === $this->formatCmd('or') || $condition === $this->formatCmd('and'))) {
			return array($condition => $this->parseDeepCondition($parameters));
		}

		//IN operator if first element doesn't start by $
		if (is_array($parameters) && reset($parameters) && !Strings::startsWith(key($parameters), $this->cmd)) {
			return $this->formatCondition($condition, 'IN', $parameters);
		}

		//other conditions like ['$exists' => TRUE], ['a' => 5]
		return array($condition => $parameters);
	}

}
