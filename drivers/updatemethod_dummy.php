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
 * This is a dummy update method for debugging and to illustrates how one is
 * made.
 */
class Updatemethod_dummy implements Update
{
	const NAME = 'Dummy Update Method';

	const VERSION = '1.0';

	public function __construct($settings)
	{

	}

	public function getIP()
	{
		return '127.0.0.1';
	}
}
?>
