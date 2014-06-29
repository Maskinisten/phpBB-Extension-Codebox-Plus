<?php

/**
*
* @package phpBB Extension - Codebox Plus
* @copyright (c) 2014 o0johntam0o
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace o0johntam0o\codebox_plus\migrations;

class release_1_0_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['codebox_plus_version']) && version_compare($this->config['codebox_plus_version'], '1.0.0', '>=');
    }

    static public function depends_on()
    {
        return array('\phpbb\db\migration\data\v310\dev');
    }

    public function update_data()
    {
        return array(
			array('custom', array(array($this, 'install_bbcode_codebox'))),
			
            array('config.add', array('codebox_plus_enable', 1)),
            array('config.add', array('codebox_plus_download', 1)),
            array('config.add', array('codebox_plus_login_required', 0)),
            array('config.add', array('codebox_plus_prevent_bots', 1)),
            array('config.add', array('codebox_plus_captcha', 1)),
            array('config.add', array('codebox_plus_max_attempt', 3)),

            array('module.add', array(
                'acp',
                'ACP_CAT_DOT_MODS',
                'CODEBOX_PLUS_TITLE_ACP'
            )),
			
            array('module.add', array(
                'acp',
                'CODEBOX_PLUS_TITLE_ACP',
                array(
                    'module_basename'   => '\o0johntam0o\codebox_plus\acp\main_module',
                    'modes'             => array('config_codebox_plus'),
                ),
            )),

            array('config.add', array('codebox_plus_version', '1.0.0')),
        );
    }
	
	public function install_bbcode_codebox()
	{
		$sql = 'SELECT bbcode_id FROM ' . $this->table_prefix . 'bbcodes WHERE LOWER(bbcode_tag) = "Codebox="';
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		
		if (!$row)
		{
			// Create new BBCode
			$sql = 'SELECT MAX(bbcode_id) AS max_bbcode_id FROM ' . $this->table_prefix . 'bbcodes';
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
			
			if ($row)
			{
				$bbcode_id = $row['max_bbcode_id'] + 1;

				// Make sure it is greater than the core BBCode ids...
				if ($bbcode_id <= NUM_CORE_BBCODES)
				{
					$bbcode_id = NUM_CORE_BBCODES + 1;
				}
			}
			else
			{
				$bbcode_id = NUM_CORE_BBCODES + 1;
			}
			
			if ($bbcode_id <= BBCODE_LIMIT)
			{
				$this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'bbcodes ' . $this->db->sql_build_array(
					'INSERT',
					array(
						'bbcode_tag'			=> 'Codebox=',
						'bbcode_id'				=> (int) $bbcode_id,
						'bbcode_helpline'		=> '',
						'display_on_posting'	=> 0,
						'bbcode_match'			=> '[Codebox={SIMPLETEXT1} file={SIMPLETEXT2}]{TEXT}[/Codebox]',
						'bbcode_tpl'			=> '',
						'first_pass_match'		=> '!\[codebox\=([a-zA-Z0-9-+.,_ ]+) file\=([a-zA-Z0-9-+.,_ ]+)\](.*?)\[/codebox\]!ies',
						'first_pass_replace'	=> '\'[codebox=${1} file=${2}:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${3}\')).\'[/codebox:$uid]\'',
						'second_pass_match'		=> '!\[codebox\=([a-zA-Z0-9-+.,_ ]+) file\=([a-zA-Z0-9-+.,_ ]+):$uid\](.*?)\[/codebox:$uid\]!s',
						'second_pass_replace'	=> ''
					)
				));
			}
		}
	}
}
