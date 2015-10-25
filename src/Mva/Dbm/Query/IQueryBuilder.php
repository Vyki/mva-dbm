<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Query;

interface IQueryBuilder
{

	function from($name);

	function getFrom();

	function group($items);

	function aggregate($type, $item = NULL, $name = NULL);

	function addAggregate($type, $item, $name = NULL);

	function order($items);

	function addOrder($items);

	function select($items);

	function addSelect($items);

	function limit($limit, $offset = NULL);

	function offset($offset);

	function where($condition, $parameters = []);

	function addWhere($condition, $parameters = []);

	function getWhere();

	function having($condition, $parameters = []);

	function addHaving($condition, $parameters = []);

	function buildSelectQuery();

	function importConditions(IQueryBuilder $builder);
}
