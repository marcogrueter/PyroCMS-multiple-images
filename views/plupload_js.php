<link href="//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.min.css" rel="stylesheet" />
<link href="<?=site_url('streams_core/field_asset/css/multiple_images/bootstrap-3.1.1.css');?>" type="text/css" rel="stylesheet" />
<link href="<?=site_url('streams_core/field_asset/css/multiple_images/style.css');?>" type="text/css" rel="stylesheet" />

<script type="text/javascript" src="<?=site_url('streams_core/field_asset/js/multiple_images/browserplus-min.js');?>"></script>
<script type="text/javascript" src="<?=site_url('streams_core/field_asset/js/multiple_images/plupload.full.min.js');?>"></script>
<script type="text/javascript" src="<?=site_url('addons/shared_addons/field_types/multiple_images/js/i18n/nl.js');?>"></script>
<script type="text/javascript" src="<?=site_url('streams_core/field_asset/js/multiple_images/mustache.js');?>"></script>

<div id="upload-container">
	
    <div id="drop-target">
        <div class="drop-area" style="display: none;">
            <span><?php echo lang('streams:multiple_images.help_draganddrop') ?></span>
            <span style="display: none;"><?php echo lang('streams:multiple_images.drop_images_here') ?></span>
        </div>
        <div class="no-drop-area" style="display: none;">
            <a href="#" class="btn blue"><?php echo lang('streams:multiple_images.select_files'); ?></a>
        </div>
    </div>
</div>

<div id="filelist" class="row-fluid">
</div>

<div style="clear: both"></div> 
 
<script type="text/javascript">

var $images_list = $('#filelist'),
    entry_is_new = <?=json_encode($is_new); ?>,
    max_files = <?=$max_files;?>,
    images = <?=json_encode($images); ?>,
    file_count = <?=(!empty($images))?count($images):0;?>,
    show_error = false,
    uploading = false;
    
var uploader = new plupload.Uploader({
    runtimes : 'html5,flash,silverlight,html4',
    browse_button : 'drop-target', // you can pass in id...
    drop_element : 'drop-target',
    container : document.getElementById('upload-container'),
    url : <?=json_encode($upload_url);?>,
    headers: { 'X-Requested-With': 'XMLHttpRequest'},
    filters : {
        max_file_size : '<?= Settings::get('files_upload_limit') ?>mb',
        mime_types: [
            {title : "Image files", extensions : "jpg,png"}
        ]
    },
    resize: {quality: 90},
    multipart: true,
    multipart_params: <?php echo json_encode($multipart_params); ?>,
    // Flash settings
    flash_swf_url : '/plupload/js/Moxie.swf',
    // Silverlight settings
    silverlight_xap_url : '/plupload/js/Moxie.xap',
	
    init: {
        PostInit: function() {

            isHTML5 = uploader.runtime === "html5";
			
            if (isHTML5) {
                $('#drop-target').addClass('html5').on({
                    drop: function(e) {
                        var files = e.originalEvent.dataTransfer.files;
                        nativeFiles = files;

                        return $(this).removeClass('dragenter').find('.drop-area span:last').hide().prev().show();
                    }
                });

                $('body').on({
                    dragenter: function() {
                        return $('#drop-target').addClass('dragenter').find('.drop-area span:first').hide().next().show();
                    },
                    dragleave: function() {
                        return $('#drop-target').removeClass('dragenter').find('.drop-area span:last').hide().prev().show();
                    }
                });

                $('.drop-area').show();
            } else {
                $('.no-drop-area').show();
            }
        },
		
		QueueChanged: function(up) {
			uploading = true;
		},
		
        FilesAdded: function(up, files) {
 
			$.each(files, function(i, file) {
				if(file_count < max_files){
					add_image_pre({id: file.id, name: file.name});
                	file_count++;
                	
                }else{
                	uploader.removeFile(file);
                	show_error = true;
                }
			});

			if(show_error && $('.upload-warning').length == false){
				$('#upload-container').before('<div class="alert alert-warning upload-warning"><?=addslashes(sprintf(lang("streams:multiple_images.max_limit_error"), $max_files));?></div>');
				$('.upload-warning').delay(6000).fadeOut(100);
				setTimeout(function() {
				    $('.upload-warning').remove();
				}, 6100);		
			}
			uploader.start();
        },
 
        UploadProgress: function(up, file) {
          
        },
        
        FileUploaded: function(up, file, info) {

            if(info.response == false){ 
            	$('#file-'+file.id).remove();
            	file_count--;
            }else{
	            var response = JSON.parse(info.response);
				if(response.status == true) {
					var path = response.data.path.replace("\{\{ url:site \}\}", "<?=base_url()?>");
					path = path.replace('large/', 'thumb/')+'/600/430/fit';
					add_image(file.id, {url: path, id: response.data.id, name: response.data.name});
				}else{
					$('#file-'+file.id).remove();
					file_count--;
				}
            }
        },
        
        UploadComplete: function(up, files){
	        uploading = false;
	        //remove loaders with error
	        $('.loading-img').remove();
        },
        
        BeforeUpload: function(up, file) {
            //disable next btn
        },
        Error: function(up, err) {
        	console.log(file_count);
        	$('#upload-container').before('<div class="alert alert-warning upload-warning2">'+err.message+'</div>');
			$('.upload-warning2').delay(6000).fadeOut(100);
			setTimeout(function() {
			    $('.upload-warning').remove();
			}, 6100);
        }
    }
});

uploader.init();

/* Private methods */

function add_image(id, data) {
	if($('#file-'+id).length == true) {
    	return $('#file-'+id).replaceWith(Mustache.to_html($('#image-template').html(),(data)));
    }else{
	    return $images_list.append(Mustache.to_html($('#image-template').html(),(data)));
    }
}

function add_image_pre(data) {
    return $images_list.append(Mustache.to_html($('#image-template-pre').html(),(data)));
}
$( document ).ready(function() {
	if (entry_is_new === false && images) {
	    for (var i in images) {
	    	//console.log(images[i]);
	    	images[i].url = images[i].url.replace('large/', 'thumb/')+'/600/430/fit';
	        add_image(images[i].id, images[i]);
	    }
	}
});

$(document).on('click', 'a, input[type="submit"], button[type="submit"]', function(e) {
	if(uploading == true){
		return false;
	}
});

$(document).on('click', '.no-drop-area', function(e) {
	return false;
});

$(document).on('click', '.delete-image', function(e) {
    var $this = $(this),
        file_id = $this.parent().find('input.images-input').val();
		$this.parents('.thumb').fadeOut(function() {
			file_count--;
            return $(this).remove();
        });
    return e.preventDefault();
});

</script>

<script id="image-template" type="text/x-handlebars-template">
    <div id="file-[[id]]" class="col-sm-2 col-xs-6 thumb">
	    <div class="image-preview">
		    <div class="image-link" rel="multiple_images">
		    	<img src="[[url]]" alt="" class="uploaded-image" />
		    </a>
		    <input class="images-input" type="hidden" name="<?=$field_slug ?>[]" value="[[id]]" />
		    <a class="delete-image" href="#"><i class="fa fa-trash-o fa-2x"></i></a>
	    </div>
    </div>
</script>

<script id="image-template-pre" type="text/x-handlebars-template">
    <div id="file-[[id]]" class="col-sm-2 col-xs-6 thumb loading-img">
	    <div class="image-preview">
			<div class="load-icon">
		    	<i class="fa fa-spinner fa-spin fa-2x"></i>
			</div>
		    <input class="images-input" type="hidden" name="<?=$field_slug ?>[]" value="[[id]]" />
		    <a class="delete-image" href="#"><i class="fa-remove fa"></i></a>
	    </div>
    </div>
</script>