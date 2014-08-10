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

use JFusion\Plugin\Plugin_Auth;
use JFusion\User\Userinfo;

/**
 * JFusion Auth Class for SMF 1.1.x
 * For detailed descriptions on these functions please check Plugin_Auth
 *
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage smf2
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Auth extends Plugin_Auth
{
    /**
     * @param Userinfo $userinfo
     * @return string
     */
    function generateEncryptedPassword(Userinfo $userinfo)
    {
        $testcrypt = sha1(strtolower($userinfo->username) . $userinfo->password_clear) ;
        return $testcrypt;
    }
}
