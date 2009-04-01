<?php

/*

PHP Image Host
www.phpace.com/php-image-host

Copyright (c) 2004,2008,2009 Sam Yapp

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
class plansAction extends Action
{
    function run()
    {
        $sql = "SELECT * FROM account_types  ";
        $res = $this->app->query($sql, 'Get Account Types');

        $errors = '';

        if( mysql_num_rows($res)<2 ) die("Error retrieving account details from database.");

        $plans = array();
        while( $p = mysql_fetch_object($res) ) $plans[$p->type_type] = $p;

        if( $this->app->getParamStr('update') != '' ){

            $this->app->config->home_page_show_plans = $this->app->getParamInt('home_page_show_plans');
            $this->app->config->upgrade_show_plans = $this->app->getParamInt('upgrade_show_plans');
            $this->app->savesettings(array('home_page_show_plans','upgrade_show_plans'));

            $ints = array('max_images', 'max_upload_size', 'max_image_width',
                            'max_image_height','bandwidth', 'storage','email_friends',
                            'auto_jpeg', 'auto_resize', 'jpeg_quality','add_branding',
                            'simultaneous_uploads', 'images_per_page', 'max_galleries',
                            'rename_images', 'resize_images', 'rotate_images',
                            'allow_zip_uploads', 'zip_uploads_max_images',
                            'zip_uploads_max_size');
            foreach( $ints as $i ){
                $n1 = $i.'1';
                $n2 = $i.'2';
                $plans['free']->$i = $this->app->getParamInt($n1);
                $plans['paid']->$i = $this->app->getParamInt($n2);
            }
            $plans['free']->type_name = $this->app->getParamStr('type_name1', 'Free');
            $plans['paid']->type_name = $this->app->getParamStr('type_name2', 'Paid');
            $plans['paid']->cost_1 = $this->app->getParamDouble('cost_1', 4.95);
            $plans['paid']->cost_3 = $this->app->getParamDouble('cost_3', 4.95);
            $plans['paid']->cost_6 = $this->app->getParamDouble('cost_6', 4.95);
            $plans['paid']->cost_12 = $this->app->getParamDouble('cost_12', 4.95);
            if( $plans['free']->type_name == '' || $plans['paid']->type_name == '' ){
                $errors = '<div class="errors">You must enter a name for each plan.</div>';
            }else{
                $sql = "UPDATE account_types SET type_name='".mysql_real_escape_string($plans['free']->type_name)."' ";
                foreach( $ints as $i ){
                    $sql .= ','.$i.'='.$plans['free']->$i.' ';
                }
                $sql.= "WHERE type_type='free' ";
                $res = $this->app->query($sql, 'Update Free Plan');

                $sql = "UPDATE account_types SET type_name='".mysql_real_escape_string($plans['paid']->type_name)."', ";
                $sql .="cost_1='".$plans['paid']->cost_1."', ";
                $sql .="cost_3='".$plans['paid']->cost_3."', ";
                $sql .="cost_6='".$plans['paid']->cost_6."', ";
                $sql .="cost_12='".$plans['paid']->cost_12."' ";
                foreach( $ints as $i ){
                    $sql .= ','.$i.'='.$plans['paid']->$i.' ';
                }
                $sql.= "WHERE type_type='paid' ";
                $res = $this->app->query($sql, 'Update Paid Plan');
                $errors =  '<div class="errors">Plan Specifications Updated</div>';

                if( !isset($users) ){
                    $users = $this->app->loadClass('users');
                }
                $users->canceluseroverbandwidth();
            }
        }
        foreach( array('plans','errors') as $var ) {
            $this->theme->assign($var, $$var);
        }
    }
}