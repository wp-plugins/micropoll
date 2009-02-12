<?php
/**
 * Plugin Name: MicroPoll
 * Plugin URI:  http://www.chrisabernethy.com/wordpress-plugins/micropoll/
 * Description: Easily integrate web-based polls from MicroPoll into WordPress
 * Version:     1.0
 * Author:      Chris Abernethy
 * Author URI:  http://www.chrisabernethy.com/
 * Text Domain: micropoll
 * Domain Path: /locale/
 *
 * Copyright 2008 Chris Abernethy
 *
 * This file is part of MicroPoll.
 *
 * MicroPoll is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * MicroPoll is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MicroPoll.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

// Include all class files up-front so that we don't have to worry about the
// include path or any globals containing the plugin base path.

require_once 'lib/MicroPoll/Structure.php';
require_once 'lib/MicroPoll/Structure/Options.php';
require_once 'lib/MicroPoll/Structure/View.php';
require_once 'lib/MicroPoll.php';

// Run the plugin.
MicroPoll::run(__FILE__);

/* EOF */