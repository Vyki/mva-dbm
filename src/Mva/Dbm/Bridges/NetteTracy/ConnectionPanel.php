<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Bridges\NetteTracy;

use Mva;
use Nette;
use Tracy;
use Mva\Dbm\Connection;

/**
 * User panel for Debugger Bar.
 */
class ConnectionPanel extends Nette\Object implements Tracy\IBarPanel
{

	/** @var int */
	private $maxQueries = 100;

	/** @var int */
	private $count = 0;

	/** @var float */
	private $totalTime;

	/** @var array */
	private $queries = [];

	public static function install(Connection $connection)
	{
		Tracy\Debugger::getBar()->addPanel(new ConnectionPanel($connection));
	}

	public function __construct(Mva\Dbm\Connection $connection)
	{
		$connection->query->onQuery[] = [$this, 'logQuery'];
	}

	public function logQuery($collection, $operation, $parameters, $rows = NULL)
	{
		$this->count++;
		if ($this->count > $this->maxQueries) {
			return;
		}

		$this->queries[] = [
			$collection,
			$operation,
			$parameters,
			$rows
		];
	}

	/**
	 * Renders tab.
	 * @return string
	 */
	public function getTab()
	{
		if (headers_sent() && !session_id()) {
			return;
		}

		ob_start();
		$count = $this->count;
		$queries = $this->queries;
		require __DIR__ . '/templates/ConnectionPanel.tab.phtml';
		return ob_get_clean();
	}

	/**
	 * Renders panel.
	 * @return string
	 */
	public function getPanel()
	{
		ob_start();
		
		if (!$this->count) {
			return;
		}

		$count = $this->count;
		$queries = $this->queries;
		require __DIR__ . '/templates/ConnectionPanel.panel.phtml';
		return ob_get_clean();
	}

}
