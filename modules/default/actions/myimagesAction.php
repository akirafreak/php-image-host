<?php
/*

PHP Image Host
www.phpace.com/php-image-host

Copyright (c) 2004,2008 Sam Yapp

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

*/

class myimagesAction extends action
{

	var $requireLogin = true;
	
	function run()
	{
		$images = $this->app->loadClass('images');
		$this->app->images->setuser($this->app->userSession->user);
		$users = $this->app->loadClass('users');
		$user = $this->app->userSession->user;
		$config = $this->app->config;
		$message = '';

		if( isset($_REQUEST['setpublic']) ){
			$pub = $this->app->getParamInt('setpublic');
			$i = $this->app->getParamInt('i');
			$images->setPrivacy($i, $user->user_id, $pub);
		}

		// check if a gallery has been selected...
		$gallery = 0;
		$g = $this->app->getParamInt('g', -1);
		if( !isset($user->galleries[$g]) ){
			if( $g > 0 ) $g = 0;
		}else{
			$gallery = $user->galleries[$g];
		}


		// initialize variables used to determine which images to list / order to list them in, which page of images to display, etc.

		$orderby = $this->app->getParamStr('o', 'date');
		$orderdir = $this->app->getParamStr('od','desc');

		if( !in_array($orderby, array('name', 'uploaded') ) ) $orderby = 'uploaded';
		if( !in_array($orderdir, array('asc', 'desc') ) ) $orderdir = 'desc';

		$ids = array();
		$msg = '';
		$emails = array();
		for( $i = 0; $i < $user->email_friends; $i++) $emails[$i] = '';

		$modified = false;

		if( $this->app->getParamStr('delete') != '' ){

			// user wants to delete some of their images

			$ids = isset($_POST['ids']) ? $_POST['ids'] : array();
			$deleted = $images->deleteimages($ids, $user->user_id);
			if( $deleted ){
				$message = $deleted.' image(s) deleted.';
				$user->images -= $deleted;
				$modified = true;
				if( $user->images < 0 ) $user->images = 0;
			}
		}elseif( $this->app->getParamStr('send') != '' ){

			// user wants to email links to their images to their friends..

			$ids = isset($_POST['ids']) ? $_POST['ids'] : array();
			$imgs = $images->getimages(array('ids'=>$ids, 'user_id'=>$userid));
			if( count($imgs) ){
				$emails = array();
				$sentto = array();
				for( $i = 0; $i < $user->email_friends; $i++){
		//			$emails[] = $i;
					if( isset($_POST['to'][$i]) ){
						$email = trim(get_magic_quotes_gpc() == 1 ? stripslashes($_POST['to'][$i]) : $_POST['to'][$i]);
						$emails[$i] = $email;
						if( $email != '' ){
							if( $this->app->validateemail($email) ){
								$sentto[] = $email;
							}else{
								$images->errors[] = 'The address "'.htmlspecialchars($email).'" is not a valid email address.';
							}
						}
					}
				}
				if( count($sentto) > 0 ){
					$msg = $this->app->getParamStr('message');
					$msg = preg_replace('#content-type|bcc:|cc:|to:#i', '', $msg);
					if( strlen($msg) > 255 ){
						$images->errors[] = 'Your message can only contain a maximum of 255 characters. It currently contains '.strlen($msg).'. Please reduce your message length.';
					}elseif( strlen($msg) == 0 ){
						$images->errors[] = 'You must enter a message to send.';
					}else{
						$headers = "From: {$user->email}\r\nReply-To: {$user->email}\r\nErrors-To: {$config->admin_email}\r\nReturn-Path: {$config->admin_email}";
						$tpl = $config->email_images_template;
						$imagelinks = array();
						foreach( $imgs as $i ){
							$imagelinks[] = $i->image_url;
						}
						$s = array('{sitename}', '{siteurl}','{message}', '{imagelinks}');
						$r = array($config->sitename, $config->siteurl, $msg,join("\n\n", $imagelinks));
						$tpl = str_replace($s, $r, $tpl);
						foreach($sentto as $email ){
							@mail($email, $config->email_images_subject, $tpl, $headers);
						}
						$msg = '';
						for( $i = 0; $i < $user->email_friends; $i++) $emails[$i] = '';
						$message = 'An email containing your message and links to your images has been sent to '.join(" and ", $sentto).'.';
					}
				}else{
					$images->errors[] = 'You must enter an email address to send the images to.';
				}
			}else{
				$images->errors[] = 'You need to check the checkboxes next to the images you want to send.';
			}
			if( count($images->errors) > 0 ) $images->errors[] = '<br />Please correct these errors and resubmit the <a href="#email">email form</a>.';
		}elseif( $this->app->getParamStr('addtogallery') != '' ){
			$ids = isset($_POST['ids']) ? $_POST['ids'] : array();
			$added = $images->addtogallery($ids, $this->app->getParamInt('gallery_id'));
			if( count($images->errors) > 0 ){
				$errors= $images->errors;
			}else{
				$message = "$added image(s) have had their gallery changed.";
			}
		}

		if( $modified ) $user = $users->getuser(array('id'=>$user->user_id));

		$criteria = array('user_id'=>$user->user_id);
		if( $gallery ){
			$criteria['galleryid'] = $gallery->gallery_id;
			$criteria['count'] = true;
			$user->images = $images->getimages($criteria);
			$criteria['count'] = false;
		}
		$page = $this->app->getParamInt('p', 1);
		if( $page < 1 ) $page = 1;
		$perpage = $user->images_per_page;
		$totalpages = ceil($user->images / $perpage);
		if( $page > $totalpages ) $page = $totalpages;
		$first = ($page -1 ) * $perpage;
		$last = min($first+$perpage,$user->images);
		$imgs = $images->getimages($criteria, $orderby, $orderdir, $first, $perpage);

		$purl = $this->url('myimages', 'o='.$orderby.'&od='.$orderdir.'&p={page}&g='.$g);

		foreach( array(
				'purl', 'imgs', 'last', 'first', 'page', 'totalpages', 
				'perpage', 'gallery', 'message', 'orderby', 'orderdir',
				'images', 'user', 'config','ids','g','emails','msg'
						)
					as $var_name ) {
			$this->theme->assign($var_name, $$var_name);
		}

	}
}
