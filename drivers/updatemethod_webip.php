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

/**
 * A driver that attempts to retreive a public IP address from a HTTP GET
 * request on a URL.
 */
class Updatemethod_webip implements Update
{
	const NAME = 'Web IP Update';

	const VERSION = '1.0';

	private $ipURL;

	public function __construct($settings)
	{
		Logger::log(Logger::LEVEL_DEBUG, 'Loaded ' . Updatemethod_webip::NAME . ' ' . Updatemethod_webip::VERSION);

		$this->ipURL = $settings->ipurl;

		Logger::log(Logger::LEVEL_DEBUG, 'Using URL ' . $this->ipURL . ' to fetch public IP address');
	}

	public function getIP()
	{
		$ip = file_get_contents($this->ipURL);

		Logger::log(Logger::LEVEL_DEBUG, 'Got IP address ' . $ip);

		return $ip;
	}
}

?>
