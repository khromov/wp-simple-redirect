<?php
/*
 * Plugin Name: Simple Redirect
 * Version: 3.0.1
 * Description: Easily redirect any post or page to another page with a dropdown menu or by manually typing in a URL. This plugin also changes permalinks and menus to point directly to the new location of the redirect - this prevents bots from getting a redirect and helps boost your SEO.
 * Author: Get on Social
 * Author URI: http://www.getonsocial.com/?simpleredirect
 * License: GPL v3

*/


class GosSimpleRedirect
{


		var $namespace = 'gos_simple_redirect';
		var $title = 'Simple Redirect';
		var $postTypes = array();
		var $context = 'side';
		var $priority = 'default';

		function __construct()
		{

			$this->postTypes = get_post_types();
			add_action( 'load-post.php', array($this,'load_post') );
			add_action( 'load-post-new.php', array($this,'load_post') );

			add_action( 'admin_footer-post-new.php', array($this,'footerjs'), 9999 );
			add_action( 'admin_footer-post.php',     array($this,'footerjs'), 9999 );


			add_action( 'wp_ajax_'.$this->namespace, array($this,'wp_ajax'), 9999 );
			add_action('template_redirect',array($this,'template_redirect'), 10 );


			add_filter('post_link',array($this,'post_link'),20,2);
			add_filter('post_type_link',array($this,'post_link'),20,2);

			add_filter('wp_nav_menu_objects',array($this,'wp_nav_menu_objects'));


		}


		function wp_nav_menu_objects($nav)
		{
			foreach($nav as &$item)
			{


				if(!empty($item->type) && $item->type == 'post_type' && !empty($item->object_id))
				{

					$redirect_info = $this->get_redirect_info($item->object_id);
					if(!empty($redirect_info['link']))
					{
						$item->url = $redirect_info['link'];
					}

				}
			}
			return $nav;
		}


		function post_link($link)
		{

			global $post;


			if(!empty($post->ID))
			{

				$redirect_info = $this->get_redirect_info($post->ID);

				if(!empty($redirect_info['link']))
				{
					return $redirect_info['link'];
				}


			}

			return $link;

		}


		function template_redirect()
		{
			global $post;
			if(is_singular() && !empty($post->ID))
			{
				$redirect_info = $this->get_redirect_info($post->ID);
				if(!empty($redirect_info['link']))
				{
					header( 'HTTP/1.1 301 Moved Permanently' );
					header( 'Location: '.$redirect_info['link'] );
					die();
				}
			}
		}

		function get_redirect_info($post_id)
		{

			$redirect_info = array();
			$redirect = get_post_meta( $post_id, $this->namespace, true );
			if(!empty($redirect['type']))
			{
				switch($redirect['type'])
				{
					case(1):
						if(!empty($redirect['postid']))
						{
							$permalink = get_permalink( $redirect['postid'] );
							if($permalink)
							{
								$redirect_info['link'] = $permalink;
								$redirect_info['code'] = 301;
							}
						}

					break;
					case(2):
						if(!empty($redirect['url']))
						{
							$redirect_info['link'] = $redirect['url'];
							$redirect_info['code'] = 301;
						}
					break;
				}
			}
			return $redirect_info;
		}


		function wp_ajax()
		{
			header('Content-type: application/json; charset=utf-8');
			$href = !empty($_POST['href'])?$_POST['href']:'';
			$post_id = 0;
			if($href)
			{
				$post_id = url_to_postid($href);
			}
			echo json_encode(array('post_id'=>$post_id));
			die();
		}


		function load_post()
		{
			add_action( 'add_meta_boxes', array($this,'add_meta_boxes') );
			add_action( 'save_post', array($this,'save_post'), 10, 2 );

		}


		function add_meta_boxes()
		{

			foreach($this->postTypes as $postType)
			{
				add_meta_box(
					$this->namespace,
					$this->title,
					array($this,'show_meta_box'),
					$postType, //context
					$this->context,
					$this->priority
				);
			}

		}


		function save_post( $post_id, $post )
		{


			// check nonce before proceeding
			if ( ! isset( $_POST[ $this->namespace.'_nonce' ] ) || ! wp_verify_nonce( $_POST[ $this->namespace.'_nonce' ], $this->namespace.'_save' ) ) return $post_id;


			if(!empty($_POST[$this->namespace]['type']))
			{
				update_post_meta( $post_id, $this->namespace, $_POST[$this->namespace] );
			}
			else
			{
				delete_post_meta( $post_id, $this->namespace );
			}



		}


		function show_meta_box( $object, $box )
		{


			wp_nonce_field( $this->namespace.'_save', $this->namespace.'_nonce' );

			$data = get_post_meta( $object->ID, $this->namespace, true );
			$selected = !empty($data['type'])?$data['type']:'';
			$title = !empty($data['title'])?$data['title']:'';
			$postid = !empty($data['postid'])?$data['postid']:'';
			$url = !empty($data['url'])?$data['url']:'';


			$options = array(

'0'=>'Disabled / No Redirect',
'1'=>'Existing Page or Post',
'2'=>'Custom URL',

			);

			?>

			<label style="display:block; margin-bottom:.5em;">Redirect this page to:</label>

		<div class="<?php echo $this->namespace; ?>-selector">
			<select class="widefat" style="margin-bottom:.5em;" name="<?php echo $this->namespace; ?>[type]">
				<?php

				foreach($options as $k=>$option)
				{
					echo '<option '.($selected==$k ?'selected="selected"':'').' value="'.$k.'">'.htmlspecialchars($option,ENT_QUOTES).'</option>';
				}


				?>
			</select>
		</div>

			<div class="<?php echo $this->namespace; ?>-properties">
				<input type="hidden" value="<?php echo htmlspecialchars($postid, ENT_QUOTES); ?>" name="<?php echo $this->namespace; ?>[postid]" class="postid" />
				<input style="margin-bottom:.25em; <?php echo $selected==2?'display:none':''; ?>" type="text" readonly="readonly" value="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>" name="<?php echo $this->namespace; ?>[title]" class="title widefat" />
				<input style="margin-bottom:.25em; <?php echo $selected==1?'display:none':''; ?>" type="text" readonly="readonly" value="<?php echo htmlspecialchars($url, ENT_QUOTES); ?>" name="<?php echo $this->namespace; ?>[url]" class="url widefat" placeholder="Enter Custom URL" />
				<textarea style="display:none;" id="<?php echo $this->namespace; ?>-textarea"></textarea>
			</div>


<br />
			<?php



		}


		function footerjs()
		{



			?>


				<script>


					( function( $ ) {

					    if ( typeof wpLink == 'undefined' )
					    {
								return;
					    }

				      if ( typeof myOpenHelper !== 'undefined' )
				      {
								return;
				      }

					   wpLink.myOpenHelper = wpLink.open;
					   wpLink.myUpdateHelper = wpLink.update;


					    wpLink.open = function(editor)
					    {
					    	if(editor == '<?php echo $this->namespace; ?>-textarea')
					    	{
					    		wpActiveEditor = editor;
						    	jQuery('#internal-toggle').hide();
						    	jQuery('#link-options').hide();
						    	jQuery('#search-panel').show();
						    	setTimeout(function(){
						    		jQuery('#search-field').focus();
						    	},250);



					    	}

					    	wpLink.myOpenHelper();

					    }

					    wpLink.mySearchOnly = function()
					    {
					    	jQuery('#internal-toggle').hide();
					    	jQuery('#link-options').hide();

					    }


					    wpLink.update = function()
					    {



								if( wpActiveEditor == '<?php echo $this->namespace; ?>-textarea' )
								{

									var attr = wpLink.getAttrs();

									if( ! attr.href ){ return; }


									var data = {
										action : '<?php echo $this->namespace; ?>',
										href   : attr.href
									}

									$.post( ajaxurl, data, function(r)
									{
										var $p = jQuery('.<?php echo $this->namespace; ?>-properties');
										$p.find('input.postid').val(r.post_id);
										$p.find('input.url').val(attr.href);
										$p.find('input.title').val(attr.title);
										$p.find('input.title').show();
										wpLink.close();
										setTimeout(function(){
											wpLink.close();
										},250);
									});

									return false;
								}
								else
								{

						    	wpLink.myUpdateHelper();
								}


					    }



					} )( jQuery );


					jQuery('#wp-link').bind('wpdialogclose',function(){

										var $ = jQuery;
						    		jQuery('#link-options').show();
							    	jQuery('#internal-toggle').show();

										var $p = jQuery('.<?php echo $this->namespace; ?>-properties');
							    	var $select = jQuery('.<?php echo $this->namespace; ?>-selector select');

							    	if( ! $p.find('input.postid').val() && ! $p.find('input.url').val() )
							    	{
							    		$select.val(0);
							    		$select.trigger('change');
							    	}




					});

						jQuery(document).on('change myload','.<?php echo $this->namespace; ?>-selector select',function(e){

							var $ = jQuery;
							var myval = parseInt($(this).val());
							var $p = jQuery('.<?php echo $this->namespace; ?>-properties');


							$p.find('.title').hide();
							$p.find('.url').hide();

							switch(myval)
							{
								case 0:
								$p.find('input.postid').val('');
								$p.find('input.url').val('');
								$p.find('input.title').val('');
								break;
								case 1:
								$p.show();
								$p.find('input:text').attr('readonly',true);
								if(e.type == 'change')
								{

									$p.find('input.postid').val('');
									$p.find('input.url').val('');
									$p.find('input.title').val('');

									wpLink.open('<?php echo $this->namespace; ?>-textarea');
								}
								if(e.type == 'myload')
								{
									$p.find('.title').show();
								}

								break;
								case 2:
								$p.find('.url').show();
								$p.find('input.title').val('');
								$p.find('input.postid').val('');
								$p.show();
								$p.find('input:text').attr('readonly',false);
								break;
							}



					});

					jQuery('.<?php echo $this->namespace; ?>-selector select').trigger('myload');


				</script>


			<?php
		}



}


global $gosSimpleRedirect;
$gosSimpleRedirect = new GosSimpleRedirect();

