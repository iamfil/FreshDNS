<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 3 as published
 * by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('VERSION', '1.0');

/**
 * Enable displaying debug messages. Otherwise, only critical errors are written
 * to the log file.
 * @var bool
 */
define('DEBUG', true);

/**
 * Location of configuration file.
 * @var string
 */
define('CONFIG_FILE', __DIR__ . '/config.xml');

/**
 * Directory where drivers are stored. all files in this folder will be loaded.
 * @var string
 */
define('DRIVERS_DIR', __DIR__ . '/drivers');

/**
 * Path to store the log file relative to location of this file.
 * @var string
 */
define('LOG_FILE', __DIR__ . '/freshdnslog.txt');

/**
 * Needed methods for all DNS service drivers to implement.
 */
interface Service
{
	/**
	 * Constructor for all drivers to implement.
	 * @param SimpleXMLObject $settings Settings for service instance.
	 */
	public function __construct($settings);

	/**
	 * Method to update IP address of a DNS record.
	 * @param String IP address to update record to.
	 * @return Boolean true, if sucessfull; false, if failed.
	 */
	public function update($ipaddress);
}

/**
 * Methods all update drivers must implement.
 */
interface Update
{
	/**
	 * Constructor all update drivers must have.
	 * @param SimpleXMLObject $settings contains settings for driver.
	 */
	public function __construct($settings);

	/**
	 * Returns IP address needed for service drivers.
	 * @return String An IP address.
	 */
	public function getIP();
}

/**
 * Logging class to log events. Events are written to the screen or written to
 * a file depending on DEBUG setting.
 */
class Logger
{
	/**
	 * Logging level where the most details are written.
	 * @var int
	 */
	const LEVEL_DEBUG = 1;

	/**
	 * Logging level where only critical errors are written.
	 * @var int
	 */
	const LEVEL_CRITICAL = 2;

	/**
	 * Get the textual representation of the level.
	 * @param $level int debug level to return string for.
	 * @return string containing a textual representation of the error.
	 */
	private static function getSeverity($level)
	{
		switch($level)
		{
			case Logger::LEVEL_DEBUG:
				{
					return '[Debug] - ';
				}
			case Logger::LEVEL_CRITICAL:
				{
					return '[Critical] - ';
				}
			default:
				{
					throw new InvalidArgumentException('Invalid level specified.');
				}
		}
	}

	/**
	 * Log an event either to the screen or a file.
	 * @param $level int debug level found in this class.
	 * @param $message string containing the message to write.
	 */
	public static function log($level, $message)
	{
		if(DEBUG)
		{
			// write messages onto the screen
			echo(Logger::getSeverity($level) . $message . "\n");
			flush();
		}
		elseif(!DEBUG && $level === Logger::LEVEL_CRITICAL)
		{
			// log only critical errors
			$written = file_put_contents(LOG_FILE, '[Critical] - ' . date('m/j/y - g:i:s A - ') . $message . "\n", FILE_APPEND);

			if(!$written)
			{
				// throw exception, could not write to log file
				throw new RuntimeException('Failed writting to log file.');
			}
		}
	}
}

/**
 * Class to hold an entry in the config file to process.
 */
class FreshDNSEntry
{
	/**
	 * Holds updatemethod object to be used.
	 * @var UpdateTemplate
	 */
	private $updatemethod;

	/**
	 * Holds service that will be updated.
	 * @var ServiceTemplate
	 */
	private $service;

	/**
	 * Number of this entry.
	 * @var int
	 */
	private $entryNumber;

	/**
	 * Create a new FreshDNS Entry.
	 * @param $service object that will be updated.
	 * @param $updatemethod object to use to update.
	 * @param $entry number of this entry.
	 */
	public function __construct($service, $updatemethod, $entry)
	{
		$this->service = $service;

		$this->updatemethod = $updatemethod;

		$this->entryNumber = $entry;
	}

	/**
	 * Process this entry using the update method to update the service.
	 */
	public function process()
	{
		Logger::log(Logger::LEVEL_DEBUG, 'Processing entry ' . $this->entryNumber);

		if($this->service->update($this->updatemethod->getIP()))
		{
			Logger::log(Logger::LEVEL_DEBUG, 'Successful update on entry ' . $this->entryNumber);
		}
		else
		{
			Logger::log(Logger::LEVEL_CRITICAL, 'Failed updating on entry ' . $this->entryNumber);
		}
	}
}

/**
 * Main FreshDNS application class.
 */
class FreshDNS
{
	/**
	 * List of entries to update.
	 * @var FreshDNSEntry
	 */
	private $freshdnsentries;

	/**
	 * Load drivers and settings, prepare for updating DNS records.
	 */
	public function __construct()
	{
		Logger::log(Logger::LEVEL_DEBUG, 'FreshDNS ' . VERSION);

		$this->loadDrivers();

		$this->loadConfig();
	}

	/**
	 * Load all drivers.
	 */
	private function loadDrivers()
	{
		$count = 0;

		if($dirhandle = opendir(DRIVERS_DIR))
		{
			while(($filename = readdir($dirhandle)) !== false)
			{
				$fullpath = DRIVERS_DIR . '/' . $filename;

				$extension = pathinfo($fullpath, PATHINFO_EXTENSION);

				if($filename != '.' && $filename != '..' && $extension == 'php')
				{
					include($fullpath);

					++$count;
				}
			}

			Logger::log(Logger::LEVEL_DEBUG, 'Loaded ' . $count . ' drivers');
		}
		else
		{
			throw new RuntimeException('Unable to open directory. Bad path specified?');
		}
	}

	/**
	 * Load configuration, create service and update objects.
	 */
	private function loadConfig()
	{
		$servicentry = NULL;

		$updatemethodentry = NULL;

		if(!file_exists(CONFIG_FILE))
		{
			throw new RuntimeException('Configuration file missing. Bad path specified?');
		}

		$xmlconfig = simplexml_load_file(CONFIG_FILE);

		if(!$xmlconfig)
		{
			throw new RuntimeException('Unable to load configuration. Bad XML?');
		}

		$count = 0;
		foreach($xmlconfig->freshentry as $freshentry)
		{
			$serviceclass = 'Service_' . $freshentry->service;

			$updatemethodclass = 'Updatemethod_' . $freshentry->updatemethod;

			// attempt to create the service specified in the entry
			if(class_exists($serviceclass))
			{
				$servicentry = new $serviceclass($freshentry);
			}
			else
			{
				throw new RuntimeException('Could not create service ' . $freshentry->service . '. Missing service driver?');
			}

			// attempt to create update method
			if(class_exists($updatemethodclass))
			{
				$updatemethodentry = new $updatemethodclass($freshentry);
			}
			else
			{
				throw new RuntimeException('Could not create update method ' . $freshentry->updatemethod . '. Missing update method driver?');
			}

			++$count;

			$this->freshdnsentries[] = new FreshDNSEntry($servicentry, $updatemethodentry, $count);
		}

		Logger::log(Logger::LEVEL_DEBUG, 'Loaded ' . count($this->freshdnsentries) . ' entries');
	}

	/**
	 * Update all service entries.
	 */
	public function updateServices()
	{
		foreach($this->freshdnsentries as $entry)
		{
			$entry->process();
		}
	}
}

$freshdns = new FreshDNS();

$freshdns->updateServices();

?>
