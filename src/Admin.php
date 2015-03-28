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

use JFusion\Application\Application;
use JFusion\Factory;
use JFusion\Framework;

use JFusion\User\Groups;
use Joomla\Language\Text;

use Psr\Log\LogLevel;

use Exception;
use stdClass;

/**
 * JFusion Admin Class for SMF 1.1.x
 * For detailed descriptions on these functions please check Plugin_Admin
 *
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage smf2
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Admin extends \JFusion\Plugin\Admin
{
    /**
     * @return string
     */
    function getTablename()
    {
        return 'members';
    }

    /**
     * @param string $softwarePath
     *
     * @return array
     */
    function setupFromPath($softwarePath)
    {
	    $myfile = $softwarePath . 'Settings.php';

        //try to open the file
        $params = array();
	    $lines = $this->readFile($myfile);
        if ($lines === false) {
            Framework::raise(LogLevel::WARNING, Text::_('WIZARD_FAILURE') . ': ' . $myfile . ' ' . Text::_('WIZARD_MANUAL'), $this->getJname());
	        return false;
        } else {
	        $config = array();
	        //parse the file line by line to get only the config variables
	        foreach ($lines as $line) {
		        if (strpos($line, '$') === 0) {
			        $vars = explode('\'', $line);
			        if(isset($vars[1]) && isset($vars[0])){
				        $name = trim($vars[0], ' $=');
				        $value = trim($vars[1], ' $=');
				        $config[$name] = $value;
			        }
		        }
	        }

            //Save the parameters into the standard JFusion params format
            $params['database_host'] = isset($config['db_server']) ? $config['db_server'] : '';
            $params['database_type'] = 'mysql';
            $params['database_name'] = isset($config['db_name']) ? $config['db_name'] : '';
            $params['database_user'] = isset($config['db_user']) ? $config['db_user'] : '';
            $params['database_password'] = isset($config['db_passwd']) ? $config['db_passwd'] : '';
            $params['database_prefix'] = isset($config['db_prefix']) ? $config['db_prefix'] : '';
            $params['source_url'] = isset($config['boardurl']) ? $config['boardurl'] : '';
            $params['cookie_name'] = isset($config['cookiename']) ? $config['cookiename'] : '';
            $params['source_path'] = $softwarePath;
        }
        return $params;
    }

    /**
     * Returns the a list of users of the integrated software
     *
     * @param int $limitstart start at
     * @param int $limit number of results
     *
     * @return array
     */
    function getUserList($limitstart = 0, $limit = 0)
    {
	    try {
		    // initialise some objects
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('member_name as username, email_address as email')
			    ->from('#__members');

		    $db->setQuery($query, $limitstart, $limit);
		    $userlist = $db->loadObjectList();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $userlist = array();
	    }
        return $userlist;
    }

    /**
     * @return int
     */
    function getUserCount()
    {
	    try {
		    //getting the connection to the db
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('count(*)')
			    ->from('#__members');

		    $db->setQuery($query);

		    //getting the results
		    return $db->loadResult();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    return 0;
	    }
    }

    /**
     * get default user group list
     *
     * @return array array with object with default user group list
     */
    function getUsergroupList()
    {
	    //getting the connection to the db
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->select('id_group as id, group_name as name')
		    ->from('#__membergroups')
		    ->where('min_posts = -1');

	    $db->setQuery($query);
	    $usergrouplist = $db->loadObjectList();
	    //append the default usergroup
	    $default_group = new stdClass;
	    $default_group->id = 0;
	    $default_group->name = 'Default User';
	    $usergrouplist[] = $default_group;
	    return $usergrouplist;
    }

    /**
     * @return array
     */
    function getDefaultUsergroup()
    {
	    $usergroup = Groups::get($this->getJname(), true);

	    $group = array();
	    if ($usergroup !== null) {
		    $db = Factory::getDatabase($this->getJname());

		    if (isset($usergroup->groups)) {
			    $groups = $usergroup->groups;
		    } else {
			    $groups = array();
		    }

		    $groups[] = $usergroup->defaultgroup;
		    foreach($groups as $g) {
			    if ($g != 0) {
				    $query = $db->getQuery(true)
					    ->select('group_name')
					    ->from('#__membergroups')
					    ->where('id_group = ' . (int)$g);

				    $db->setQuery($query);
				    $group[] = $db->loadResult();
			    } else {
				    $group[] = 'Default Usergroup';
			    }
		    }
	    }
	    return $group;
    }

    /**
     * return list of post groups
     *
     * @return object with default user group
     */
    function getUserpostgroupList()
    {
	    try {
		    //getting the connection to the db
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('id_group as id, group_name as name')
			    ->from('#__membergroups')
			    ->where('min_posts != -1');

		    $db->setQuery($query);
		    return $db->loadObjectList();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    return array();
	    }
    }

    /**
     * @return bool
     */
    function allowRegistration()
    {
	    $result = false;
	    try {
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('value')
			    ->from('#__settings')
			    ->where('variable = ' . $db->quote('registration_method'));

		    $db->setQuery($query);
		    $new_registration = $db->loadResult();
		    if ($new_registration != 3) {
			    $result = true;
		    }
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
	    return $result;
    }

    /**
     * do plugin support multi usergroups
     *
     * @return bool
     */
    function isMultiGroup()
	{
		return false;
	}

    /**
     * do plugin support multi usergroups
     *
     * @return string UNKNOWN or JNO or JYES or ??
     */
    function requireFileAccess()
	{
		return 'JNO';
	}

	/**
	 * create the render group function
	 *
	 * @return string
	 */
	function getRenderGroup()
	{
		$jname = $this->getJname();

		Application::getInstance()->loadScriptLanguage(array('MAIN_USERGROUP', 'MEMBERGROUPS', 'POSTGROUP'));

		$postgroups = json_encode($this->getUserpostgroupList());

		$js = <<<JS
		if (typeof JFusion.postgroups === 'undefined') {
		    JFusion.postgroups = {};
		}
		JFusion.postgroups['{$jname}'] = {$postgroups};

		JFusion.renderPlugin['{$jname}'] = function(index, plugin, pair, usergroups) {
			return (function( $ ) {
			    var postgroups = JFusion.postgroups[plugin.name];
				var defaultgroup = $(pair).prop('defaultgroup');
				var groups = $(pair).prop('groups');

				var root = $('<div></div>');
				// render default group
				root.append($('<div>' + JFusion.Text._('MAIN_USERGROUP') + '</div>'));

				var defaultselect = $('<select></select>');
				defaultselect.attr('name', 'usergroups['+plugin.name+']['+index+'][defaultgroup]');
				defaultselect.attr('id', 'usergroups_'+plugin.name+index+'defaultgroup');

	            $.each(usergroups, function( key, group ) {
	                var options = $('<option></option>');
					options.val(group.id);
	                options.html(group.name);

			        if (pair && defaultgroup && defaultgroup == group.id) {
						options.attr('selected','selected');
			        }

					defaultselect.append(options);
	            });

			    root.append(defaultselect);


			    // render default post groups
			    root.append($('<div>' + JFusion.Text._('POSTGROUP') + '</div>'));

				var postgroupsselect = $('<select></select>');
				postgroupsselect.attr('name', 'usergroups['+plugin.name+']['+index+'][defaultgroup]');
				postgroupsselect.attr('id', 'usergroups_'+plugin.name+index+'defaultgroup');

	            $.each(postgroups, function( key, group ) {
	                var options = $('<option></option>');
					options.val(group.id);
	                options.html(group.name);

			        if (pair && defaultgroup && defaultgroup == group.id) {
						options.attr('selected','selected');
			        }

					postgroupsselect.append(options);
	            });

	            root.append(postgroupsselect);


				// render default member groups
				root.append($('<div>' + JFusion.Text._('MEMBERGROUPS') + '</div>'));

				var membergroupsselect = $('<select></select>');
				membergroupsselect.attr('name', 'usergroups['+plugin.name+']['+index+'][groups][]');
				membergroupsselect.attr('id', 'usergroups_'+plugin.name+index+'groups');
				membergroupsselect.attr('multiple', 'multiple');

	            $.each(usergroups, function( i, group ) {
	                if (group.id !== 0) {
		                var options = $('<option></option>');
						options.val(group.id);
		                options.html(group.name);

			            if (pair && groups && $.inArray(group.id, groups) >= 0) {
			                options.attr('selected', 'selected');
			            }

						membergroupsselect.append(options);
	                }
	            });

			    root.append(membergroupsselect);
			    return root;
			})(jQuery);
		};
JS;
		return $js;
	}
}

