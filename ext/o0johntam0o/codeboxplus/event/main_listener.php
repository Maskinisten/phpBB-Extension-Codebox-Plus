<?php
/**
*
* Codebox Plus extension for the phpBB Forum Software package
*
* @copyright (c) 2014 o0johntam0o
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace o0johntam0o\codeboxplus\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class main_listener implements EventSubscriberInterface
{
	protected $helper, $template, $user, $config, $root_path, $php_ext;
	protected $codebox_plus_enabled, $download_enabled, $find, $find_code, $find_lang, $find_file;
	
	public function __construct(\phpbb\controller\helper $helper, \phpbb\template\template $template, \phpbb\user $user, \phpbb\config\config $config, $root_path, $php_ext)
	{
		$this->helper = $helper;
		$this->template = $template;
		$this->user = $user;
		$this->config = $config;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		
		$this->codebox_plus_enabled = isset($this->config['codebox_plus_enable']) ? $this->config['codebox_plus_enable'] : 0;
		$this->download_enabled = isset($this->config['codebox_plus_download']) ? $this->config['codebox_plus_download'] : 0;
	}
	
	static public function getSubscribedEvents()
    {
        return array(
            'core.user_setup'							=> 'load_language_on_setup',
            'core.modify_submit_post_data'				=> 'posting_modify_input',
            'core.posting_modify_template_vars'			=> 'posting_event',
            'core.viewtopic_post_rowset_data'			=> 'viewtopic_event',
            'core.modify_format_display_text_after'		=> 'message_parser_event',
        );
    }
	
    public function load_language_on_setup($event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        $lang_set_ext[] = array(
            'ext_name' => 'o0johntam0o/codeboxplus',
            'lang_set' => 'codebox_plus',
        );
        $event['lang_set_ext'] = $lang_set_ext;
		
		if ($this->user->page['page_name'] == 'posting.' . $this->php_ext)
		{
			$this->template->assign_vars(array(
				'CODEBOX_PLUS_IN_POSTING'				=> true,
			));
		}
		
		if ($this->codebox_plus_enabled)
		{
			$this->template->assign_vars(array(
				'CODEBOX_PLUS_AVAILABLE'				=> true,
			));
		}
		
		if ($this->download_enabled)
		{
			$this->template->assign_vars(array(
				'CODEBOX_PLUS_DOWNLOAD_AVAILABLE'		=> true,
			));
		}
    }
	
	/*
	* Event: core.modify_format_display_text_after (message_parser.php)
	* Use: $this->codebox_template()
	* Generate text for preview
	*/
	public function message_parser_event($event)
	{
		if (isset($event['text']))
		{
			$text = $event['text'];
			$text = preg_replace('#' . preg_quote('<div class="codebox_plus_wrap"><div class="codebox_plus_header">') . '.*?' . preg_quote('<div class="codebox_plus_footer"><a href="http://qbnz.com/highlighter/">GeSHi</a> &copy; <a href="https://www.phpbb.com/customise/db/extension/codeboxplus/">Codebox Plus</a></div></div>') . '#msi', $this->codebox_template('NULL', 'NULL'), $text);
			$event['text'] = $text;
		}
    }
	
	/*
	* Event: core.posting_modify_template_vars (posting.php)
	* Remove extra space before '[/code]'
	*/
	public function posting_event($event)
	{
		if (isset($event['page_data']))
		{
			$page_data = $event['page_data'];
			$message = $page_data['MESSAGE'];
			$message = str_replace(' [/code]', '[/code]', $message);
			
			if (isset($page_data['MESSAGE']))
			{
				$page_data['MESSAGE'] = $message;
				$event['page_data'] = $page_data;
			}
		}
    }
	
	/*
	* Event: core.viewtopic_post_rowset_data (viewtopic.php)
	*/
    public function viewtopic_event($event)
    {
		if (isset($event['rowset_data']))
		{
			$rowset_data = $event['rowset_data'];
			$post_text = isset($rowset_data['post_text']) ? $rowset_data['post_text'] : '';
			$bbcode_uid = isset($rowset_data['bbcode_uid']) ? $rowset_data['bbcode_uid'] : '';
			$post_id = isset($rowset_data['post_id']) ? $rowset_data['post_id'] : 0;
			$part = 0;
			
			while (preg_match("#\[codebox=[a-z0-9_-]+ file=(.*?):" . $bbcode_uid . "\](.*?)\[/codebox:" . $bbcode_uid . "\]#msi", $post_text))
			{
				$part++;
				
				if ($this->codebox_plus_enabled)
				{
					$post_text = preg_replace("#\[codebox=([a-z0-9_-]+) file=(.*?):" . $bbcode_uid . "\](.*?)\[/codebox:" . $bbcode_uid . "\]#msie", "\$this->codebox_template('\$3', '\$1', '\$2', \$post_id, \$part)", $post_text, 1);
				}
				else
				{
					$post_text = preg_replace("#\[codebox=[a-z0-9_-]+ file=(.*?):" . $bbcode_uid . "\](.*?)\[/codebox:" . $bbcode_uid . "\]#msie", "\$this->codebox_decode_code('\$2', \$bbcode_uid)", $post_text, 1);
				}
			}
			
			if (isset($rowset_data['post_text']) && $part > 0)
			{
				$rowset_data['post_text'] = $post_text;
				$event['rowset_data'] = $rowset_data;
			}
		}
	}
	
	/*
	* Event: core.modify_submit_post_data (includes/functions_posting.php)
	* Use: $this->codebox_clean_code()
	* Generate text for storage
	*/
	public function posting_modify_input($event)
	{
		if (isset($event['data']))
		{
			// REQUEST
			$data = $event['data'];
			$message = $data['message'];
			$bbcode_uid = $data['bbcode_uid'];
			// MODIFY
			$message = preg_replace("#(\[codebox=[a-z0-9_-]+ file=(?:.*?):" . $bbcode_uid . "\])(.*?)(\[/codebox:" . $bbcode_uid . "\])#msie", "'\$1' . \$this->codebox_clean_code('\$2', \$bbcode_uid) . '\$3'", $message);
			// RETURN
			$data['message'] = $message;
			$event['data'] = $data;
			$event['update_message'] = true;
		}
    }
	
	/*
	* Use: $this->codebox_parse_code(), $this->codebox_decode_code()
	* Generate text for display
	*/
	public function codebox_template($code = '', $lang = 'text', $file = '', $id = 0, $part = 0)
	{
		if (strlen($code) == 0)
		{
			return '';
		}
		
		if (strlen($file) == 0)
		{
			$file = $this->user->lang['CODEBOX_PLUS_DEFAULT_FILENAME'] . 'txt';
		}
		
		$re = '<div class="codebox_plus_wrap"><div class="codebox_plus_header">';
		$re .= '<strong>' . $this->user->lang['CODEBOX_PLUS_CODE'] . ': </strong>';
		$re .= '<a href="#" onclick="codebox_plus_select(this, 1); return false;">[' . $this->user->lang['SELECT_ALL_CODE'] . ']</a>';
		$re .= '&nbsp;<a href="#" onclick="codebox_plus_toggle(this, 1); return false;">[' . $this->user->lang['CODEBOX_PLUS_EXPAND'] . '/' . $this->user->lang['CODEBOX_PLUS_COLLAPSE'] . ']</a>';
		
		if ($this->download_enabled && $lang != 'NULL')
		{
			$re .= '&nbsp;<a href="' . $this->helper->route('o0johntam0o_codeboxplus_download_controller', array('id' => $id, 'part' => $part)) . '" onclick="window.open(this.href); return false;">';
			$re .= '[' . $this->user->lang['CODEBOX_PLUS_DOWNLOAD'] . ']</a> ' . '('. $file . ')';
		}
		
		$re .= '</div>';
		$re .= '<div><div style="display: none;">';
		
		if ($lang != 'NULL')
		{
			$re .= $this->codebox_parse_code($this->codebox_decode_code($code), $lang);
		}
		else
		{
			$re .= $this->user->lang['CODEBOX_PLUS_NO_PREVIEW'];
		}
		
		$re .= '</div></div>';
		$re .= '<div class="codebox_plus_footer"><a href="http://qbnz.com/highlighter/">GeSHi</a> &copy; <a href="https://www.phpbb.com/customise/db/extension/codeboxplus/">Codebox Plus</a></div></div>';
		
		return $re;
	}
	
	/*
	* Syntax highlighter
	*/
	private function codebox_parse_code($code = '', $lang = 'text')
	{
		if (strlen($code) == 0)
		{
			return '';
		}
		// Remove newline at the beginning
		if (!empty($code) && $code[0] == "\n")
		{
			$code = substr($code, 1);
		}
		
		// GeSHi
		if (!class_exists("GeSHi"))
		{
			include($this->root_path . 'ext/o0johntam0o/codeboxplus/includes/geshi/geshi.' . $this->php_ext);
		}
		
		$geshi = new \GeSHi($code, $lang);
		$geshi->set_header_type(GESHI_HEADER_DIV);
		$geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
		$geshi->enable_keyword_links(false);
		$geshi->set_line_style('margin-left:20px;', false);
		$geshi->set_code_style('border-bottom: dotted 1px #cccccc;', false);
		$geshi->set_line_ending("\n");
		$code = str_replace("\n", "", $geshi->parse_code());
		
		return $code;
	}
	
	/*
	* Decode some special characters
	*/
	private function codebox_decode_code($code = '', $bbcode_uid = '')
	{
		if (strlen($code) == 0)
		{
			return $code;
		}
		
		$str_from = array('&lt;', '&gt;', '&#91;', '&#93;', '&#40;', '&#41;', '&#46;', '&#58;', '&#058;', '&#39;', '&#039;', '&quot;', '&amp;');
		$str_to = array('<', '>', '[', ']', '(', ')', '.', ':', ':', "'", "'", '"', '&');
		$code = str_replace($str_from, $str_to, $code);
		
		if (strlen($bbcode_uid) == 0)
		{
			return $code;
		}
		else
		{
			return '[code:' . $bbcode_uid . ']' . $code . '[/code:' . $bbcode_uid . ']';
		}
	}
	
	/*
	* Remove BBCodes UID & Smilies & Emails
	*/
	private function codebox_clean_code($code = '', $bbcode_uid = '')
	{
		if (strlen($code) == 0 || strlen($bbcode_uid) == 0)
		{
			return $code;
		}
		
		// Email
		$code = preg_replace('#<!-- e --><a href=\\\\"mailto:(?:.*?)\\\\">(.*?)</a><!-- e -->#msi', '$1', $code);
		// Smilies
		$code = preg_replace('#<!-- s(.*?) --><img src=\\\\"{SMILIES_PATH}/(?:.*?)\\\\" /><!-- s(?:.*?) -->#msi', '$1', $code);
		// BBCodes
		$code = str_replace(':o:' . $bbcode_uid, '', $code);
		$code = str_replace(':u:' . $bbcode_uid, '', $code);
		$code = str_replace(':m:' . $bbcode_uid, '', $code);
		$code = str_replace(':' . $bbcode_uid, '', $code);
		// Trouble with BBCode [CODE]
		$code = str_replace('<br />', "\n", $code);
		$code = str_replace('\\"', '&quot;', $code);
		$code = str_replace('&nbsp;', ' ', $code);
		$code = preg_replace('#<(.*?)>#msi', '', $code);
		
		return $code;
	}
}
