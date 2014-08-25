<?php namespace JFusion\Plugins\smf2\Platform\Joomla;
/**
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage smf2
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;
use JFusion\Plugin\Platform\Joomla;

use Joomla\Filesystem\File;
use Joomla\Language\Text;

use Joomla\Registry\Registry;
use Joomla\Uri\Uri;

use Psr\Log\LogLevel;

use JUri;
use JEventDispatcher;
use JFactory;

use RuntimeException;
use Exception;
use stdClass;

/**
 * JFusion Platform Class for SMF 1.1.x
 * For detailed descriptions on these functions please check Joomla
 *
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage smf2
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Platform extends Joomla
{
	/**
	 * @var $callbackdata object
	 */
	var $callbackdata = null;
	/**
	 * @var bool $callbackbypass
	 */
	var $callbackbypass = null;

    /**
     * @param int $threadid
     * @return string
     */
    function getThreadURL($threadid)
    {
        return  'index.php?topic=' . $threadid;
    }

    /**
     * @param int $threadid
     * @param int $postid
     * @return string
     */
    function getPostURL($threadid, $postid)
    {
        return  'index.php?topic=' . $threadid . '.msg' . $postid . '#msg' . $postid;
    }

    /**
     * @param int $forumid
     * @param int $threadid
     * @return string
     */
    function getReplyURL($forumid, $threadid)
    {
        return 'index.php?action=post;topic=' . $threadid;
    }

    /**
     * @param int|string $userid
     *
     * @return string
     */
    function getProfileURL($userid)
    {
        return  'index.php?action=profile&u=' . $userid;
    }

    /**
     * @return string
     */
    function getPrivateMessageURL()
    {
        return 'index.php?action=pm';
    }

    /**
     * @return string
     */
    function getViewNewMessagesURL()
    {
        return 'index.php?action=unread';
    }

    /**
     * @return array
     */
    function getForumList()
    {
	    try {
		    // initialise some objects
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('id_board as id, name')
			    ->from('#__boards');

		    $db->setQuery($query);

		    //getting the results
		    return $db->loadObjectList('id');
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    return array();
	    }
    }

    /**
     * @param object $post
     * @return int
     */
    function checkReadStatus(&$post)
    {
		$JUser = JFactory::getUser();
	    $newstatus = 0;
	    try {
	        if (!$JUser->guest) {
	            static $markread;
	            if (!is_array($markread)) {
	                $markread = array();
	                $db = Factory::getDatabase($this->getJname());

		            $userlookup = new Userinfo('joomla_int');
		            $userlookup->userid = $JUser->get('id');

		            $PluginUser = Factory::getUser($this->getJname());
		            $userlookup = $PluginUser->lookupUser($userlookup);
	                if ($userlookup) {
		                $query = $db->getQuery(true)
			                ->select('id_msg, id_topic')
			                ->from('#__log_topics')
			                ->where('id_member = ' . (int)$userlookup->userid);

	                    $db->setQuery($query);
	                    $markread['topic'] = $db->loadObjectList('id_topic');

		                $query = $db->getQuery(true)
			                ->select('id_msg, id_board')
			                ->from('#__log_mark_read')
			                ->where('id_member = ' . (int)$userlookup->userid);

	                    $db->setQuery($query);
	                    $markread['mark_read'] = $db->loadObjectList('id_board');

		                $query = $db->getQuery(true)
			                ->select('id_msg, id_board')
			                ->from('#__log_boards')
			                ->where('id_member = ' . (int)$userlookup->userid);

	                    $db->setQuery($query);
	                    $markread['board'] = $db->loadObjectList('id_board');
	                }
	            }

	            if (isset($markread['topic'][$post->threadid])) {
	                $latest_read_msgid = $markread['topic'][$post->threadid]->id_msg;
	            } elseif (isset($markread['mark_read'][$post->forumid])) {
	                $latest_read_msgid = $markread['mark_read'][$post->forumid]->id_msg;
	            } elseif (isset($markread['board'][$post->forumid])) {
	                $latest_read_msgid = $markread['board'][$post->forumid]->id_msg;
	            } else {
	                $latest_read_msgid = false;
	            }

	            $newstatus = ($latest_read_msgid !== false && $post->postid > $latest_read_msgid) ? 1 : 0;
	        }
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
        return $newstatus;
    }

    /**
     * @param int $userid
     * @return array
     */
    function getPrivateMessageCounts($userid)
    {
        try {
	        if ($userid) {
		        // initialise some objects
		        $db = Factory::getDatabase($this->getJname());

		        $query = $db->getQuery(true)
			        ->select('unread_messages')
			        ->from('#__members')
			        ->where('id_member = ' . (int)$userid);

		        // read unread count
		        $db->setQuery($query);
		        $unreadCount = $db->loadResult();

		        // read total pm count
		        $query = $db->getQuery(true)
			        ->select('instant_messages')
			        ->from('#__members')
			        ->where('id_member = ' . (int)$userid);

		        $db->setQuery($query);
		        $totalCount = $db->loadResult();

		        return array('unread' => $unreadCount, 'total' => $totalCount);
	        }
        } catch (Exception $e) {
	        Framework::raise(LogLevel::ERROR, $e, $this->getJname());
        }
        return array('unread' => 0, 'total' => 0);
    }

    /**
     * @param int $userid
     * @return bool|string
     */
    function getAvatar($userid)
    {
	    $url = false;
	    try {
		    if ($userid) {
			    // Get SMF Params and get an instance of the database
			    $db = Factory::getDatabase($this->getJname());
			    // Load member params from database "mainly to get the avatar"

			    $query = $db->getQuery(true)
				    ->select('*')
				    ->from('#__members')
			        ->where('id_member = ' . (int)$userid);

			    $db->setQuery($query);
			    $db->execute();
			    $result = $db->loadObject();

			    if (!empty($result)) {
				    $url = '';
				    // SMF has a wired way of holding attachments. Get instance of the attachments table
				    $query = $db->getQuery(true)
					    ->select('*')
					    ->from('#__attachments')
					    ->where('id_member = ' . (int)$userid);

				    $db->setQuery($query);
				    $db->execute();
				    $attachment = $db->loadObject();
				    // See if the user has a specific attachment meant for an avatar
				    if(!empty($attachment) && $attachment->id_thumb == 0 && $attachment->id_msg == 0 && empty($result->avatar)) {
					    $url = $this->params->get('source_url') . 'index.php?action=dlattach;attach=' . $attachment->id_attach . ';type=avatar';
					    // If user didn't, check to see if the avatar specified in the first query is a url. If so use it.
				    } else if(preg_match('/http(s?):\/\//', $result->avatar)) {
					    $url = $result->avatar;
				    } else if($result->avatar) {
					    // If the avatar specified in the first query is not a url but is a file name. Make it one
					    $query = $db->getQuery(true)
						    ->select('*')
						    ->from('#__settings')
						    ->where('variable = ' . $db->quote('avatar_url'));

					    $db->setQuery($query);
					    $avatarurl = $db->loadObject();
					    // Check for trailing slash. If there is one DON'T ADD ONE!
					    if(substr($avatarurl->value, -1) == DIRECTORY_SEPARATOR) {
						    $url = $avatarurl->value . $result->avatar;
						    // I like redundancy. Recheck to see if there isn't a trailing slash. If there isn't one, add one.
					    } else if(substr($avatarurl->value, -1) !== DIRECTORY_SEPARATOR) {
						    $url = $avatarurl->value . '/' . $result->avatar;
					    }
				    }
				    return $url;
			    }
		    }
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $url = false;
	    }
        return $url;
	}

     /**
      * Creates new thread and posts first post
      *
      * @param Registry &$dbparams with discussion bot parameters
      * @param object &$contentitem object containing content information
      * @param int $forumid Id of forum to create thread
      *
      * @return stdClass
     */
	function createThread(&$dbparams, &$contentitem, $forumid)
	{
		//setup some variables
		$userid = $this->getThreadAuthor($dbparams, $contentitem);
		$db = Factory::getDatabase($this->getJname());
		$subject = trim(strip_tags($contentitem->title));

		//prepare the content body
		$text = $this->prepareFirstPostBody($dbparams, $contentitem);

		//the user information
		$query = $db->getQuery(true)
			->select('member_name, email_address')
			->from('#__members')
			->where('id_member = ' . (int)$userid);

		$db->setQuery($query);
		$smfUser = $db->loadObject();

		if ($dbparams->get('use_content_created_date', false)) {
			$timezone = Factory::getConfig()->get('offset');
			$timestamp = strtotime($contentitem->created);
			//undo Joomla timezone offset
			$timestamp += ($timezone * 3600);
		} else {
			$timestamp = time();
		}

		$topic_row = new stdClass();

		$topic_row->is_sticky = 0;
		$topic_row->id_board = $forumid;
		$topic_row->id_first_msg = $topic_row->id_last_msg = 0;
		$topic_row->id_member_started = $topic_row->id_member_updated =  $userid;
		$topic_row->id_poll = 0;
		$topic_row->num_replies = 0;
		$topic_row->num_views = 0;
		$topic_row->locked = 0;

		$db->insertObject('#__topics', $topic_row, 'id_topic' );
		$topicid = $db->insertid();

		$post_row = new stdClass();
		$post_row->id_board			= $forumid;
		$post_row->id_topic 		= $topicid;
		$post_row->poster_time		= $timestamp;
		$post_row->id_member		= $userid;
		$post_row->subject			= $subject;
		$post_row->poster_name		= $smfUser->member_name;
		$post_row->poster_email		= $smfUser->email_address;
		$post_row->poster_ip			= $_SERVER['REMOTE_ADDR'];
		$post_row->smileys_enabled	= 1;
		$post_row->modified_time		= 0;
		$post_row->modified_name		= '';
		$post_row->body				= $text;
		$post_row->icon				= 'xx';

		$db->insertObject('#__messages', $post_row, 'id_msg');

		$postid = $db->insertid();

		$post_row = new stdClass();
		$post_row->id_msg = $postid;
		$post_row->id_msg_modified = $postid;

		$db->updateObject('#__messages', $post_row, 'id_msg' );

		$topic_row = new stdClass();

		$topic_row->id_first_msg	= $postid;
		$topic_row->id_last_msg		= $postid;
		$topic_row->id_topic 		= $topicid;

		$db->updateObject('#__topics', $topic_row, 'id_topic' );

		$forum_stats = new stdClass();
		$forum_stats->id_board =  $forumid;

		$query = $db->getQuery(true)
			->select('m.poster_time')
			->from('#__messages AS m')
			->innerJoin('#__boards AS b ON b.id_last_msg = m.id_msg')
			->where('b.id_board = ' . (int)$forumid);

		$db->setQuery($query);
		$lastPostTime = (int) $db->loadResult();
		if($dbparams->get('use_content_created_date', false)) {
			//only update the last post for the board if it really is newer
			$updateLastPost = ($timestamp > $lastPostTime) ? true : false;
		} else {
			$updateLastPost = true;
		}

		if($updateLastPost) {
			$forum_stats->id_last_msg =  $postid;
			$forum_stats->id_msg_updated =  $postid;
		}

		$query = $db->getQuery(true)
			->select('num_topics, num_posts')
			->from('#__boards')
			->where('id_board = ' . (int)$forumid);

		$db->setQuery($query);
		$num = $db->loadObject();
		$forum_stats->num_posts =  $num->num_posts +1;
		$forum_stats->num_topics =  $num->num_topics +1;

		$db->updateObject('#__boards', $forum_stats, 'id_board' );

		if ($updateLastPost) {
			$query = 'REPLACE INTO #__log_topics SET id_member = ' . $userid . ', id_topic = ' . $topicid . ', id_msg = ' . ($postid + 1);
			$db->setQuery($query);
			$db->execute();

			$query = 'REPLACE INTO #__log_boards SET id_member = ' . $userid . ', id_board = ' . $forumid . ', id_msg = ' . $postid;
			$db->setQuery($query);
			$db->execute();
		}
		$threadinfo = new stdClass();
		if(!empty($topicid) && !empty($postid)) {
			//add information to update forum lookup
			$threadinfo->forumid = $forumid;
			$threadinfo->threadid = $topicid;
			$threadinfo->postid = $postid;
		}
		return $threadinfo;
	}

	 /**
	  * Updates information in a specific thread/post
	  * @param Registry &$dbparams with discussion bot parameters
	  * @param object &$existingthread with existing thread info
	  * @param object &$contentitem object containing content information
	  *
	  * @return void
     **/
	function updateThread(&$dbparams, &$existingthread, &$contentitem)
	{
		$postid = $existingthread->postid;

		//setup some variables
		$db = Factory::getDatabase($this->getJname());
		$subject = trim(strip_tags($contentitem->title));

		//prepare the content body
		$text = $this->prepareFirstPostBody($dbparams, $contentitem);

		$timestamp = time();
		$userid = $dbparams->get('default_user');

		$query = $db->getQuery(true)
			->select('member_name')
			->from('#__members')
			->where('id_member = ' . (int)$userid);

		$db->setQuery($query);
		$smfUser = $db->loadObject();

		$post_row = new stdClass();
		$post_row->subject			= $subject;
		$post_row->body				= $text;
		$post_row->modified_time 	= $timestamp;
		$post_row->modified_name 	= $smfUser->member_name;
		$post_row->id_msg_modified	= $postid;
		$post_row->id_msg 			= $postid;
		$db->updateObject('#__messages', $post_row, 'id_msg');
	}

	/**
	 * Returns HTML of a quick reply
	 * @param Registry $dbparams object with discussion bot parameters
	 * @param boolean $showGuestInputs toggles whether to show guest inputs or not
	 * @return string of html
	 */
	function createQuickReply(&$dbparams, $showGuestInputs)
	{
        $html = '';
		$mainframe = Application::getInstance();
        if ($showGuestInputs) {
            $username = $mainframe->input->post->get('guest_username', '');
            $email = $mainframe->input->post->get('guest_email', '');

            $j_username = Text::_('USERNAME');
            $j_email = Text::_('EMAIL');
            $html = <<<HTML
                <table>
                    <tr>
                        <td>
                            {$j_username}:
                        </td>
                        <td>
                            <input name='guest_username' value='{$username}' class='inputbox'/>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            {$j_email}
                        </td>
                        <td>
                            <input name='guest_email' value='{$email}' class='inputbox'/>
                        </td>
                    </tr>
                    {$this->createCaptcha($dbparams)}
                </table>
                <br />
HTML;
        }
        $quickReply = $mainframe->input->post->get('quickReply', '');
        $html .= '<textarea name="quickReply" class="inputbox quickReply" rows="15" cols="100">' . $quickReply . '</textarea><br />';
        return $html;
	}

	/**
	 * Creates a post from the quick reply
	 *
	 * @param Registry $params      object with discussion bot parameters
	 * @param stdClass  $ids         stdClass with forum id ($ids->forumid, thread id ($ids->threadid) and first post id ($ids->postid)
	 * @param object    $contentitem object of content item
	 * @param Userinfo  $userinfo    object info of the forum user
	 * @param stdClass  $postinfo    object with post info
	 *
	 * @throws \RuntimeException
	 * @return stdClass
	 */
	function createPost($params, $ids, $contentitem, Userinfo $userinfo, $postinfo)
	{
		$post = new stdClass();
		$post->postid = 0;
		$post->moderated = 0;

		if($userinfo->guest) {
			$userinfo->username = $postinfo->username;
			$userinfo->email = $postinfo->email;
			$userinfo->userid = null;
			if (empty($userinfo->username) || empty($userinfo->email) || !preg_match('/^[^@]+@[a-zA-Z0-9._-]+\.[a-zA-Z]+$/', $userinfo->email)) {
				throw new RuntimeException(Text::_('GUEST_FIELDS_MISSING'));
			} else {
				//check to see if user exists to prevent user hijacking
				$JFusionUser = Factory::getUser($this->getJname());
				$existinguser = $JFusionUser->getUser($userinfo);
				if(!empty($existinguser)) {
					throw new RuntimeException(Text::_('USERNAME_IN_USE'));
				}

				//check for email
				$existinguser = $JFusionUser->getUser($userinfo->email);
				if(!empty($existinguser)) {
					throw new RuntimeException(Text::_('EMAIL_IN_USE'));
				}
			}
		}

		//setup some variables
		$userid = $userinfo->userid;
		$db = Factory::getDatabase($this->getJname());
		$front = Factory::getFront($this->getJname());
		//strip out html from post
		$text = strip_tags($postinfo->text);

		if(!empty($text)) {
			$this->prepareText($text, 'forum', new Registry());

			//get some topic information
			$query = $db->getQuery(true)
				->select('t.id_first_msg , t.num_replies, m.subject')
				->from('#__messages')
				->innerJoin('#__topics as t ON t.id_topic = m.id_topic')
				->where('id_topic = ' . (int)$ids->threadid)
				->where('m.id_msg = t.id_first_msg');

			$db->setQuery($query);
			$topic = $db->loadObject();

			//the user information
			if($userinfo->guest) {
				$smfUser = new stdClass();
				$smfUser->member_name = $userinfo->username;
				$smfUser->email_address = $userinfo->email;
			} else {
				$query = $db->getQuery(true)
					->select('member_name, email_address')
					->from('#__members')
					->where('id_member = ' . (int)$userid);

				$db->setQuery($query);
				$smfUser = $db->loadObject();
			}

			$timestamp = time();

			$post_approved = ($userinfo->guest && $params->get('moderate_guests', 1)) ? 0 : 1;

			$post_row = new stdClass();
			$post_row->id_board			= $ids->forumid;
			$post_row->id_topic 		= $ids->threadid;
			$post_row->poster_time		= $timestamp;
			$post_row->id_member		= $userid;
			$post_row->subject			= 'Re: ' . $topic->subject;
			$post_row->poster_name		= $smfUser->member_name;
			$post_row->poster_email		= $smfUser->email_address;
			$post_row->poster_ip		= $_SERVER["REMOTE_ADDR"];
			$post_row->smileys_enabled	= 1;
			$post_row->modified_time	= 0;
			$post_row->modified_name	= '';
			$post_row->body				= $text;
			$post_row->icon				= 'xx';
			$post_row->approved 		= $post_approved;

			$db->insertObject('#__messages', $post_row, 'id_msg');

			$postid = $db->insertid();

			$post_row = new stdClass();
			$post_row->id_msg = $postid;
			$post_row->id_msg_modified = $postid;
			$db->updateObject('#__messages', $post_row, 'id_msg' );

			//store the postid
			$post->postid = $postid;

			//only update the counters if the post is approved
			if($post_approved) {
				$topic_row = new stdClass();
				$topic_row->id_last_msg			= $postid;
				$topic_row->id_member_updated	= (int) $userid;
				$topic_row->num_replies			= $topic->num_replies + 1;
				$topic_row->id_topic			= $ids->threadid;
				$db->updateObject('#__topics', $topic_row, 'id_topic' );

				$forum_stats = new stdClass();
				$forum_stats->id_last_msg 		=  $postid;
				$forum_stats->id_msg_updated	=  $postid;

				$query = $db->getQuery(true)
					->select('num_posts')
					->from('#__boards')
					->where('id_member = ' . (int)$ids->forumid);

				$db->setQuery($query);
				$num = $db->loadObject();
				$forum_stats->num_posts = $num->num_posts + 1;
				$forum_stats->id_board 			= $ids->forumid;
				$db->updateObject('#__boards', $forum_stats, 'id_board' );

				//update stats for threadmarking purposes
				$query = 'REPLACE INTO #__log_topics SET id_member = ' . $userid . ', id_topic = ' . $ids->threadid . ', id_msg = ' . ($postid + 1);
				$db->setQuery($query);
				$db->execute();

				$query = 'REPLACE INTO #__log_boards SET id_member = ' . $userid . ', id_board = ' . $ids->forumid . ', id_msg = ' . $postid;
				$db->setQuery($query);
				$db->execute();
			} else {
				//add the post to the approval queue
				$approval_queue = new stdClass;
				$approval_queue->id_msg = $postid;

				$db->insertObject('#__approval_queue', $approval_queue);
			}

			//update moderation status to tell discussion bot to notify user
			$post->moderated = ($post_approved) ? 0 : 1;
		}

		return $post;
	}

	/**
	 * Retrieves the posts to be displayed in the content item if enabled
	 *
	 * @param Registry $dbparams with discussion bot parameters
	 * @param object $existingthread object with forumid, threadid, and postid (first post in thread)
	 * @param int $start
	 * @param int $limit
	 * @param string $sort
	 *
	 * @return array or object Returns retrieved posts
	 */
	function getPosts($dbparams, $existingthread, $start, $limit, $sort)
	{
		try {
			//set the query
			$where = 'WHERE id_topic = ' . $existingthread->threadid . ' AND id_msg != ' . $existingthread->postid . ' AND approved = 1';
	        $query = '(SELECT a.id_topic , a.id_msg, a.poster_name, b.real_name, a.id_member, 0 AS guest, a.subject, a.poster_time, a.body, a.poster_time AS order_by_date FROM `#__messages` as a INNER JOIN #__members as b ON a.id_member = b.id_member ' . $where . ' AND a.id_member != 0)';
	        $query.= ' UNION ';
	        $query.= '(SELECT a.id_topic , a.id_msg, a.poster_name, a.poster_name as real_name, a.id_member, 1 AS guest, a.subject, a.poster_time, a.body, a.poster_time AS order_by_date FROM `#__messages` as a ' . $where . ' AND a.id_member = 0)';
	        $query.= ' ORDER BY order_by_date ' . $sort;
			$db = Factory::getDatabase($this->getJname());

			$db->setQuery($query, $start, $limit);

			$posts = $db->loadObjectList();
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
			$posts = array();
		}
		return $posts;
	}

    /**
     * @param object $existingthread
     * @return int
     */
    function getReplyCount($existingthread)
	{
		try {
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('num_posts')
				->from('#__topics')
				->where('id_topic = ' . (int)$existingthread->threadid);

			$db->setQuery($query);
			$result = $db->loadResult();
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
			$result = 0;
		}
		return $result;
	}

	/**
	 * Returns an object of columns used in createPostTable()
	 * Saves from having to repeat the same code over and over for each plugin
	 * For example:
	 * $columns->userid = 'userid';
	 * $columns->username = 'username';
	 * $columns->username_clean = 'username_clean'; //if applicable for filtered usernames
	 * $columns->dateline = 'dateline';
	 * $columns->posttext = 'pagetext';
	 * $columns->posttitle = 'title';
	 * $columns->postid = 'postid';
	 * $columns->threadid = 'threadid';
	 * @return object with column names
	 */
	function getDiscussionColumns()
	{
		$columns = new stdClass();
		$columns->userid = 'id_member';
		$columns->username = 'poster_name';
		$columns->name = 'real_name';
		$columns->dateline = 'poster_time';
		$columns->posttext = 'body';
		$columns->posttitle = 'subject';
		$columns->postid = 'id_msg';
		$columns->threadid = 'id_topic';
		$columns->guest = 'guest';
		return $columns;
	}

    /**
     * @param int $threadid
     * @return object
     */
    function getThread($threadid)
    {
	    try {
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('id_topic AS threadid, id_board AS forumid, id_first_msg AS postid')
			    ->from('#__topics')
			    ->where('id_topic = ' . (int)$threadid);

		    $db->setQuery($query);
		    $results = $db->loadObject();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $results = null;
	    }
		return $results;
    }

    /**
     * @param int $threadid
     * @return bool
     */
    function getThreadLockedStatus($threadid) {
	    try {
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('locked')
			    ->from('#__topics')
			    ->where('id_topic = ' . (int)$threadid);

		    $db->setQuery($query);
		    $locked = $db->loadResult();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $locked = true;
	    }
	    return $locked;
    }

    /**
     * @param array $usedforums
     * @param string $result_order

     * @return array|string
     */
    function getActivityQuery($usedforums, $result_order)
    {
        $db = Factory::getDatabase($this->getJname());

		$userPlugin = Factory::getUser($this->getJname());

		$user = JFactory::getUser();
		$userid = $user->get('id');

		if ($userid) {
			$userlookup = new Userinfo('joomla_int');
			$userlookup->userid = $userid;

			$userlookup = $userPlugin->lookupUser($userlookup);
			$existinguser = $userPlugin->getUser($userlookup);
			$group_id = $existinguser->group_id;
		} else {
			$group_id = '-1';
		}

	    $query = $db->getQuery(true)
		    ->select('member_groups, id_board')
		    ->from('#__boards');

		$db->setQuery($query);
        $boards = $db->loadObjectList();

		$list = array();
		foreach($boards as $value) {
			$member_groups = explode(',', $value->member_groups);
			if ( in_array($group_id, $member_groups) || $group_id == 1) {
				$list[] =  $value->id_board;
			}
		}

        $where = (!empty($usedforums)) ? ' WHERE b.id_board IN (' . $usedforums . ') AND a.id_board IN (' . implode(',', $list) . ')' : ' WHERE a.id_board IN (' . implode(',', $list) . ' )';

        $numargs = func_num_args();
        if ($numargs > 3) {
            $filters = func_get_args();
            for ($i = 3; $i < $numargs; $i++) {
                if ($filters[$i][0] == 'userid') {
                    $where .= ' HAVING userid = ' . $db->quote($filters[$i][1]);
                }
            }
        }

        //setup the guest where clause to be used in union query
        $guest_where = (empty($where)) ? ' WHERE b.id_member = 0' : ' AND b.id_member = 0';

        $query = array(
        //LAT with first post info
	    self::LAT . '0' =>
        "(SELECT a.id_topic AS threadid, a.id_last_msg AS postid, b.poster_name AS username, d.real_name AS name, b.id_member AS userid, b.subject AS subject, b.poster_time AS dateline, a.id_board as forumid, c.poster_time as last_post_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.id_first_msg = b.id_msg
                INNER JOIN `#__messages` as c ON a.id_last_msg = c.id_msg
                INNER JOIN `#__members`  as d ON b.id_member = d.id_member
                $where)
        UNION
            (SELECT a.id_topic AS threadid, a.id_last_msg AS postid, b.poster_name AS username, b.poster_name AS name, b.id_member AS userid, b.subject AS subject, b.poster_time AS dateline, a.id_board as forumid, c.poster_time as last_post_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.id_first_msg = b.id_msg
                INNER JOIN `#__messages` as c ON a.id_last_msg = c.id_msg
                $where $guest_where)
        ORDER BY last_post_date $result_order",
        //LAT with latest post info
	    self::LAT . '1' =>
        "(SELECT a.id_topic AS threadid, a.id_last_msg AS postid, b.poster_name AS username, d.real_name as name, b.id_member AS userid, c.subject AS subject, b.poster_time AS dateline, a.id_board as forumid, b.poster_time as last_post_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.id_last_msg = b.id_msg
                INNER JOIN `#__messages` as c ON a.id_first_msg = c.id_msg
                INNER JOIN `#__members`  as d ON b.id_member = d.id_member
                $where)
        UNION
            (SELECT a.id_topic AS threadid, a.id_last_msg AS postid, b.poster_name AS username, b.poster_name as name, b.id_member AS userid, c.subject AS subject, b.poster_time AS dateline, a.id_board as forumid, b.poster_time as last_post_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.id_last_msg = b.id_msg
                INNER JOIN `#__messages` as c ON a.id_first_msg = c.id_msg
                $where $guest_where)
        ORDER BY last_post_date $result_order",
        //LCT
	    self::LCT =>
        "(SELECT a.id_topic AS threadid, b.id_msg AS postid, b.poster_name AS username, d.real_name as name, b.id_member AS userid, b.subject AS subject, b.body, b.poster_time AS dateline, a.id_board as forumid, b.poster_time as topic_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.id_first_msg = b.id_msg
                INNER JOIN `#__messages` as c ON a.id_last_msg = c.id_msg
                INNER JOIN `#__members`  as d ON b.id_member = d.id_member
                $where)
        UNION
            (SELECT a.id_topic AS threadid, b.id_msg AS postid, b.poster_name AS username, b.poster_name as name, b.id_member AS userid, b.subject AS subject, b.body, b.poster_time AS dateline, a.id_board as forumid, b.poster_time as topic_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.id_first_msg = b.id_msg
                INNER JOIN `#__messages` as c ON a.id_last_msg = c.id_msg
                $where $guest_where)
        ORDER BY topic_date $result_order",
        //LCP
	    self::LCP => "
        (SELECT b.id_topic AS threadid, b.id_msg AS postid, b.poster_name AS username, d.real_name as name, b.id_member AS userid, b.subject AS subject, b.body, b.poster_time AS dateline, b.id_board as forumid, b.poster_time as last_post_date
            FROM `#__messages` as b
                INNER JOIN `#__members` as d ON b.id_member = d.id_member
                INNER JOIN `#__topics` as a ON b.id_topic = a.id_topic
                $where)
        UNION
            (SELECT b.id_topic AS threadid, b.id_msg AS postid, b.poster_name AS username, b.poster_name as name, b.id_member AS userid, b.subject AS subject, b.body, b.poster_time AS dateline, b.id_board as forumid, b.poster_time as last_post_date
            FROM `#__messages` as b
            	INNER JOIN `#__topics` as a ON b.id_topic = a.id_topic
                $where $guest_where)
        ORDER BY last_post_date $result_order");

        return $query;
    }

	/**
	 * Filter forums from a set of results sent in / useful if the plugin needs to restrict the forums visible to a user
	 * @param array $results set of results from query
	 * @param int $limit limit results parameter as set in the module's params; used for plugins that cannot limit using a query limiter
	 *
	 * @return void
	 */
	function filterActivityResults(&$results, $limit=0)
	{
		try {
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('value')
				->from('#__settings')
				->where('variable = ' . $db->quote('censor_vulgar'));

			$db->setQuery($query);
			$vulgar = $db->loadResult();

			$query = $db->getQuery(true)
				->select('value')
				->from('#__settings')
				->where('variable = ' . $db->quote('censor_proper'));

			$db->setQuery($query);
			$proper = $db->loadResult();

			$vulgar = explode(',', $vulgar);
			$proper = explode(',', $proper);

			foreach($results as $rkey => $result) {
				foreach($vulgar as $key => $value) {
					$results[$rkey]->subject = preg_replace('#\b' . preg_quote($value,'#') . '\b#is', $proper[$key], $result->subject);
					if (isset($results[$rkey]->body)) {
						$results[$rkey]->body = preg_replace('#\b' . preg_quote($value,'#') . '\b#is', $proper[$key], $result->body);
					}
				}
			}
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		}
	}

	/************************************************
	 * Functions For JFusion Who's Online Module
	 ***********************************************/

	/**
	 * Returns a query to find online users
	 * Make sure columns are named as userid, username, username_clean (if applicable), name (of user), and email
	 *
	 * @param array $usergroups
	 *
	 * @return string
	 **/
	function getOnlineUserQuery($usergroups = array())
	{
		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->select('DISTINCT u.id_member AS userid, u.member_name AS username, u.real_name AS name, u.email_address as email')
			->from('#__members AS u')
			->innerJoin('#__log_online AS s ON u.id_member = s.id_member')
			->where('s.id_member != 0');

		if(!empty($usergroups)) {
			if(is_array($usergroups)) {
				$usergroups_string = implode(',', $usergroups);
				$usergroup_query = '(u.id_group IN (' . $usergroups_string . ') OR u.id_post_group IN (' . $usergroups_string . ')';
				foreach($usergroups AS $usergroup) {
					$usergroup_query .= ' OR FIND_IN_SET(' . intval($usergroup) . ', u.additional_groups)';
				}
				$usergroup_query .= ')';
			} else {
				$usergroup_query = '(u.id_group = ' . $usergroups . ' OR u.id_post_group = ' . $usergroups . ' OR FIND_IN_SET(' . $usergroups . ', u.additional_groups))';
			}
			$query->where($usergroup_query);
		}

		$query = (string)$query;
		return $query;
	}

	/**
	 * Returns number of guests
	 * @return int
	 */
	function getNumberOnlineGuests()
	{
		try {
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('COUNT(DISTINCT(ip))')
				->from('#__log_online')
				->where('id_member = 0');

			$db->setQuery($query);
			return $db->loadResult();
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
			return 0;
		}
	}

	/**
	 * Returns number of logged in users
	 *
	 * @return int
	 */
	function getNumberOnlineMembers()
	{
		try {
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('COUNT(DISTINCT(l.ip))')
				->from('#__log_online AS l')
				->join('', '#__members AS u ON l.id_member = u.id_member')
				->where('l.id_member != 0');

			$db->setQuery($query);
			return $db->loadResult();
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
			return 0 ;
		}
	}

	/**
	 * Prepares text for various areas
	 *
	 * @param string  &$text             Text to be modified
	 * @param string  $for              (optional) Determines how the text should be prepared.
	 *                                  Options for $for as passed in by JFusion's plugins and modules are:
	 *                                  joomla (to be displayed in an article; used by discussion bot)
	 *                                  forum (to be published in a thread or post; used by discussion bot)
	 *                                  activity (displayed in activity module; used by the activity module)
	 *                                  search (displayed as search results; used by search plugin)
	 * @param Registry $params           (optional) Joomla parameter object passed in by JFusion's module/plugin
	 *
	 * @return array  $status           Information passed back to calling script such as limit_applied
	 */
	function prepareText(&$text, $for = 'forum', $params = null)
	{
		$status = array();
		if ($for == 'forum') {
			static $bbcode;
			//first thing is to remove all joomla plugins
			preg_match_all('/\{(.*)\}/U', $text, $matches);
			//find each thread by the id
			foreach ($matches[1] AS $plugin) {
				//replace plugin with nothing
				$text = str_replace('{' . $plugin . '}', "", $text);
			}
			if (!is_array($bbcode)) {
				$bbcode = array();
				//pattens to run in beginning
				$bbcode[0][] = '#<a[^>]*href=[\'|"](ftp://)(.*?)[\'|"][^>]*>(.*?)</a>#si';
				$bbcode[1][] = '[ftp=$1$2]$3[/ftp]';
				//pattens to run in end
				$bbcode[2][] = '#<table[^>]*>(.*?)<\/table>#si';
				$bbcode[3][] = '[table]$1[/table]';
				$bbcode[2][] = '#<tr[^>]*>(.*?)<\/tr>#si';
				$bbcode[3][] = '[tr]$1[/tr]';
				$bbcode[2][] = '#<td[^>]*>(.*?)<\/td>#si';
				$bbcode[3][] = '[td]$1[/td]';
				$bbcode[2][] = '#<strong[^>]*>(.*?)<\/strong>#si';
				$bbcode[3][] = '[b]$1[/b]';
				$bbcode[2][] = '#<(strike|s)>(.*?)<\/\\1>#sim';
				$bbcode[3][] = '[s]$2[/s]';
			}
			$options = array();
			$options['bbcode_patterns'] = $bbcode;
			$text = Framework::parseCode($text, 'bbcode', $options);
		} elseif ($for == 'joomla' || ($for == 'activity' && $params->get('parse_text') == 'html')) {
			$options = array();
			//convert smilies so they show up in Joomla as images
			static $custom_smileys;
			if (!is_array($custom_smileys)) {
				$custom_smileys = array();
				try {
					$db = Factory::getDatabase($this->getJname());

					$query = $db->getQuery(true)
						->select('value, variable')
						->from('#__settings')
						->where('variable = ' . $db->quote('smileys_url'), 'OR')
						->where('variable = ' . $db->quote('smiley_sets_default'));

					$db->setQuery($query);
					$settings = $db->loadObjectList('variable');

					$query = $db->getQuery(true)
						->select('code, filename')
						->from('#__smileys')
						->order('smileyOrder');

					$db->setQuery($query);
					$smilies = $db->loadObjectList();
					if (!empty($smilies)) {
						foreach ($smilies as $s) {
							$custom_smileys[$s->code] = "{$settings['smileys_url']->value}/{$settings['smiley_sets_default']->value}/{$s->filename}";
						}
					}
				} catch (Exception $e) {
					Framework::raise(LogLevel::ERROR, $e, $this->getJname());
				}
			}
			$options['custom_smileys'] = $custom_smileys;
			$options['parse_smileys'] = \JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/smileys';
			//parse bbcode to html
			if (!empty($params) && $params->get('character_limit', false)) {
				$status['limit_applied'] = 1;
				$options['character_limit'] = $params->get('character_limit');
			}

			//add smf bbcode rules
			$options['html_patterns'] = array();
			$options['html_patterns']['li'] = array('simple_start' => '<li>', 'simple_end' => "</li>\n", 'class' => 'listitem', 'allow_in' => array('list'), 'end_tag' => 0, 'before_tag' => 's', 'after_tag' => 's', 'before_endtag' => 'sns', 'after_endtag' => 'sns', 'plain_start' => "\n * ", 'plain_end' => "\n");

			$bbcodes = array('size', 'glow', 'shadow', 'move', 'pre', 'hr', 'flash', 'ftp', 'table', 'tr', 'td', 'tt', 'abbr', 'anchor', 'black', 'blue', 'green', 'iurl', 'html', 'ltr', 'me', 'nobbc', 'php', 'red', 'rtl', 'time', 'white', 'o', 'O', '0', '@', '*', '=', '@', '+', 'x', '#');

			foreach($bbcodes as $bb) {
				if (in_array($bb, array('ftp', 'iurl'))) {
					$class = 'link';
				} elseif (in_array($bb, array('o', 'O', '0', '@', '*', '=', '@', '+', 'x', '#'))) {
					$class = 'listitem';
				} elseif ($bb == 'table') {
					$class = 'table';
				} else {
					$class = 'inline';
				}

				if (in_array($bb, array('o', 'O', '0', '@', '*', '=', '@', '+', 'x', '#'))) {
					$allow_in = array('list');
				} elseif (in_array($bb, array('td', 'tr'))) {
					$allow_in = array('table');
				} else {
					$allow_in = array('listitem', 'block', 'columns', 'inline', 'link');
				}

				$options['html_patterns'][$bb] = array('mode' => 1, 'content' => 0, 'method' => array($this, 'parseCustomBBCode'), 'class' => $class, 'allow_in' => $allow_in);
			}

			$text = Framework::parseCode($text, 'html', $options);
		} elseif ($for == 'search') {
			$text = Framework::parseCode($text, 'plaintext');
		} elseif ($for == 'activity') {
			if ($params->get('parse_text') == 'plaintext') {
				$options = array();
				$options['plaintext_line_breaks'] = 'space';
				if ($params->get('character_limit')) {
					$status['limit_applied'] = 1;
					$options['character_limit'] = $params->get('character_limit');
				}
				$text = Framework::parseCode($text, 'plaintext', $options);
			}
		}
		return $status;
	}

	/**
	 * @param string $url
	 * @param int $itemid
	 *
	 * @return string
	 */
	function generateRedirectCode($url, $itemid)
	{
		//create the new redirection code
		/*
		$pattern = \'#action=(login|admin|profile|featuresettings|news|packages|detailedversion|serversettings|theme|manageboards|postsettings|managecalendar|managesearch|smileys|manageattachments|viewmembers|membergroups|permissions|regcenter|ban|maintain|reports|viewErrorLog|optimizetables|detailedversion|repairboards|boardrecount|convertutf8|helpadmin|packageget)#\';
		 */
		$redirect_code = '
//JFUSION REDIRECT START
//SET SOME VARS
$joomla_url = \'' . $url . '\';
$joomla_itemid = ' . $itemid . ';
	';
		$redirect_code .= '
if(!defined(\'_JEXEC\') && strpos($_SERVER[\'QUERY_STRING\'], \'dlattach\') === false && strpos($_SERVER[\'QUERY_STRING\'], \'verificationcode\') === false)';

		$redirect_code .= '
{
	$pattern = \'#action=(login|logout)#\';
	if (!preg_match($pattern , $_SERVER[\'QUERY_STRING\'])) {
		$file = $_SERVER["SCRIPT_NAME"];
		$break = explode(\'/\', $file);
		$pfile = $break[count($break) - 1];
		$query = str_replace(\';\', \'&\', $_SERVER[\'QUERY_STRING\']);
		$jfusion_url = $joomla_url . \'index.php?option=com_jfusion&Itemid=\' . $joomla_itemid . \'&jfile=\'.$pfile. \'&\' . $query;
		header(\'Location: \' . $jfusion_url);
		exit;
	}
}
//JFUSION REDIRECT END';
		return $redirect_code;
	}

	/**
	 * @param $action
	 *
	 * @return int
	 */
	function redirectMod($action)
	{
		$error = 0;
		$reason = '';
		$mod_file = $this->getPluginFile('index.php', $error, $reason);
		switch($action) {
			case 'reenable':
			case 'disable':
				if ($error == 0) {
					//get the joomla path from the file
					$file_data = file_get_contents($mod_file);
					$search = '/(\r?\n)\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/si';
					preg_match_all($search, $file_data, $matches);
					//remove any old code
					if (!empty($matches[1][0])) {
						$file_data = preg_replace($search, '', $file_data);
						if (!File::write($mod_file, $file_data)) {
							$error = 1;
						}
					}
				}
				if ($action == 'disable') {
					break;
				}
			case 'enable':
				$joomla_url = Factory::getParams('joomla_int')->get('source_url');
				$joomla_itemid = $this->params->get('redirect_itemid');

				//check to see if all vars are set
				if (empty($joomla_url)) {
					Framework::raise(LogLevel::WARNING, Text::_('MISSING') . ' Joomla URL', $this->getJname(), $this->getJname());
				} else if (empty($joomla_itemid) || !is_numeric($joomla_itemid)) {
					Framework::raise(LogLevel::WARNING, Text::_('MISSING') . ' ItemID', $this->getJname(), $this->getJname());
				} else if (!$this->isValidItemID($joomla_itemid)) {
					Framework::raise(LogLevel::WARNING, Text::_('MISSING') . ' ItemID ' . Text::_('MUST BE') . ' ' . $this->getJname(), $this->getJname(), $this->getJname());
				} else if($error == 0) {
					//get the joomla path from the file
					$file_data = file_get_contents($mod_file);
					$redirect_code = $this->generateRedirectCode($joomla_url, $joomla_itemid);

					$search = '/\<\?php/si';
					$replace = '<?php' . $redirect_code;

					$file_data = preg_replace($search, $replace, $file_data);
					File::write($mod_file, $file_data);
				}
				break;
		}
		return $error;
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $node
	 * @param $control_name
	 * @return string
	 */
	function showRedirectMod($name, $value, $node, $control_name)
	{
		$error = 0;
		$reason = '';
		$mod_file = $this->getPluginFile('index.php', $error, $reason);

		if($error == 0) {
			//get the joomla path from the file
			jimport('joomla.filesystem.file');
			$file_data = file_get_contents($mod_file);
			preg_match_all('/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/ms', $file_data, $matches);

			//compare it with our joomla path
			if(empty($matches[1][0])){
				$error = 1;
				$reason = Text::_('MOD_NOT_ENABLED');
			}
		}

		//add the javascript to enable buttons
		if ($error == 0) {
			//return success
			$text = Text::_('REDIRECTION_MOD') . ' ' . Text::_('ENABLED');
			$disable = Text::_('MOD_DISABLE');
			$update = Text::_('MOD_UPDATE');
			$output = <<<HTML
            <img src="components/com_jfusion/images/check_good_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'disable');">{$disable}</a>
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'reenable');">{$update}</a>
HTML;
		} else {
			$text = Text::_('REDIRECTION_MOD') . ' ' . Text::_('DISABLED') . ': ' . $reason;
			$enable = Text::_('MOD_ENABLE');
			$output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'enable');">{$enable}</a>
HTML;
		}
		return $output;
	}

	/**
	 * uninstall function is to disable verious mods
	 *
	 * @return array
	 */
	function uninstall()
	{
		$error = $this->redirectMod('disable');
		if (!empty($error)) {
			$reason = Text::_('REDIRECT_MOD_UNINSTALL_FAILED');
			return array(false, $reason);
		}

		return array(true, '');
	}

	/**
	 * @return object
	 */
	function getSearchQueryColumns()
	{
		$columns = new stdClass();
		$columns->title = 'p.subject';
		$columns->text = 'p.body';
		return $columns;
	}

	/**
	 * @param object $pluginParam
	 *
	 * @return string
	 */
	function getSearchQuery(&$pluginParam)
	{
		$db = Factory::getDatabase($this->getJname());
		//need to return threadid, postid, title, text, created, section

		$query = $db->getQuery(true)
			->select('p.id_topic, p.id_msg, p.id_board, CASE WHEN p.subject = "" THEN CONCAT("Re: ",fp.subject) ELSE p.subject END AS title, p.body AS text,
					FROM_UNIXTIME(p.poster_time, "%Y-%m-%d %h:%i:%s") AS created,
					CONCAT_WS( "/", f.name, fp.subject ) AS section,
					t.num_views as hits')
			->from('#__messages AS p')
			->innerJoin('#__topics AS t ON t.id_topic = p.id_topic')
			->innerJoin('#__messages AS fp ON fp.id_msg = t.id_first_msg')
			->innerJoin('#__boards AS f on f.id_board = p.id_board');

		return (string)$query;
	}

	/**
	 * Add on a plugin specific clause;
	 *
	 * @param string &$where reference to where clause already generated by search bot; add on plugin specific criteria
	 * @param Registry &$pluginParam custom plugin parameters in search.xml
	 * @param string $ordering
	 *
	 * @return void
	 */
	function getSearchCriteria(&$where, &$pluginParam, $ordering)
	{
		try {
			$db = Factory::getDatabase($this->getJname());

			$userPlugin = Factory::getUser($this->getJname());

			$user = JFactory::getUser();
			$userid = $user->get('id');

			if ($userid) {
				$userlookup = new Userinfo('joomla_int');
				$userlookup->userid = $userid;

				$PluginUser = Factory::getUser($this->getJname());
				$userlookup = $userPlugin->lookupUser($userlookup);

				$existinguser = $userPlugin->getUser($userlookup);
				$group_id = $existinguser->group_id;
			} else {
				$group_id = '-1';
			}

			$query = $db->getQuery(true)
				->select('member_groups, id_board')
				->from('#__boards');

			if ($pluginParam->get('forum_mode', 0)) {
				$forumids = $pluginParam->get('selected_forums', array());

				$query->where('id_board IN (' . implode(',', $forumids) . ')');
			}

			$db->setQuery($query);
			$boards = $db->loadObjectList();

			$list = array();
			foreach($boards as $value) {
				$member_groups = explode(',', $value->member_groups);
				if (in_array($group_id, $member_groups) || $group_id == 1) {
					$list[] =  $value->id_board;
				}
			}
			//determine how to sort the results which is required for accurate results when a limit is placed
			switch ($ordering) {
				case 'oldest':
					$sort = 'p.poster_time ASC';
					break;
				case 'category':
					$sort = 'section ASC';
					break;
				case 'popular':
					$sort = 't.num_views DESC, p.poster_time DESC';
					break;
				case 'alpha':
					$sort = 'title ASC';
					break;
				case 'newest':
				default:
					$sort = 'p.poster_time DESC';
					break;
			}
			$where .= ' AND p.id_board IN (' . implode(',', $list) . ') ORDER BY ' . $sort;
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		}
	}

	/**
	 * @param array &$results
	 * @param object &$pluginParam
	 *
	 * @return void
	 */
	function filterSearchResults(&$results = array(), &$pluginParam)
	{
		try {
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('value')
				->from('#__settings')
				->where('variable = ' . $db->quote('censor_vulgar'));

			$db->setQuery($query);
			$vulgar = $db->loadResult();

			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('value')
				->from('#__settings')
				->where('variable = ' . $db->quote('censor_proper'));

			$db->setQuery($query);
			$proper = $db->loadResult();

			$vulgar = explode(',', $vulgar);
			$proper = explode(',', $proper);

			foreach($results as $rkey => $result) {
				foreach($vulgar as $key => $value) {
					$results[$rkey]->title = preg_replace('#\b' . preg_quote($value, '#') . '\b#is', $proper[$key], $result->title);
					$results[$rkey]->text = preg_replace('#\b' . preg_quote($value, '#') . '\b#is', $proper[$key], $result->text);
				}
			}
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		}
	}

	/**
	 * @param mixed $post
	 *
	 * @return string
	 */
	function getSearchResultLink($post)
	{
		/**
		 * @ignore
		 * @var $platform \JFusion\Plugin\Platform\Joomla
		 */
		$platform = Factory::getPlayform('Joomla', $this->getJname());
		return $platform->getPostURL($post->id_topic, $post->id_msg);
	}

	/**
	 * @param object $data
	 *
	 * @return void
	 */
	function getBuffer(&$data)
	{
		$mainframe = Application::getInstance();
		$jFusion_Route = $mainframe->input->get('jFusion_Route', null, 'raw');
		if ($jFusion_Route) {
			$jFusion_Route = unserialize ($jFusion_Route);
			foreach ($jFusion_Route as $value) {
				if (stripos($value, 'action') === 0) {
					list ($k, $v) = explode(',', $value);
					if ($k == 'action') {
						$mainframe->input->set('action', $v);
					}
				}
			}
		}
		$action = $mainframe->input->get('action');
		if ($action == 'register' || $action == 'reminder') {
			$master = Framework::getMaster();
			if ($master->name != $this->getJname()) {
				$JFusionMaster = Factory::getFront($master->name);
				$source_url = $this->params->get('source_url');
				$source_url = rtrim($source_url, '/');
				try {
					if ($action == 'register') {
						header('Location: ' . $source_url . '/' . $JFusionMaster->getRegistrationURL());
					} else {
						header('Location: ' . $source_url . '/' . $JFusionMaster->getLostPasswordURL());
					}
					exit();
				} catch (Exception $e) {}
			}
		}
		//handle dual logout
		if ($action == 'logout') {
			//destroy the SMF session first
			$JFusionUser = Factory::getUser($this->getJname());
			try {
				$JFusionUser->destroySession(null, null);
			} catch (Exception $e) {
				Framework::raise(LogLevel::ERROR, $e, $JFusionUser->getJname());
			}

			//destroy the Joomla session
			$mainframe->logout();
			Session::getInstance()->close();

			$cookies = Factory::getCookies();
			$cookies->addCookie($this->params->get('cookie_name'), '', 0, $this->params->get('cookie_path'), $this->params->get('cookie_domain'), $this->params->get('secure'), $this->params->get('httponly'));
			//redirect so the changes are applied
			$mainframe->redirect(str_replace('&amp;', '&', $data->baseURL));
			exit();
		}
		//handle dual login
		if ($action == 'login2') {
			//uncommented out the code below, as the smf session is needed to validate the password, which can not be done unless SSI.php is required
			//get the submitted user details
			//$username = $mainframe->input->get('user');
			//$password = $mainframe->input->get('hash_passwrd');
			//get the userinfo directly from SMF
			//$JFusionUser = \JFusion\Factory::getUser($this->getJname());
			//$userinfo = $JFusionUser->getUser($username);
			//generate the password hash
			//$test_crypt = sha1($userinfo->password . $smf_session_id);
			//validate that the password is correct
			//if (!empty($password) && !empty($test_crypt) && $password == $test_crypt){
			//}

		}
		if ($action == 'verificationcode') {
			$mainframe->input->set('format', null);
		}

		// We're going to want a few globals... these are all set later.
		global $time_start, $maintenance, $msubject, $mmessage, $mbname, $language;
		global $boardurl, $boarddir, $sourcedir, $webmaster_email, $cookiename;
		global $db_connection, $db_server, $db_name, $db_user, $db_prefix, $db_persist, $db_error_send, $db_last_error;
		global $modSettings, $context, $sc, $user_info, $topic, $board, $txt;
		global $scripturl, $ID_MEMBER, $func;
		global $settings, $options, $board_info, $attachments, $messages_request, $memberContext, $db_character_set;
		global $db_cache, $db_count, $db_show_debug;

		// new in smf 2
		global $smcFunc, $mysql_set_mode, $cachedir, $db_passwd, $db_type, $ssi_db_user, $ssi_db_passwd, $board_info, $options;

		// Required to avoid a warning about a license violation even though this is not the case
		global $forum_version;

		// Get the path
		$source_path = $this->params->get('source_path');

		$index_file = $source_path . 'index.php';

		if ( ! is_file($index_file) ) {
			Framework::raise(LogLevel::WARNING, 'The path to the SMF index file set in the component preferences does not exist', $this->getJname());
		} else {
			//add handler to undo changes that plgSystemSef create
			$dispatcher = JEventDispatcher::getInstance();
			/**
			 * @ignore
			 * @var $method object
			 */
			$method = array('event' => 'onAfterRender', 'handler' => array($this, 'onAfterRender'));
			$dispatcher->attach($method);

			//set the current directory to SMF
			chdir($source_path);
			$this->callbackdata = $data;
			$this->callbackbypass = false;

			// Get the output
			ob_start(array($this, 'callback'));
			$h = ob_list_handlers();
			$rs = include_once($index_file);
			// die if popup
			if ($action == 'findmember' || $action == 'helpadmin' || $action == 'spellcheck' || $action == 'requestmembers' || strpos($action , 'xml') !== false ) {
				exit();
			} else {
				$this->callbackbypass = true;
			}
			while( in_array( get_class($this) . '::callback', $h) ) {
				$data->buffer .= ob_get_contents();
				ob_end_clean();
				$h = ob_list_handlers();
			}

			// needed to ensure option is defined after using smf frameless. bug/conflict with System - Highlight plugin
			$mainframe->input->set('option', 'com_jfusion');

			//change the current directory back to Joomla.
			chdir(JPATH_SITE);

			// Log an error if we could not include the file
			if (!$rs) {
				Framework::raise(LogLevel::WARNING, 'Could not find SMF in the specified directory', $this->getJname());
			}
		}
	}

	/**
	 * undo damage caused by plgSystemSef
	 *
	 * @return bool
	 */
	function onAfterRender()
	{
		$buffer = JFactory::getApplication()->getBody();

		$base = JUri::base(true) . '/';

		$regex_body  = '#src="' . preg_quote($base, '#') . '%#mSsi';
		$replace_body= 'src="%';

		$buffer = preg_replace($regex_body, $replace_body, $buffer);

		JFactory::getApplication()->setBody($buffer);
		return true;
	}

	/**
	 * @param $args
	 *
	 * @return bool
	 */
	function update($args)
	{
		if (isset($args['event']) && $args['event'] == 'onAfterRender') {
			return $this->onAfterRender();
		}
		return true;
	}

	/**
	 * @param object $data
	 *
	 * @return void
	 */
	function parseBody(&$data)
	{
		$regex_body		= array();
		$replace_body	= array();

		//fix for form actions
		$regex_body[] = '#action="(.*?)"(.*?)>#m';
		$replace_body[] = '';
		$callback_body[] = 'fixAction';

		$regex_body[] = '#(?<=href=["\'])' . $data->integratedURL . '(.*?)(?=["\'])#mSi';
		$replace_body[] = '';
		$callback_body[] = 'fixURL';
		$regex_body[] = '#(?<=href=["\'])(\#.*?)(?=["\'])#mSi';
		$replace_body[] = '';
		$callback_body[] = 'fixURL';

		$regex_body[]	= '#sScriptUrl: \'http://joomla.fanno.dk/smf2/index.php\'#mSsi';
		$replace_body[]	= 'sScriptUrl: \'' . $data->baseURL . '\'';

		// Captcha fix
		$regex_body[] = '#(?<=src=")' . $data->integratedURL . '(index.php\?action=verificationcode.*?)(?=")#si';
		$replace_body[] = '';
		$callback_body[] = 'fixURL';
		$regex_body[] = '#(?<=data=")' . $data->integratedURL . '(index.php\?action=verificationcode.*?)(?=")#si';
		$replace_body[] = '';
		$callback_body[] = 'fixURL';
		$regex_body[] = '#(?<=\(")' . $data->integratedURL . '(index.php\?action=verificationcode.*?)(?=")#si';
		$replace_body[] = '';
		$callback_body[] = 'fixUrlNoAmp';
		$regex_body[] = '#(?<=\>)' . $data->integratedURL . '(index.php\?action=verificationcode.*?)(?=</a>)#si';
		$replace_body[] = '';
		$callback_body[] = 'fixUrlNoAmp';

		foreach ($regex_body as $k => $v) {
			//check if we need to use callback
			if(!empty($callback_body[$k])){
				$data->body = preg_replace_callback($regex_body[$k], array(&$this, $callback_body[$k]), $data->body);
			} else {
				$data->body = preg_replace($regex_body[$k], $replace_body[$k], $data->body);
			}
		}
	}

	/**
	 * @param object $data
	 *
	 * @return void
	 */
	function parseHeader(&$data)
	{
		static $regex_header, $replace_header;
		if ( ! $regex_header || ! $replace_header )
		{
			$joomla_url = Factory::getParams('joomla_int')->get('source_url');

			$baseURLnoSef = 'index.php?option=com_jfusion&Itemid=' . Application::getInstance()->input->getInt('Itemid');
			if (substr($joomla_url, -1) == '/') $baseURLnoSef = $joomla_url . $baseURLnoSef;
			else $baseURLnoSef = $joomla_url . '/' . $baseURLnoSef;

			// Define our preg arrays
			$regex_header		= array();
			$replace_header	= array();

			//convert relative links into absolute links
			$regex_header[]	= '#(href|src)=("./|"/)(.*?)"#mS';
			$replace_header[]	= '$1="' . $data->integratedURL . '$3"';

			//$regex_header[]	= '#(href|src)="(.*)"#mS';
			//$replace_header[]	= 'href="' . $data->integratedURL . '$2"';

			//convert relative links into absolute links
			$regex_header[]	= '#(href|src)=("./|"/)(.*?)"#mS';
			$replace_header[]	= '$1="' . $data->integratedURL . '$3"';

			$regex_header[] = '#var smf_scripturl = ["\'](.*?)["\'];#mS';
			$replace_header[] = 'var smf_scripturl = "' . $baseURLnoSef . '&";';

			//fix for URL redirects
			$regex_body[] = '#(?<=")' . $data->integratedURL . '(index.php\?action=verificationcode;rand=.*?)(?=")#si';
			$replace_body[] = ''; //\'"\' . $this->fixUrl('index.php?$2$3',"' . $data->baseURL . '","' . $data->fullURL . '") . \'"\'';
			$callback_body[] = 'fixRedirect';
		}
		$data->header = preg_replace($regex_header, $replace_header, $data->header);
	}

	/**
	 * @param $matches
	 * @return string
	 */
	function fixUrl($matches)
	{
		$q = $matches[1];

		$baseURL = $this->data->baseURL;
		$fullURL = $this->data->fullURL;

		//SMF uses semi-colons to separate vars as well. Convert these to normal ampersands
		$q = str_replace(';', '&amp;', $q);
		if (strpos($q, '#') === 0) {
			$url = $fullURL . $q;
		} else {
			if (substr($baseURL, -1) != '/') {
				//non sef URls
				$q = str_replace('?', '&amp;', $q);
				$url = $baseURL . '&amp;jfile=' . $q;
			} else {
				$sefmode = $this->params->get('sefmode');
				if ($sefmode == 1) {
					$url = JFusionFunction::routeURL($q, Application::getInstance()->input->getInt('Itemid'));
				} else {
					//we can just append both variables
					$url = $baseURL . $q;
				}
			}
		}
		return $url;
	}

	/**
	 * Fix url with no amps
	 *
	 * @param array $matches
	 *
	 * @return string url
	 */
	function fixUrlNoAmp($matches)
	{
		$url = $this->fixUrl($matches);
		$url = str_replace('&amp;', '&', $url);
		return $url;
	}

	/**
	 * @param $matches
	 * @return string
	 */
	function fixAction($matches)
	{
		$url = $matches[1];
		$extra = $matches[2];

		$baseURL = $this->data->baseURL;
		//\JFusion\Framework::raise(LogLevel::WARNING, $url, $this->getJname());
		$url = htmlspecialchars_decode($url);
		$Itemid = Application::getInstance()->input->getInt('Itemid');
		$extra = stripslashes($extra);
		$url = str_replace(';', '&amp;', $url);
		if (substr($baseURL, -1) != '/') {
			//non-SEF mode
			$url_details = parse_url($url);
			$url_variables = array();
			$jfile = basename($url_details['path']);
			if (isset($url_details['query'])) {
				parse_str($url_details['query'], $url_variables);
				$baseURL.= '&amp;' . $url_details['query'];
			}
			//set the correct action and close the form tag
			$replacement = 'action="' . $baseURL . '"' . $extra . '>';
			$replacement.= '<input type="hidden" name="jfile" value="' . $jfile . '"/>';
			$replacement.= '<input type="hidden" name="Itemid" value="' . $Itemid . '"/>';
			$replacement.= '<input type="hidden" name="option" value="com_jfusion"/>';
		} else {
			//check to see what SEF mode is selected
			$sefmode = $this->params->get('sefmode');
			if ($sefmode == 1) {
				//extensive SEF parsing was selected
				$url = JFusionFunction::routeURL($url, $Itemid);
				$replacement = 'action="' . $url . '"' . $extra . '>';
				return $replacement;
			} else {
				//simple SEF mode
				$url_details = parse_url($url);
				$url_variables = array();
				$jfile = basename($url_details['path']);
				if (isset($url_details['query'])) {
					parse_str($url_details['query'], $url_variables);
					$jfile.= '?' . $url_details['query'];
				}
				$replacement = 'action="' . $baseURL . $jfile . '"' . $extra . '>';
			}
		}
		unset($url_variables['option'], $url_variables['jfile'], $url_variables['Itemid']);
		//add any other variables
		/* Commented out because of problems with wrong variables being set
		if (is_array($url_variables)){
		foreach ($url_variables as $key => $value){
		$replacement .=  '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
		}
		}
		*/
		return $replacement;
	}

	/**
	 * @param $matches
	 * @return string
	 */
	function fixRedirect($matches) {
		$url = $matches[1];
		$baseURL = $this->data->baseURL;

		//\JFusion\Framework::raise(LogLevel::WARNING, $url, $this->getJname());
		//split up the timeout from url
		$parts = explode(';url=', $url);
		$timeout = $parts[0];
		$uri = new Uri($parts[1]);
		$jfile = $uri->getPath();
		$jfile = basename($jfile);
		$query = $uri->getQuery(false);
		$fragment = $uri->getFragment();
		if (substr($baseURL, -1) != '/') {
			//non-SEF mode
			$redirectURL = $baseURL . '&amp;jfile=' . $jfile;
			if (!empty($query)) {
				$redirectURL.= '&amp;' . $query;
			}
		} else {
			//check to see what SEF mode is selected
			$sefmode = $this->params->get('sefmode');
			if ($sefmode == 1) {
				//extensive SEF parsing was selected
				$redirectURL = $jfile;
				if (!empty($query)) {
					$redirectURL.= '?' . $query;
				}
				$redirectURL = JFusionFunction::routeURL($redirectURL, Application::getInstance()->input->getInt('Itemid'));
			} else {
				//simple SEF mode, we can just combine both variables
				$redirectURL = $baseURL . $jfile;
				if (!empty($query)) {
					$redirectURL.= '?' . $query;
				}
			}
		}
		if (!empty($fragment)) {
			$redirectURL .= '#' . $fragment;
		}
		$return = '<meta http-equiv="refresh" content="' . $timeout . ';url=' . $redirectURL . '">';
		//\JFusion\Framework::raise(LogLevel::WARNING, htmlentities($return), $this->getJname());
		return $return;
	}

	/**
	 * @return array
	 */
	function getPathWay()
	{
		$pathway = array();
		try {
			$db = Factory::getDatabase($this->getJname());

			$mainframe = Application::getInstance();

			list ($board_id ) = explode('.', $mainframe->input->get('board'), 1);
			list ($topic_id ) = explode('.', $mainframe->input->get('topic'), 1);
			list ($action ) = explode(';', $mainframe->input->get('action'), 1);

			$msg = $mainframe->input->get('msg');

			$query = $db->getQuery(true)
				->select('id_topic, id_board, subject')
				->from('#__messages')
				->where('id_topic = ' . $db->quote($topic_id));

			$db->setQuery($query);
			$topic = $db->loadObject();

			if ($topic) {
				$board_id = $topic->id_board;
			}

			if ($board_id) {
				$boards = array();
				// Loop while the parent is non-zero.
				while ($board_id != 0)
				{
					$query = $db->getQuery(true)
						->select('b.id_parent , b.id_board, b.id_cat, b.name , c.name as catname')
						->from('#__boards AS b')
						->innerJoin('#__categories AS c ON b.id_cat = c.id_cat')
						->where('id_board = ' . $db->quote($board_id));

					$db->setQuery($query);
					$result = $db->loadObject();

					$board_id = 0;
					if ($result) {
						$board_id = $result->id_parent;
						$boards[] = $result;
					}
				}
				$boards = array_reverse($boards);
				$cat_id = 0;
				foreach($boards as $board) {
					$path = new stdClass();
					if ($board->id_cat != $cat_id) {
						$cat_id = $board->id_cat;
						$path->title = $board->catname;
						$path->url = 'index.php#' . $board->id_cat;
						$pathway[] = $path;

						$path = new stdClass();
						$path->title = $board->name;
						$path->url = 'index.php?board=' . $board->id_board . '.0';
					} else {
						$path->title = $board->name;
						$path->url = 'index.php?board=' . $board->id_board . '.0';
					}
					$pathway[] = $path;
				}
			}
			switch ($action) {
				case 'post':
					$path = new stdClass();
					if ( $mainframe->input->get('board') ) {
						$path->title = 'Modify Toppic ( Start new topic )';
						$path->url = 'index.php?action=post&board=' . $board_id . '.0';;
					} else if ($msg) {
						$path->title = 'Modify Toppic ( ' . $topic->subject . ' )';
						$path->url = 'index.php?action=post&topic=' . $topic_id . '.msg' . $msg . '#msg' . $msg;
					} else {
						$path->title = 'Post reply ( Re: ' . $topic->subject . ' )';
						$path->url = 'index.php?action=post&topic=' . $topic_id;
					}
					$pathway[] = $path;
					break;
				case 'pm':
					$path = new stdClass();
					$path->title = 'Personal Messages';
					$path->url = 'index.php?action=pm';
					$pathway[] = $path;

					$path = new stdClass();
					if ( $mainframe->input->get('sa') == 'send' ) {
						$path->title = 'New Message';
						$path->url = 'index.php?action=pm&sa=send';
						$pathway[] = $path;
					} elseif ( $mainframe->input->get('sa') == 'search' ) {
						$path->title = 'Search Messages';
						$path->url = 'index.php?action=pm&sa=search';
						$pathway[] = $path;
					} elseif ( $mainframe->input->get('sa') == 'prune' ) {
						$path->title = 'Prune Messages';
						$path->url = 'index.php?action=pm&sa=prune';
						$pathway[] = $path;
					} elseif ( $mainframe->input->get('sa') == 'manlabels' ) {
						$path->title = 'Manage Labels';
						$path->url = 'index.php?action=pm&sa=manlabels';
						$pathway[] = $path;
					} elseif ( $mainframe->input->get('f') == 'outbox' ) {
						$path->title = 'Outbox';
						$path->url = 'index.php?action=pm&f=outbox';
						$pathway[] = $path;
					} else {
						$path->title = 'Inbox';
						$path->url = 'index.php?action=pm';
						$pathway[] = $path;
					}
					break;
				case 'search2':
					$path = new stdClass();
					$path->title = 'Search';
					$path->url = 'index.php?action=search';
					$pathway[] = $path;
					$path = new stdClass();
					$path->title = 'Search Results';
					$path->url = 'index.php?action=search';
					$pathway[] = $path;
					break;
				case 'search':
					$path = new stdClass();
					$path->title = 'Search';
					$path->url = 'index.php?action=search';
					$pathway[] = $path;
					break;
				case 'unread':
					$path = new stdClass();
					$path->title = 'Recent Unread Topics';
					$path->url = 'index.php?action=unread';
					$pathway[] = $path;
					break;
				case 'unreadreplies':
					$path = new stdClass();
					$path->title = 'Updated Topics';
					$path->url = 'index.php?action=unreadreplies';
					$pathway[] = $path;
					break;
				default:
					if ($topic_id) {
						$path = new stdClass();
						$path->title = $topic->subject;
						$path->url = 'index.php?topic=' . $topic_id;
						$pathway[] = $path;
					}
			}
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		}
		return $pathway;
	}

	/**
	 * @param $buffer
	 *
	 * @return mixed|string
	 */
	function callback($buffer) {
		$data = $this->callbackdata;
		$headers_list = headers_list();
		foreach ($headers_list as $value) {
			$matches = array();
			if (stripos($value, 'location') === 0) {
				if (preg_match('#' . preg_quote($data->integratedURL, '#') . '(.*?)\z#Sis', $value, $matches)) {
					header('Location: ' . $this->fixUrlNoAmp($matches));
					return $buffer;
				}
			} else if (stripos($value, 'refresh') === 0) {
				if (preg_match('#: (.*?) URL=' . preg_quote($data->integratedURL, '#') . '(.*?)\z#Sis', $value, $matches)) {
					$time = $matches[1];
					$matches[1] = $matches[2];
					header('Refresh: ' . $time . ' URL=' . $this->fixUrlNoAmp($matches));
					return $buffer;
				}
			}
		}
		if ($this->callbackbypass) return $buffer;
		global $context;

		if (isset($context['get_data'])) {
			if ($context['get_data'] && strpos($context['get_data'], 'jFusion_Route')) {
				$buffer = str_replace ($context['get_data'], '?action=admin', $buffer);
			}
		}

		//fix for form actions
		$data->buffer = $buffer;
		ini_set('pcre.backtrack_limit', strlen($data->buffer) * 2);
		$pattern = '#<head[^>]*>(.*?)<\/head>.*?<body([^>]*)>(.*)<\/body>#si';
		if (preg_match($pattern, $data->buffer, $temp)) {
			$data->header = $temp[1];
			$data->body = $temp[3];
			$pattern = '#onload=["]([^"]*)#si';
			if (preg_match($pattern, $temp[2], $temp)) {
				$js ='<script language="JavaScript" type="text/javascript">';
				$js .= <<<JS
                if(window.addEventListener) { // Standard
                    window.addEventListener(\'load\', function(){
                        {$temp[1]}
                    }, false);
                } else if(window.attachEvent) { // IE
                    window.attachEvent(\'onload\', function(){
                        {$temp[1]}
                    });jfusionButtonConfirmationBox
                }
JS;
				$js .='</script>';
				$data->header.= $js;
			}
			unset($temp);
			$this->parseHeader($data);
			$this->parseBody($data);
			return '<html><head>' . $data->header . '</head><body>' . $data->body . '<body></html>';
		} else {
			return $buffer;
		}
	}
}
