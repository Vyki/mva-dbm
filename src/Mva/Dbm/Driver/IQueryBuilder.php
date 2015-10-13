<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Driver;

interface IQueryBuilder
{

	function getFrom();

	function setFrom($name);

	function getGroup();

	function setGroup($items);

	function getAggregate();

	function addAggregate($type, $item, $name = NULL);

	function getOrder();

	function addOrder($items);

	function addSelect($items);

	function addUnselect($items);

	function getSelect();

	function setLimit($limit);

	function getLimit();

	function getOffset();

	function setOffset($offset);

	function getWhere();

	function addWhere($condition, $parameters = []);

	function getHaving();

	function addHaving($condition, $parameters = []);

	################## Builders ##################

	function buildSelectQuery();

	function importConditions(IQueryBuilder $builder);
}
