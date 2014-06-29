<?php

/**
*
* @package phpBB Extension - Codebox Plus
* @copyright (c) 2014 o0johntam0o
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
// TODO: Improve anti-spam feature
namespace o0johntam0o\codebox_plus\controller;

class main
{
	protected $enable_codebox_plus, $enable_download, $enable_login_required, $enable_prevent_bots, $enable_captcha, $max_attempt;
	protected $helper, $template, $user, $config, $auth, $request, $db, $root_path, $php_ext;

	public function __construct(\phpbb\controller\helper $helper, \phpbb\template\template $template, \phpbb\user $user, \phpbb\config\config $config, \phpbb\auth\auth $auth, \phpbb\request\request $request, \phpbb\db\driver\driver $db, $root_path, $php_ext)
	{
		$this->helper = $helper;
		$this->template = $template;
		$this->user = $user;
		$this->config = $config;
		$this->auth = $auth;
		$this->request = $request;
		$this->db = $db;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		
		$this->user->session_begin();
		$this->auth->acl($this->user->data);
		
		$this->enable_codebox_plus = isset($this->config['codebox_plus_enable']) ? $this->config['codebox_plus_enable'] : 0;
		$this->enable_download = isset($this->config['codebox_plus_download']) ? $this->config['codebox_plus_download'] : 0;
		$this->enable_login_required = isset($this->config['codebox_plus_login_required']) ? $this->config['codebox_plus_login_required'] : 0;
		$this->enable_prevent_bots = isset($this->config['codebox_plus_prevent_bots']) ? $this->config['codebox_plus_prevent_bots'] : 0;
		$this->enable_captcha = isset($this->config['codebox_plus_captcha']) ? $this->config['codebox_plus_captcha'] : 0;
		$this->max_attempt = isset($this->config['codebox_plus_max_attempt']) ? $this->config['codebox_plus_max_attempt'] : 0;
	}

	public function base()
	{
		$this->template->assign_vars(array(
			'CODEBOX_PLUS_AVAILABLE'				=> false,
			));

		return $this->helper->render('codebox_plus.html', $this->user->lang['CODEBOX_PLUS_DOWNLOAD']);
	}
	
	public function downloader($mode = 0, $id = 0, $part = 0)
	{
		// If Codebox Plus was disabled
		if (!$this->enable_codebox_plus)
		{
			trigger_error($this->user->lang['CODEBOX_PLUS_ERROR_CODEBOX_PLUS_DISABLED']);
		}
		// If download function was disabled
		if (!$this->enable_download)
		{
			trigger_error($this->user->lang['CODEBOX_PLUS_ERROR_DOWNLOAD_DISABLED']);
		}
		// Prevent bots
		if ($this->enable_prevent_bots && $this->user->data['is_bot'])
		{
			redirect(append_sid("{$this->root_path}index.$this->php_ext"));
		}
		// Login to download
		if ($this->enable_login_required && !$this->user->data['is_registered'])
		{
			login_box($this->helper->route('codebox_plus_download_controller', array('mode' => $mode, 'id' => $id, 'part' => $part)), $this->user->lang['CODEBOX_PLUS_ERROR_LOGIN_REQUIRED']);
		}
		
		// Captcha
		if ($this->enable_captcha && $this->config['enable_confirm'])
		{
			if (!class_exists('phpbb_captcha_factory'))
			{
				include("{$this->root_path}includes/captcha/captcha_factory.$this->php_ext");
			}
			
			$captcha = \phpbb_captcha_factory::get_instance($this->config['captcha_plugin']);
			$captcha->init(CONFIRM_POST);
			$ok = false;
			
			if ($this->request->is_set_post('submit'))
			{
				$captcha->validate();
				if ($captcha->is_solved())
				{
					$captcha->reset();
					// Everything is ok, start download
					$this->codebox_output($mode, $id, $part);
					$ok = true;
				}
			}
			
			// If the form was not submitted yet or the CAPTCHA was not solved
			if (!$ok)
			{
				// Too many request...
				if ($captcha->get_attempt_count() >= $this->max_attempt)
				{
					trigger_error($this->user->lang['CODEBOX_PLUS_ERROR_CONFIRM']);
				}
				
				$this->template->assign_vars(array(
					'S_CODE_DOWNLOADER_ACTION'		=> $this->helper->route('codebox_plus_download_controller', array('mode' => $mode, 'id' => $id, 'part' => $part)),
					'S_CONFIRM_CODE'                => true,
					'CAPTCHA_TEMPLATE'              => $captcha->get_template(),
				));

				return $this->helper->render('codebox_plus.html', $this->user->lang['CODEBOX_PLUS_DOWNLOAD']);
			}
			else
			{
				garbage_collection();
				exit_handler();
			}
		}
		else
		{
			// Everything is ok, start download
			$this->codebox_output($mode, $id, $part);
			garbage_collection();
			exit_handler();
		}
	}
	
	private function codebox_output($mode, $id, $part)
	{
		$id = (int) $id;
		$part = (int) $part;
		$code = '';
		$filename = $this->user->lang['CODEBOX_PLUS_DEFAULT_FILENAME'];
		$post_data = array();
		$code_data = array();

		// CHECK REQUEST
		if ($id <= 0 || $part <= 0)
		{
			trigger_error($this->user->lang['CODEBOX_PLUS_ERROR_GENERAL']);
		}

		// PROCESS REQUEST
		//- Get post data
		switch ($mode)
		{
			case 1:
				$table = PRIVMSGS_TABLE;
				$col_msg_id = 'msg_id';
				$col_msg_text = 'message_text';
				$col_bbcode_uid = 'bbcode_uid';
			break;
			
			case 2:
				$table = USERS_TABLE;
				$col_msg_id = 'user_id';
				$col_msg_text = 'user_sig';
				$col_bbcode_uid = 'user_sig_bbcode_uid';
			break;
			
			default:
				$table = POSTS_TABLE;
				$col_msg_id = 'post_id';
				$col_msg_text = 'post_text';
				$col_bbcode_uid = 'bbcode_uid';
			break;
		}

		$sql = "SELECT $col_msg_text, $col_bbcode_uid FROM $table WHERE $col_msg_id = $id";
		$result = $this->db->sql_query($sql);
		$post_data = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($post_data === false)
		{
			trigger_error($this->user->lang['CODEBOX_PLUS_ERROR_POST_NOT_FOUND']);
		}

		//- Process post data
		// Collect code
		preg_match_all("#\[codebox=[a-z0-9_-]+ file=(.*?):" . $post_data[$col_bbcode_uid] . "\](.*?)\[/codebox:" . $post_data[$col_bbcode_uid] . "\]#msi", $post_data[$col_msg_text], $code_data);
		
		if (count($code_data[2]) >= $part)
		{
			$part--;
			$code = $code_data[2][$part];
			
			if ($code != '')
			{
				// Remove BBCodes & Smilies
				$code = $this->codebox_clean_code($code, $post_data[$col_bbcode_uid]);
				
				if ($code_data[1][$part] != '')
				{
					$filename = $code_data[1][$part];
				}
			}
			else
			{
				trigger_error($this->user->lang['CODEBOX_PLUS_ERROR_FILE_EMPTY']);
			}
		}
		else
		{
			trigger_error($this->user->lang['CODEBOX_PLUS_ERROR_CODE_NOT_FOUND']);
		}

		// RESPOND
		header('Content-Type: application/force-download');
		header('Content-Length: ' . strlen($code));
		header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');

		echo $code;
	}
	
	// From main_listener.php
	private function codebox_clean_code($code = '', $bbcode_uid = '')
	{
		if (strlen($code) == 0 || strlen($bbcode_uid) == 0)
		{
			return $code;
		}
		// Email
		$code = preg_replace('#<!-- e --><a href=\\\\"mailto:(?:.*?)\\\\">(.*?)</a><!-- e -->#msi', '$1', $code);
		// Smilies - a bit of change
		$code = preg_replace('#<!-- s(.*?) --><img src=\\"{SMILIES_PATH}/(?:.*?)\\" /><!-- s(?:.*?) -->#msi', '$1', $code);
		// To prevent -> [CODE1][CODE2]TEXT[/CODE2][/CODE1]
		// BBCodes with no param
		$code = preg_replace('#\[b:' . $bbcode_uid . '\](.*?)\[/b:' . $bbcode_uid . '\]#msi', '[b]$1[/b]', $code);
		$code = preg_replace('#\[i:' . $bbcode_uid . '\](.*?)\[/i:' . $bbcode_uid . '\]#msi', '[i]$1[/i]', $code);
		$code = preg_replace('#\[u:' . $bbcode_uid . '\](.*?)\[/u:' . $bbcode_uid . '\]#msi', '[u]$1[/u]', $code);
		$code = preg_replace('#\[img:' . $bbcode_uid . '\](.*?)\[/img:' . $bbcode_uid . '\]#msi', '[img]$1[/img]', $code);
		$code = preg_replace('#\[\*:' . $bbcode_uid . '\](.*?)\[/\*:' . $bbcode_uid . '\]#msi', '[*]$1[/*]', $code);
		$code = preg_replace('#\[code:' . $bbcode_uid . '\](.*?)\[/code:' . $bbcode_uid . '\]#msi', '[code]$1[/code]', $code);
		$code = preg_replace('#\[quote:' . $bbcode_uid . '\](.*?)\[/quote:' . $bbcode_uid . '\]#msi', '[quote]$1[/quote]', $code);
		$code = preg_replace('#\[url:' . $bbcode_uid . '\](.*?)\[/url:' . $bbcode_uid . '\]#msi', '[url]$1[/url]', $code);
		$code = preg_replace('#\[list:' . $bbcode_uid . '\](.*?)\[/list:u:' . $bbcode_uid . '\]#msi', '[list]$1[/list]', $code);
		// BBCodes with params
		$code = preg_replace('#\[code=([a-z]+):' . $bbcode_uid . '\](.*?)\[/code:' . $bbcode_uid . '\]#msi', '[code=$1]$2[/code]', $code);
		$code = preg_replace('#\[quote=&quot;(.*?)&quot;:' . $bbcode_uid . '\](.*?)\[/quote:' . $bbcode_uid . '\]#msi', '[quote="$1"]$2[/quote]', $code);
		$code = preg_replace('#\[url=(.*?):' . $bbcode_uid . '\](.*?)\[/url:' . $bbcode_uid . '\]#msi', '[url=$1]$2[/url]', $code);
		$code = preg_replace('#\[list=([a-z0-9]|disc|circle|square):' . $bbcode_uid . '\](.*)\[/list:u:' . $bbcode_uid . '\]#msi', '[list=$1]$2[/list]', $code);
		$code = preg_replace('#\[size=([\-\+]?\d+):' . $bbcode_uid . '\](.*?)\[/size:' . $bbcode_uid . '\]#msi', '[size=$1]$2[/size]', $code);
		$code = preg_replace('!\[color=(#[0-9a-f]{3}|#[0-9a-f]{6}|[a-z\-]+):' . $bbcode_uid . '\](.*?)\[/color:' . $bbcode_uid . '\]!msi', '[color=$1]$2[/color]', $code);
		$code = preg_replace('#\[flash=([0-9]+,[0-9]+):' . $bbcode_uid . '\](.*?)\[/flash:' . $bbcode_uid . '\]#msi', '[flash=$1]$2[/flash]', $code);
		$code = preg_replace('#\[attachment=([0-9]+):' . $bbcode_uid . '\]<(?:.*?)>(.*?)<(?:.*?)>\[/attachment:' . $bbcode_uid . '\]#msi', '[attachment=$1]$2[/attachment]', $code);
		// A trouble with [CODE=PHP][/CODE]
		$code = preg_replace('#<(.*?)>#msi', '', $code);
		$code = preg_replace('#&nbsp;#msi', ' ', $code);
		// Some characters was encoded before. We have to decode it
		$str_from = array('<br />', '\"', '&lt;', '&gt;', '&#91;', '&#93;', '&#40;', '&#41;', '&#46;', '&#58;', '&#058;', '&#39;', '&#039;', '&quot;', '&amp;');
		$str_to = array("\n", '"', '<', '>', '[', ']', '(', ')', '.', ':', ':', "'", "'", '"', '&');
		$code = str_replace($str_from, $str_to, $code);
		
		return $code;
	}
}
