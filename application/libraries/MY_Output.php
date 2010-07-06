<?php

class MY_Output extends CI_Output {
	
	/**
	 * Write a Cache File
	 *
	 * Stock CI method altered to write cache to a mongodb instance as specified in application/config/mongodb.php
	 * Additionally the $GET and $POST arrays are taken into account when caching a page.
	 *
	 * @access	public
	 * @return	void
	 */	
	function _write_cache($output)
	{
		$CI =& get_instance();	
		
		$mongo_server = $CI->config->item('server', 'mongodb');
		$mongo_port = $CI->config->item('port', 'mongodb');
		try {
			$m = new Mongo($mongo_server, $mongo_port);
		} catch (Exception $e) {
			log_message('error', "Unable to connect to mongodb server: ".$mongo_server.":".$mongo_port);
			return;
		}
		
		$mongo_dbname = $CI->config->item('db_name', 'mongodb');
		$mongo_collection = $CI->config->item('collection_name', 'mongodb');
		
		$collection = $m->$mongo_dbname->$mongo_collection;
		
		$cache_key = $CI->config->item('base_url').
					$CI->config->item('index_page').
					$CI->uri->uri_string().
					serialize($_POST).
					serialize($_GET);
		
		$expire = time() + ($this->cache_expiration * 60);
				
		$cache_doc = array('cache_key' => md5($cache_key),
							'content' => $output,
							'expiry' => $expire);


		try {
			$collection->remove( array( 'cache_key' => md5($cache_key) ) );
			$collection->insert( &$cache_doc, true);
			log_message('debug', "MongoDB cache written: ".$cache_doc['_id']);			
		} catch (Exception $e) {
			log_message('debug', "Unable to write to mongodb");
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * Update/serve a cached file
	 *
	 * @access	public
	 * @return	void
	 */	
	function _display_cache(&$CFG, &$URI)
	{
		$CFG->load('mongodb');

		$mongo_server = $CFG->item('server', 'mongodb');
		$mongo_port = $CFG->item('port', 'mongodb');
		
		try {
			$m = new Mongo($mongo_server, $mongo_port);
		} catch (Exception $e) {
			log_message('error', "Unable to connect to mongodb server: ".$mongo_server.":".$mongo_port);
			return;
		}

		$mongo_dbname = $CFG->item('db_name', 'mongodb');
		$mongo_collection = $CFG->item('collection_name', 'mongodb');

		$collection = $m->$mongo_dbname->$mongo_collection;

		$cache_key = $CFG->item('base_url').
					$CFG->item('index_page').
					$URI->uri_string().
					serialize($_POST).
					serialize($_GET);
		
		$cache_doc = $collection->findOne( array( 'cache_key' => md5($cache_key) ) );
		
		if (!$cache_doc) {
			log_message('debug', "No cache document found");
			return FALSE;
		}
		
		
		// Has the file expired? If so we'll delete it.
		if (time() >= $cache_doc['expiry'])
		{ 		
			$collection->remove( array( 'cache_key' => md5($cache_key) ) );
			log_message('debug', "Cache has expired. Document removed");
			return FALSE;
		}

		// Display the cache
		$this->_display($cache_doc['content']);
		log_message('debug', "Cache document is current. Sending it to browser.");		
		return TRUE;
	}
	
}