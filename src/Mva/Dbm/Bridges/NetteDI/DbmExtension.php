<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Bridges\NetteDI;

use Tracy\Debugger;
use Nette\DI\CompilerExtension;

class DbmExtension extends CompilerExtension
{

	public function loadConfiguration()
	{
		$config = $this->getConfig();
		$this->setupConnection($config);
	}

	protected function setupConnection(array $config)
	{
		$builder = $this->getContainerBuilder();

		$definition = $builder->addDefinition($this->prefix('connection'))
				->setClass('Mva\Dbm\Connection')
				->setArguments([
			'config' => $config,
		]);
		
		$definition->addSetup('connect');

		if (isset($config['debugger'])) {
			$debugger = $config['debugger'];
		} else {
			$debugger = class_exists('Tracy\Debugger', FALSE) && Debugger::$productionMode === Debugger::DEVELOPMENT;
		}

		if ($debugger) {
			$definition->addSetup('Mva\Dbm\Bridges\NetteTracy\ConnectionPanel::install', ['@self']);
		}
	}

}
