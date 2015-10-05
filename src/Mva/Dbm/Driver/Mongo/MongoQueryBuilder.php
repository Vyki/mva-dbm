<?php

namespace Mva\Dbm\Driver\Mongo;

use Nette,
	Mva\Dbm\Driver\IQuery,
	Mva\Dbm\Driver\IQueryBuilder;

/**
 * Prepares query, projection and parameters.
 * ParamBuilder is inspired by Nette\Database http://doc.nette.org/en/2.3/database by Jakub Vrana, Jan Skrasek, David Grudl
 *
 * @author Roman Vykuka
 * 
 * @property array $group
 * @property int $limit
 * @property int $offset
 * @property string $from
 * @property-read string $cmd
 * @property-read array $order
 * @property-read array $select
 * @property-read array $where
 * @property-read array $having
 * 
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
		$this->setFrom($name);
	}

	public function getFrom()
	{
		return $this->from;
	}

	public function setFrom($name)
	{
		$this->from = (string) $name;
	}

	public function getGroup()
	{
		return $this->group;
	}

	public function setGroup($items)
	{
		$group = [];

		foreach ((array) $items as $item) {
			$group[$item] = $this->formatCmd($item);
		}

		$this->group = $group;
	}

	public function addAggregate($type, $item, $name = NULL)
	{
		$ltype = strtolower($type);
		$this->aggregate[$name ? : '_' . $item . '_' . $ltype] = [$this->formatCmd($ltype) => ($item == '*') ? 1 : $this->formatCmd($item)];
	}

	public function getAggregate()
	{
		if (empty($this->group) && empty($this->aggregate)) {
			return FALSE;
		}

		return ['_id' => empty($this->group) ? NULL : $this->group] + (array) $this->aggregate;
	}

	public function addOrder($items)
	{
		foreach ((array) $items as $item) {
			if (preg_match('#^(\w+(?:\.\w+)*)\s+(ASC|DESC)$#i', $item, $part)) {
				$this->order[$part[1]] = $part[2] === 'ASC' ? 1 : -1;
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
			if (preg_match('#^(\w+)\(([\w_]+|\*)\)(?:\s+AS\s+([\w_]+))?$#i', $item, $part)) {
				$this->addAggregate($part[1], $part[2], isset($part[3]) ? $part[3] : NULL);
				continue;
			}

			if (substr_compare($item, 'distinct ', 0, 10, true) === 1) {
				$this->select['distinct'] = substr($item, 9);
				continue;
			}

			$this->select[$item] = TRUE;
		}
	}

	public function getSelect()
	{
		if (count($this->select) > 1 && isset($this->select['distinct']) && is_string($this->select['distinct'])) {
			unset($this->select['distinct']);
		}

		return $this->select;
	}

	public function setLimit($limit)
	{
		$this->limit = (int) $limit;
	}

	public function getLimit()
	{
		return $this->limit;
	}

	public function setOffset($offset)
	{
		$this->offset = (int) $offset;
	}

	public function getOffset()
	{
		return $this->offset;
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

	public function addHaving($condition, $parameters = [])
	{
		$this->having[] = empty($parameters) ? [$condition] : [$condition => $parameters];
	}

	public function getHaving()
	{
		return $this->having;
	}

	################## Builders ##################

	public function buildSelectQuery()
	{
		if ($aggregate = $this->getAggregate()) {
			return [$this->buildAggreregateQuery(), [], []];
		}

		return [$this->getSelect(), $this->getWhere(), [
			IQuery::SELECT_LIMIT => $this->getLimit(),
			IQuery::SELECT_OFFSET => $this->getOffset(),
			IQuery::SELECT_ORDER => $this->getOrder()
		]];
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

	public function buildUpdateQuery()
	{
		return [$this->getUpdate(), $this->getWhere()];
	}

	public function buildInsertQuery()
	{
		throw new Nette\NotImplementedException('Insert query is not implemented in MongoQueryBilder class!');
	}

	public function importConditions(IQueryBuilder $builder)
	{
		$this->where = $builder->where;
	}

	################## Internals ##################

	private function formatCmd($cmd)
	{
		return $this->cmd . $cmd;
	}

}
