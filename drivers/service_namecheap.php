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
 * A driver to update Namecheap DNS records.
 */
class Service_namecheap implements Service
{
	const NAME = 'Namecheap Service';

	const VERSION = '1.0';

	const NAMECHEAP_URL = 'https://dynamicdns.park-your-domain.com/update?';

	/**
	 * Url to use to update the IP address.
	 * @var string
	 */
	private $updateUrl;

	/**
	 * Settings that were given to this service.
	 * @var SimpleXML Element
	 */
	private $settings;

	public function __construct($settings)
	{
		Logger::log(Logger::LEVEL_DEBUG, 'Loaded ' . Service_namecheap::NAME . ' ' . Service_namecheap::VERSION);

		//check if needed settings are specified
		if(isset($settings->host) && isset($settings->domain) && isset($settings->password))
		{
			$this->updateUrl = Service_namecheap::NAMECHEAP_URL . 'host=' . urlencode($settings->host) . '&domain=' . urlencode($settings->domain) . '&password=' . urlencode($settings->password) . '&ip=';

			$this->settings = $settings;
		}
		else
		{
			throw new RuntimeException('Missing required setting. Namecheap service driver needs host, domain and password.');
		}
	}

	/**
	 * Lookup a fully qualified domain name and get its IP address. If a dns server was specified in the settings, then that dns server is used using the dig command.
	 * @param string $domainname
	 * @return string containing IP address.
	 */
	private function lookupFQDN($domainname)
	{
		//dig +short @dnsserver domainname A
		if(isset($this->settings->dnsserver) && filter_var($this->settings->dnsserver, FILTER_VALIDATE_IP))
		{
			Logger::log(Logger::LEVEL_DEBUG, 'Using dig command for lookup...');

			return exec('dig +short @' . escapeshellcmd($this->settings->dnsserver) . ' ' . escapeshellcmd($domainname) . ' A');
		}
		else
		{
			return gethostbyname($domainname);
		}
	}

	public function update($ipaddress)
	{
		$fqdn = $this->settings->host . '.' . $this->settings->domain;

		$currentIP = $this->lookupFQDN($fqdn);

		if(filter_var($currentIP, FILTER_VALIDATE_IP))
		{
			if($currentIP != $ipaddress)
			{
				Logger::log(Logger::LEVEL_DEBUG, $fqdn . ' Host has old IP ' . $currentIP . '. Updating to ' . $ipaddress);

				$result = simplexml_load_file($this->updateUrl);

				if($result)
				{
					if($result->ErrCount == 0)
					{
						// success
						Logger::log(Logger::LEVEL_DEBUG, 'Successful update on ' . $fqdn);
					}
					else
					{
						Logger::log(Logger::LEVEL_CRITICAL, 'Failed attempting to update ' . $fqdn . ' due to ' . print_r($result->errors, true));

						return false;
					}
				}
				else
				{
					Logger::log(Logger::LEVEL_CRITICAL, 'Invalid response. Failed attempting to update ' . $fqdn . '.');

					throw new RuntimeException('Namecheap service driver bad XML file. Failed updating.');
				}
			}
			else
			{
				Logger::log(Logger::LEVEL_DEBUG, $fqdn . ' Host IP ' . $currentIP . ' is current, not updating');
			}

			return true;
		}
		else
		{
			Logger::log(Logger::LEVEL_CRITICAL, 'Failed DNS lookup for ' . $fqdn . '.');

			throw new RuntimeException('Namecheap service driver DNS query failed. Unable to get current IP address for host.');
		}

		return false;
	}
}
?>
