<?php
/*
==========================================================
	This software package is intended for use with 
	ExpressionEngine.	ExpressionEngine is Copyright © 
	2002-2011 EllisLab, Inc. 
	http://ellislab.com/
==========================================================
	THIS IS COPYRIGHTED SOFTWARE, All RIGHTS RESERVED.
	Written by: Louis Dekeister
	Copyright (c) 2014 Hop Studios
	http://www.hopstudios.com/software/
--------------------------------------------------------
	Please do not distribute this software without written
	consent from the author.
==========================================================
*/

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
  'pi_name'			=> 'Hop Fetch Date',
  'pi_version'		=> '1.0',
  'pi_author' 		=> 'Louis Dekeister (Hop Studios)',
  'pi_author_url' 	=> 'http://www.hopstudios.com/software/',
  'pi_description' 	=> 'Display a date from a url, url_title or entry_id',
  'pi_usage' 		=> Hop_fetch_date::usage()
);

/**
 * Hop_fetch_date Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			Louis Dekeister (Hop Studios)
 * @copyright		Copyright (c) 2017, Hopstudios
 * @link			http://www.hopstudios.com/software
 */

class Hop_fetch_date
{

	public $return_data = "";
	private $_cache_ttl = 3600;

	// constructor
	function __construct() 
	{
		$this->return_data = '';
		// Retrieve an entry from entry id, url title or a full url
		$entry_id = intval(ee()->TMPL->fetch_param('entry_id'));
		$url_title = ee()->TMPL->fetch_param('url_title');
		$channel_id = intval(ee()->TMPL->fetch_param('channel_id'));
		$url = ee()->TMPL->fetch_param('url');
		$date_format = ee()->TMPL->fetch_param('date_format');

		$caching = get_bool_from_string(ee()->TMPL->fetch_param('use_cache', 'yes'));
		$cache_ttl = intval(ee()->TMPL->fetch_param('cache_ttl'));

		if ($cache_ttl != 0)
		{
			$this->_cache_ttl = $cache_ttl;
		}

		if ($url != NULL && $url != '')
		{
			// Get the latest segment and try to use it as a url_title
			// the last two remove ...#anything and ...?anything from the link
			// this handles Analytics extras like ?utm_source=dlvr.it&utm_medium=twitter
			// specific to site.com
			// $str = array("%https?://(www.)?site.com/([^/]+/)?article/%", "%/%", "%#.+$%", "%\?.+$%");
			// $rpl = array("", "", "", "");

			// $this->return_data = preg_replace($str, $rpl, $url);
			// $url_title = preg_replace($str, $rpl, $url);

			// Regular Expression would have worked too
			// $re = '/article\/(.+)(?:\#|\?|$)/';
			// preg_match($re, $link, $matches);
			// $matches[1] would contain the url_title

			$last_segment = basename($url);

			// Clean the last segment from anchor and GET parameters
			$re = '/(.+)(?:\?|#)/U';
			preg_match($re, $last_segment, $matches);

			if (count($matches) == 0)
			{
				$url_title = $last_segment;
			}
			else if (count($matches) == 2)
			{
				$url_title = $matches[1];
			}
			else
			{
				// Well, we shouldn't get here...
			}
		}

		if ($entry_id != 0)
		{
			if ($caching)
			{
				$cache_str = __CLASS__.'/entry_id/'.$entry_id;
				if ($entry_date = ee()->cache->get($cache_str))
				{
					$this->return_data = $this->_format_entry_date($entry_date, $date_format);
					return;
				}
			}
			
			$query = ee()->db->select('entry_id, url_title, entry_date')
				->from('channel_titles')
				->where('entry_id', $entry_id)
				->limit(1)
				->get();

			foreach ($query->result() as $row)
			{
				$this->return_data = $this->_format_entry_date($row->entry_date, $date_format);
				
				if ($caching)
				{
					// Save for 1 hour (3600 sec)
					ee()->cache->save($cache_str, $row->entry_date, $this->_cache_ttl);
				}
			}

			return;
		}
		else if ($url_title != NULL && $url_title != '')
		{
			if ($caching)
			{
				$cache_str = __CLASS__;
				if ($channel_id != 0)
				{
					$cache_str .= '/channel_id/'.$channel_id;
				}
				$cache_str .= '/url_title/'.$url_title;

				if ($entry_date = ee()->cache->get($cache_str))
				{
					$this->return_data = $this->_format_entry_date($entry_date, $date_format);
					return;
				}
			}

			$query = ee()->db->select('entry_id, url_title, entry_date')
				->from('channel_titles')
				->where('url_title', $url_title);

			if ($channel_id != 0)
			{
				$query->where('channel_id', $channel_id);
			}

			$query = $query->limit(1)
				->get();

			foreach ($query->result() as $row)
			{
				$this->return_data = $this->_format_entry_date($row->entry_date, $date_format);

				if ($caching)
				{
					// Save for 1 hour (3600 sec)
					ee()->cache->save($cache_str, $row->entry_date, $this->_cache_ttl);
				}
			}
		}

	}
	
	private function _format_entry_date($entry_date, $date_format)
	{
		// Entry date is stored as UMT, convert it to local datetime
		return ee()->localize->format_date($date_format, $entry_date);
	}

	// ----------------------------------------
	//  Plugin Usage
	// ----------------------------------------

	// This function describes how the plugin is used.
	//  Make sure and use output buffering

	public static function usage()
	{
		ob_start(); 
		?>

Gets an entry id, a url_title or a full url and tries to fetch the corresponding entry to display its date.


Usage
=====

Parameters:
- entry_id
- url_title
- url
- channel_id
- use_cache
- cache_ttl

Entry id 2:
{exp:hop_fetch_date entry_id="2" date_format="%Y %m %d"}

Entry with url title "first-news" (will retrieve the oldest entry if 2 have the same url title)
{exp:hop_fetch_date url_title="first-news" date_format="%d %m %Y | %h %i %s"}

Entry url title first-news in channel id 1
{exp:hop_fetch_date url_title="first-news" channel_id="1" date_format="%d %m %Y | %h %i %s"}

Full url http://ee.dev/blabla/news/first-news?param=fdfdfd&p=qwqwqw
{exp:hop_fetch_date url="http://ee.dev/blabla/news/first-news?param=fdfdfd&p=qwqwqw"  date_format="%d %m %Y | %h %i %s"}

Full url http://ee.dev/blabla/news/first-news#param=fd.df_d-f in channel id 1
{exp:hop_fetch_date url="http://ee.dev/blabla/news/first-news#param=fd.df_d-f"  date_format="%d %m %Y | %h %i %s" channel_id="1"}


Caching
=======

Results are cached automatically for 1 hour.

Use 'use_cache="no"' parameter to disable it.


		<?php
		$buffer = ob_get_contents();
			
		ob_end_clean(); 

		return $buffer;
	}
	// END

}

/* End of file edit_this.php */
