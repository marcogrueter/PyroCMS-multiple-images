<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Upload Multiple Images Field Type,
 *
 * @author		Rigo B Castro
 * @author      	Jose Fonseca
 * @author		Ben Rogmans
 * @link		https://github.com/benrogmans/PyroCMS-multiple-images
 */

class Field_multiple_images {

    public $field_type_name = 'Multiple images';
    public $field_type_slug = 'multiple_images';
    public $alt_process = true;
    public $db_col_type = false;
    public $custom_parameters = array('folder', 'max_limit_images');
    public $version = '1.1.1';
    public $author = array('name' => 'Ben Rogmans/Gijs-Jan Roof', 'url' => 'http://rogmansmedia.nl');

    private $_table_name = 'default_multiple_images';
    private $_file_id_column = 'image';
    private $_resource_id_column = 'streamitem';

    // --------------------------------------------------------------------------

    /**
     * Run time cache
     */
    private $cache;

    // --------------------------------------------------------------------------

	public function __construct() {
		$this->CI =& get_instance();
		
	}

    public function event($field) {
		
    }
    
    public function ajax_upload() {
	    //streams_core/public_ajax/field/multiple_images/upload

		$this->CI->load->library('files/files');

		$allowed_extensions = 'jpg|png';
	    
	    $result = null;
		$input = $this->CI->input->post();

		if($input['replace_id'] > 0)
		{
			$result = Files::replace_file($input['replace_id'], $input['folder_id'], $input['name'], 'file', $input['width'], $input['height'], $input['ratio'], $input['alt_attribute']);
			$result['status'] AND Events::trigger('file_replaced', $result['data']);
		}
		elseif ($input['folder_id'] and $input['name'])
		{
			$result = Files::upload($input['folder_id'], $input['name'], 'file', $input['width'], $input['height'], $input['ratio'], null, $input['alt_attribute']);
			$result['status'] AND Events::trigger('file_uploaded', $result['data']);
		}else{
			$result['status'] = 'error';
			$result['message'] = 'Upload Error';
		}

		echo json_encode($result);	
    }

    /**
     * Output form input
     *
     * @param	array
     * @param	array
     * @return	string
     */
    public function form_output($data, $entry_id, $field)
    {	

        $this->CI->load->library('files/files');

        $this->_clean_files($field);

        $upload_url = site_url('streams_core/public_ajax/field/multiple_images/upload');

        $data = array(
            'multipart_params' => array(
                $this->CI->security->get_csrf_token_name() => $this->CI->security->get_csrf_hash(),
                'folder_id' => $field->field_data['folder'],
                ),
            'upload_url' => $upload_url,
            'is_new' => empty($entry_id),
            'max_files' => $field->field_data['max_limit_images']
            );

        if (!empty($entry_id))
        {
            $table_data = $this->_table_data($field);
            $images_out = array();

            $this->CI->db->join('files as F', "F.id = {$table_data->table}.{$table_data->file_id_column}");

            $images = $this->CI->db->order_by('F.sort', 'ASC')->get_where($table_data->table, array(
                $table_data->table .'.'. $this->_resource_id_column => $entry_id
                ))->result();

            if (!empty($images))
            {

                foreach ($images as $image)
                {
                    $images_out[] = array(
                        'id' => $image->{$table_data->file_id_column},
                        'name' => $image->name,
                        'url' => str_replace('{{ url:site }}', base_url(), $image->path),
                        'is_new' => false
                        );
                }

                $data['images'] = $images_out;
            }
        }
        $data['field_slug'] = $field->field_slug;

        return $this->CI->type->load_view('multiple_images', 'plupload_js', $data);
    }

    // --------------------------------------------------------------------------

    /**
     * User Field Type Query Build Hook
     *
     * This joins our user fields.
     *
     * @access 	public
     * @param 	array 	&$sql 	The sql array to add to.
     * @param 	obj 	$field 	The field obj
     * @param 	obj 	$stream The stream object
     * @return 	void
     */
    public function query_build_hook(&$sql, $field, $stream)
    {
        $sql['select'][] = $this->CI->db->protect_identifiers($stream->stream_prefix . $stream->stream_slug . '.id', true) . "as `" . $field->field_slug . "||{$this->_resource_id_column}`";
    }

    // --------------------------------------------------------------------------

    public function pre_save($images, $field, $stream, $row_id, $data_form)
    {

        $table_data = $this->_table_data($field);
        $table = $table_data->table;
        $resource_id_column = $table_data->resource_id_column;
        $file_id_column = $table_data->file_id_column;
        $max_limit_images = (int) $field->field_data['max_limit_images'];

        if (!empty($max_limit_images))
        {
            if (count($images) > $max_limit_images)
            {
                $this->CI->session->set_flashdata('notice', sprintf(lang('streams:multiple_images.max_limit_error'), $max_limit_images));
            }
        }

        if ($this->CI->db->table_exists($table))
        {

            // Reset
            if ($this->CI->db->delete($table, array($resource_id_column => (int) $row_id)))
            {


				$count = 1;
                // Insert new images
                foreach ($images as $file_id)
                {

                    $check = !empty($max_limit_images) ? $count <= $max_limit_images : true;

                    if ($check)
                    {
                        if (!$this->CI->db->insert($table, array(
                            $resource_id_column => $row_id,
                            $file_id_column => $file_id
                            )))
                        {
                            $this->CI->session->set_flashdata('error', 'Error saving new images');
                            return false;
                        }

                    }

                    $count++;
                }
            }

        }
    }

    // --------------------------------------------------------------------------


    /**
     * Pre Ouput
     *
     * Process before outputting on the CP. Since
     * there is less need for performance on the back end,
     * this is accomplished via just grabbing the title column
     * and the id and displaying a link (ie, no joins here).
     *
     * @access	public
     * @param	array 	$input
     * @return	mixed 	null or string
     */
    public function alt_pre_output($row_id, $params, $field_type, $stream)
    {


	}

    /**
     * Pre Ouput
     *
     * Process before outputting on the CP. Since
     * there is less need for performance on the back end,
     * this is accomplished via just grabbing the title column
     * and the id and displaying a link (ie, no joins here).
     *
     * @access	public
     * @param	array 	$input
     * @return	mixed 	null or string
     */
    public function pre_output($input, $data)
    {
        if (!$input)
            return null;


        $stream = $this->CI->streams_m->get_stream($data['choose_stream']);

        $title_column = $stream->title_column;

        // -------------------------------------
        // Data Checks
        // -------------------------------------
        // Make sure the table exists still. If it was deleted we don't want to
        // have everything go to hell.
        if (!$this->CI->db->table_exists($stream->stream_prefix . $stream->stream_slug))
        {
            return null;
        }

        // We need to make sure the select is NOT NULL.
        // So, if we have no title column, let's use the id
        if (trim($title_column) == '')
        {
            $title_column = 'id';
        }

        // -------------------------------------
        // Get the entry
        // -------------------------------------

        $row = $this->CI->db
        ->select()
        ->where('id', $input)
        ->get($stream->stream_prefix . $stream->stream_slug)
        ->row_array();

        if ($this->CI->uri->segment(1) == 'admin')
        {
            if (isset($data['link_uri']) and !empty($data['link_uri']))
            {
                return '<a href="' . site_url(str_replace(array('-id-', '-stream-'), array($row['id'], $stream->stream_slug), $data['link_uri'])) . '">' . $row[$title_column] . '</a>';
            }
            else
            {
                return '<a href="' . site_url('admin/streams/entries/view/' . $stream->id . '/' . $row['id']) . '">' . $row[$title_column] . '</a>';
            }
        }
        else
        {
            return $row;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Pre Ouput Plugin
     *
     * This takes the data from the join array
     * and formats it using the row parser.
     *
     * @access	public
     * @param	array 	$row 		the row data from the join
     * @param	array  	$custom 	custom field data
     * @param	mixed 	null or formatted array
     */
    public function pre_output_plugin($row, $custom)
    {

        $table = $custom['field_data']['table_name'];

        if (empty($table))
        {
            $table = "{$custom['stream_slug']}_{$custom['field_slug']}";
        }

        $file_id_column = $this->_file_id_column;


        $images = $this->CI->db->where($this->_resource_id_column, (int) $row[$this->_resource_id_column])->get($table)->result_array();
        $return = array();
        if (!empty($images))
        {
            foreach ($images as &$image)
            {
                $this->CI->load->library('files/files');
                $file_id = $image[$file_id_column];
                $file = Files::get_file($file_id);
                $image_data = array();
                if ($file['status'])
                {
                    $image = $file['data'];

                    // If we don't have a path variable, we must have an
                    // older style image, so let's create a local file path.
                    if (!$image->path)
                    {
                        $image_data['image'] = base_url($this->CI->config->item('files:path') . $image->filename);
                    }
                    else
                    {
                        $image_data['image'] = str_replace('{{ url:site }}', base_url(), $image->path);
                    }

                    // For <img> tags only
                    $alt = $this->obvious_alt($image);

                    $image_data['filename'] = $image->filename;
                    $image_data['name'] = $image->name;
                    $image_data['alt'] = $image->alt_attribute;
                    $image_data['description'] = $image->description;
                    $image_data['img'] = img(array('alt' => $alt, 'src' => $image_data['image']));
                    $image_data['ext'] = $image->extension;
                    $image_data['mimetype'] = $image->mimetype;
                    $image_data['width'] = $image->width;
                    $image_data['height'] = $image->height;
                    $image_data['id'] = $image->id;
                    $image_data['filesize'] = $image->filesize;
                    $image_data['download_count'] = $image->download_count;
                    $image_data['date_added'] = $image->date_added;
                    $image_data['folder_id'] = $image->folder_id;
                    $image_data['folder_name'] = $image->folder_name;
                    $image_data['folder_slug'] = $image->folder_slug;
                    $image_data['thumb'] = site_url('files/thumb/' . $file_id);
                    $image_data['thumb_img'] = img(array('alt' => $alt, 'src' => site_url('files/thumb/' . $file_id)));
                }

                $return[] = $image_data;
            }
        }
        
        
        
        return $return;
    }

    // ----------------------------------------------------------------------

    /**
     * Choose a folder to upload to.
     *
     * @access	public
     * @param	[string - value]
     * @return	string
     */
    public function param_folder($value = null)
    {
        // Get the folders
        $this->CI->load->model('files/file_folders_m');

        $tree = $this->CI->file_folders_m->get_folders();

        $tree = (array) $tree;

        if (!$tree)
        {
            return '<em>' . lang('streams:file.folder_notice') . '</em>';
        }

        $choices = array();

        foreach ($tree as $tree_item)
        {
            // We are doing this to be backwards compat
            // with PyroStreams 1.1 and below where
            // This is an array, not an object
            $tree_item = (object) $tree_item;

            $choices[$tree_item->id] = $tree_item->name;
        }

        return form_dropdown('folder', $choices, $value);
    }

    // --------------------------------------------------------------------------
    // --------------------------------------------------------------------------


    /**
     * Data for choice. In x : X format or just X format
     *
     * @access	public
     * @param	[string - value]
     * @return	string
     */
    public function param_max_limit_images($value = null)
    {

        return form_input(array(
            'name' => 'max_limit_images',
            'value' => !empty($value) ? $value : 5,
            'type' => 'text'
            ));
    }

    // ----------------------------------------------------------------------

    /**
     * Obvious alt attribute for <img> tags only
     *
     * @access	private
     * @param	obj
     * @return	string
     */
    private function obvious_alt($image)
    {
        if ($image->alt_attribute)
        {
            return $image->alt_attribute;
        }
        if ($image->description)
        {
            return $image->description;
        }
        return $image->name;
    }

    // ----------------------------------------------------------------------

    private function _table_data($field)
    {
        return (object) array(
            'table' => $this->_table_name,
            'resource_id_column' => $this->_resource_id_column,
            'file_id_column' => $this->_file_id_column
            );
    }

    // ----------------------------------------------------------------------

    private function _clean_files($field)
    {

        $table_data = $this->_table_data($field);

        $content = Files::folder_contents($field->field_data['folder']);
        $files = $content['data']['file'];
        $valid_files = $this->CI->db->select($table_data->file_id_column . ' as id')->from($table_data->table)->get()->result();
        $valid_files_ids = array();

        if (!empty($valid_files))
        {
            foreach ($valid_files as $vf)
            {
                array_push($valid_files_ids, $vf->id);
            }
        }

        if (!empty($files))
        {
            foreach ($files as $file)
            {
                if (!in_array($file->id, $valid_files_ids))
                {
                    Files::delete_file($file->id);
                }
            }
        }
    }


	public function field_assignment_construct($field, $stream) {

		$query = "CREATE TABLE IF NOT EXISTS `".$this->_table_name."` (
		  `id` int(9) NOT NULL AUTO_INCREMENT,
		  `created` datetime DEFAULT NULL,
		  `updated` datetime DEFAULT NULL,
		  `created_by` int(11) DEFAULT NULL,
		  `ordering_count` int(11) DEFAULT NULL,
		  `image` char(60) COLLATE utf8_unicode_ci DEFAULT NULL,
		  `streamitem` int(11) DEFAULT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

		$this->CI->db->query($query);

	}

	public function field_assignment_destruct($field, $stream) {

		$this->CI->dbforge->drop_table($this->_table_name);

	}

}
