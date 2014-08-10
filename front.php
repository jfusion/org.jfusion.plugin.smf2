<?php namespace JFusion\Plugins\smf2;
/**
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage smf2
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

use JFusion\Plugin\Plugin_Front;

/**
 * JFusion Front Class for SMF 1.1.x
 * For detailed descriptions on these functions please check Plugin_Front
 *
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage smf2
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Front extends Plugin_Front
{
    /**
     * @return string
     */
    function getRegistrationURL()
	{
		return 'index.php?action=register';
	}

    /**
     * @return string
     */
    function getLostPasswordURL()
	{
		return 'index.php?action=reminder';
	}

    /**
     * @return string
     */
    function getLostUsernameURL()
	{
		return 'index.php?action=reminder';
	}
}