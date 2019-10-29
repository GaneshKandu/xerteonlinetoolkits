<?php
/**
 * Licensed to The Apereo Foundation under one or more contributor license
 * agreements. See the NOTICE file distributed with this work for
 * additional information regarding copyright ownership.

 * The Apereo Foundation licenses this file to you under the Apache License,
 * Version 2.0 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.

 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
 
/**
 * 
 * peer page, allows for the peer review of a template
 *
 * @author Patrick Lockley
 * @version 1.0
 * @package
 */

require_once(dirname(__FILE__) . "/config.php");

_load_language_file("/peer.inc");

require $xerte_toolkits_site->php_library_path . "display_library.php";

/**
 *  Check the template ID is a number
 */

if(empty($_SESSION['template_id']) || !is_numeric($_SESSION['template_id'])) {
    die("Invalid template id");
}

$template_id = (int) $_SESSION['template_id'];

$query_to_check_peer = "select * from " . $xerte_toolkits_site->database_table_prefix . "additional_sharing where sharing_type=\"peer\" and template_id=\"" . $template_id . "\"";

$query_for_peer_response = db_query_one("SELECT * FROM {$xerte_toolkits_site->database_table_prefix}additional_sharing WHERE sharing_type = ? AND template_id = ?", array('peer', $template_id));

/**
 *  The number of rows being not equal to 0, indicates peer review has been set up.
 */

if(!empty($query_for_peer_response)) {

    $query_for_play_content = "select otd.template_name, otd.parent_template, ld.username, otd.template_framework, tr.user_id, tr.folder, tr.template_id, td.access_to_whom, td.extra_flags";
    $query_for_play_content .= " from " . $xerte_toolkits_site->database_table_prefix . "originaltemplatesdetails otd, " . $xerte_toolkits_site->database_table_prefix . "templaterights tr, " . $xerte_toolkits_site->database_table_prefix . "templatedetails td, " . $xerte_toolkits_site->database_table_prefix . "logindetails ld";
    $query_for_play_content .= " where td.template_type_id = otd.template_type_id and td.creator_id = ld.login_id and tr.template_id = td.template_id and tr.template_id=" . $template_id .  " and (role='creator' or role='co-author')";

    $row_play = db_query_one($query_for_play_content);


    /**
     *  Peer review needs a password, so check if anything has been posted
     */
    require $xerte_toolkits_site->php_library_path . "screen_size_library.php";
    require $xerte_toolkits_site->root_file_path . "modules/" . $row_play['template_framework'] . "/peer.php";

    show_peer_template($row_play);
}else{
    dont_show_template();
}
