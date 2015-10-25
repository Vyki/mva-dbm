<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver\Mongo;

use Nette,
	Mva\Dbm\Query\IQuery,
	Mva\Dbm\Query\IQueryBuilder;

/**
 * MongoQueryBuilder is inspired by Nette\Database https://github.com/nette/database by Jakub Vrana, Jan Skrasek, David Grudl
 *
 * @property-read int $limit
 * @property-read int $offset
 * @property-read array $group
 * @property-read string $from
 * @property-read array $order
 * @property-read array $where
 * @property-read array $select
 * @property-read array $having
 */
class MongoQueryBuilder extends Nette\Object implements IQueryBuilder
{

	/** @var string command prefix */
	private $cmd = '$';

	/** @var string from collection */
	private $from = '';

	/** @var array of projection items */
	private $select = [];

	/** @var array of conditions */
	private $where = [];

	/** @var array of columns to order by */
	private $order = [];

	/** @var array of columns to grouping */
	private $group = [];

	/** @var array of grouping conditions */
	private $having = [];

	/** @var array of aggregation functions */
	private $aggregate = [];

	/** @var type Records limit */
	private $limit;

	/** @var int Records offset */
	private $offset;

	public function __construct($name = '')
	{
		$this->cmd = ini_get('mongo.cmd') ? : '$';
		$this->from($name);
	}

	public function from($name)
	{
		$this->from = (string) $name;
	}

	public function getFrom()
	{
		return $this->from;
	}

	######################### aggregation ##############################

	public function group($items)
	{
		$group = [];

		foreach ((array) $items as $item) {
			$group[$item] = $this->formatCmd($item);
		}

		$this->group = $group;

		return $this;
	}

	public function getGroup()
	{
		return $this->group;
	}

	public function aggregate($type, $item = NULL, $name = NULL)
	{
		$this->aggregate = [];

		if (!empty($type) && !empty($item)) {
			$this->addAggregate($type, $item, $name);
		}

		return $this;
	}

	public function addAggregate($type, $item, $name = NULL)
	{
		$ltype = strtolower($type);
		$this->aggregate[$name ? : '_' . $item . '_' . $ltype] = [$this->formatCmd($ltype) => ($item == '*') ? 1 : $this->formatCmd($item)];

		return $this;
	}

	public function getAggregate()
	{
		if (empty($this->group) && empty($this->aggregate)) {
			return FALSE;
		}

		return ['_id' => empty($this->group) ? NULL : $this->group] + (array) $this->aggregate;
	}

	######################### ordering ##############################

	public function order($items)
	{
		$this->order = [];

		if (!empty($items)) {
			$this->addOrder($items);
		}

		return $this;
	}

	public function addOrder($items)
	{
		foreach ((array) $items as $key => $item) {
			if (is_string($key)) {
				$this->order[$key] = empty($item) || $item < 0 ? -1 : 1;
				continue;
			}

			if (preg_match('#^(\w+(?:\.\w+)*)\s+(ASC|DESC)$#i', $item, $part)) {
				$this->order[$part[1]] = $part[2] === 'ASC' ? 1 : -1;
			}
		}

		return $this;
	}

	public function getOrder()
	{
		return $this->order;
	}

	######################### projection ##############################

	public function select($items)
	{
		$this->select = [];

		if (!empty($items)) {
			$this->addSelect($items);
		}

		return $this;
	}

	public function addSelect($items)
	{
		foreach ((array) $items as $key => $item) {
			if (is_string($key)) {
				$this->select[$key] = $item;
				continue;
			}

			if (is_string($item) && preg_match('#^(\w+)\(([\w_]+|\*)\)(?:\s+AS\s+([\w_]+))?$#i', $item, $part)) {
				$this->addAggregate($part[1], $part[2], isset($part[3]) ? $part[3] : NULL);
				continue;
			}

			$this->select[] = $item;
		}

		return $this;
	}

	public function getSelect()
	{
		return $this->select;
	}

	######################### limit and offset ##############################

	public function limit($limit, $offset = NULL)
	{
		$this->limit = (int) $limit;
		$offset && $this->offset($offset);
	}

	public function getLimit()
	{
		return $this->limit;
	}

	public function offset($offset)
	{
		$this->offset = (int) $offset;
	}

	public function getOffset()
	{
		return $this->offset;
	}

	######################### where condition ##############################

	public function where($conditions, $parameters = [])
	{
		$this->where = [];

		if (!empty($conditions)) {
			$this->addWhere($conditions, $parameters);
		}

		return $this;
	}

	public function addWhere($condition, $parameters = [])
	{
		if (is_array($condition) && $parameters === []) {
			foreach ($condition as $key => $val) {
				if (is_int($key)) {
					$this->addWhere($val);
				} else {
					$this->addWhere($key, $val);
				}
			}
			return $this;
		}

		$this->where[] = empty($parameters) ? [$condition] : [$condition => $parameters];

		return $this;
	}

	public function getWhere()
	{
		return $this->where;
	}

	######################### having condition ##############################

	public function having($conditions, $parameters = [])
	{
		$this->having = [];

		if (!empty($conditions)) {
			$this->addHaving($conditions, $parameters);
		}

		return $this;
	}

	public function addHaving($condition, $parameters = [])
	{
		if (is_array($condition) && $parameters === []) {
			foreach ($condition as $key => $val) {
				if (is_int($key)) {
					$this->addHaving($val);
				} else {
					$this->addHaving($key, $val);
				}
			}
			return $this;
		}

		$this->having[] = empty($parameters) ? [$condition] : [$condition => $parameters];

		return $this;
	}

	public function getHaving()
	{
		return $this->having;
	}

	################## builders ##################

	public function buildSelectQuery()
	{
		if ($aggregate = $this->getAggregate()) {
			return [$this->buildAggreregateQuery(), [], []];
		}

		$options = [];

		if ($limit = $this->getLimit()) {
			$options[IQuery::SELECT_LIMIT] = $limit;
		}

		if ($offset = $this->getOffset()) {
			$options[IQuery::SELECT_OFFSET] = $offset;
		}

		if (($sort = $this->getOrder()) && !empty($sort)) {
			$options[IQuery::SELECT_ORDER] = $sort;
		}

		return [$this->getSelect(), $this->getWhere(), $options];
	}

	public function buildAggreregateQuery()
	{
		$query = [];

		if (($select = $this->getSelect()) && !empty($select)) {
			$query[] = [$this->formatCmd('project') => $select];
		}

		if (($where = $this->getWhere()) && !empty($where)) {
			$query[] = [$this->formatCmd('match') => $where];
		}

		if (($aggregate = $this->getAggregate()) && !empty($aggregate)) {
			$query[] = [$this->formatCmd('group') => $aggregate];
		}

		if (($sort = $this->getOrder()) && !empty($sort)) {
			$query[] = [$this->formatCmd('sort') => $sort];
		}

		if (($having = $this->getHaving()) && !empty($having)) {
			$query[] = [$this->formatCmd('match') => $having];
		}

		if ($offset = $this->getOffset()) {
			$query[] = [$this->formatCmd('skip') => $offset];
		}

		if ($limit = $this->getLimit()) {
			$query[] = [$this->formatCmd('limit') => $limit];
		}

		return $query;
	}

	public function importConditions(IQueryBuilder $builder)
	{
		$this->where = $builder->where;
	}

	################## Internal ##################

	private function formatCmd($cmd)
	{
		return $this->cmd . $cmd;
	}

}
