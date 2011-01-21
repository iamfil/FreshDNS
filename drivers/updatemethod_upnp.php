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
 * A driver that attempts to retreive a public IP address using UPnP.
 */
class Updatemethod_upnp implements Update
{
	/**
	 * Name of this driver.
	 * @var string
	 */
	const NAME = 'UPnP Update Driver';

	/**
	 * Version of this driver.
	 * @var string
	 */
	const VERSION = '1.0';

	/**
	 * GUPnP control point resource.
	 * @var resource
	 */
	private $controlPoint;

	/**
	 * The IP address obtained from UPnP.
	 * @var string containg an IP address.
	 */
	private $ipAddress;

	public function __construct($settings)
	{
		Logger::log(Logger::LEVEL_DEBUG, 'Loaded ' . Updatemethod_upnp::NAME . ' ' . Updatemethod_upnp::VERSION);

		$context = gupnp_context_new();

		$this->controlPoint = gupnp_control_point_new($context, 'urn:schemas-upnp-org:service:WANIPConnection:1');

		gupnp_control_point_callback_set($this->controlPoint, GUPNP_SIGNAL_SERVICE_PROXY_AVAILABLE, Array(&$this, 'service_proxy_available'));
	}

	/**
	 * Clean up by stopping the search.
	 */
	public function __destruct()
	{
		gupnp_control_point_browse_stop($this->controlPoint);
	}

	private function service_proxy_available($proxy, $arg)
	{
		// stop searching, already found wan connection
		gupnp_control_point_browse_stop($this->controlPoint);

		$out = Array(Array('NewExternalIPAddress', GUPNP_TYPE_STRING));
		$serviceParams = gupnp_service_proxy_send_action($proxy, 'GetExternalIPAddress', Array(), $out);

		$this->ipAddress = $serviceParams[0][2];

		Logger::log(Logger::LEVEL_DEBUG, Updatemethod_upnp::NAME . ' got IP address ' . $this->ipAddress);
	}

	public function getIP()
	{
		Logger::log(Logger::LEVEL_DEBUG, Updatemethod_upnp::NAME . ' waiting for callback');

		// this will block
		gupnp_control_point_browse_start($this->controlPoint);

		return $this->ipAddress;
	}
}
?>
