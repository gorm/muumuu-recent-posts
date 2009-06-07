<?php
/*
Plugin Name: Muumuu Recent Posts
Version: 1.01
Plugin URI: http://github.com/vocino/muumuu-recent-posts/
Description: Retrieves a list of the most recent posts in a WordPress MU installation with category support and nice display.
Author: Travis Vocino
Author URI: http://vocino.com/

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/

/* Based on the work of the following cool kids:

Version: 1.0
Update Author: Rich Cavanaugh
Update Author URI: http://github.com/rich/

Version: 0.32
Update Author: MagicHour
Update Author URI: http://wiki.thisblueroom.net/wiki/Wordpress_MU_sitewide_recent_posts_plugin

Version: 0.31
Update Author: Sven Laqua
Update Author URI: http://www.sl-works.de/

Version: 0.01
Update Author: Ron Rennick
Update Author URI: http://atypicalhomeschool.net/



Parameters

	$how_many: How many recent posts are being displayed
	$how_long: Time frame to choose recent posts from (in days)
	$titleOnly: True (only title of post is displayed) OR false (full content is displayed)
	$begin_wrap: Opening HTML
	$end_wrap: Closing HTML

Sample call: muumuu_recent_posts_mu(5, 30, true, '<li>', '</li>', array("news"));
*/

function muumuu_recent_posts($how_many, $how_long, $titleOnly, $begin_wrap, $end_wrap, $categories=array()) {
    global $wpdb;
    $counter = 0;
    
    // get a list of blogs in order of most recent update. show only public and nonarchived/spam/mature/deleted
    $blogs = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs WHERE
        public = '1' AND archived = '0' AND mature = '0' AND spam = '0' AND deleted = '0' AND
        last_updated >= DATE_SUB(CURRENT_DATE(), INTERVAL $how_long DAY)
        ORDER BY last_updated DESC");
        
    if ($blogs) {
        foreach ($blogs as $blog) {
            // we need _posts and _options tables for this to work
            $blogOptionsTable = "wp_".$blog."_options";
            $blogPostsTable = "wp_".$blog."_posts";
            $termsTable = "wp_" . $blog . "_terms";
            $taxonomyTable = "wp_" . $blog . "_term_taxonomy";
            $termRelationshipsTable = "wp_" . $blog . "_term_relationships";
            
            $categoryWhere = '';
            if (!empty($categories)) {
                $catNames = "'" . implode("', '", $categories) . "'";
                $query = "SELECT t.term_id FROM $termsTable AS t INNER JOIN $taxonomyTable AS tt ON tt.term_id = t.term_id INNER JOIN $termRelationshipsTable AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy = 'category' AND t.name in ($catNames) AND tr.object_id = p.id";
                $categoryWhere = "AND EXISTS (" . $query . ")";
            }
            
            $options = $wpdb->get_results("SELECT option_value FROM
                $blogOptionsTable WHERE option_name IN ('siteurl','blogname') 
                ORDER BY option_name DESC");
                // we fetch the title and ID for the latest post
            $thispost = $wpdb->get_results("SELECT p.ID, p.post_title, p.post_content, p.post_author
                FROM $blogPostsTable as p WHERE p.post_status = 'publish' $categoryWhere
                AND p.post_type = 'post' AND p.post_date >= DATE_SUB(CURRENT_DATE(), INTERVAL $how_long DAY)
                ORDER BY p.id DESC LIMIT 0,1");
            // if it is found put it to the output
            if($thispost) {
                // get permalink by ID.  check wp-includes/wpmu-functions.php
                $thispermalink = get_blog_permalink($blog, $thispost[0]->ID);
				$fixedcontent = wpautop($thispost[0]->post_content);
                if ($titleOnly == false) {
					
					echo $begin_wrap.'	
					<div class="title-post">
						<h4><a href="'.$options[0]->option_value.'" title="Dine '.$options[1]->option_value.'">'.$options[1]->option_value.'</a></h4>
					</div>
					<p><a href="'.$thispermalink.'" rel="bookmark">'.$thispost[0]->post_title.'</a></p>
					<a class="more" href="'.$thispermalink.'">More...</a>'.$end_wrap;
                    $counter++;
                    } else {
	
					echo $begin_wrap.'
						<div class="title-post">
							<h4><a href="'.$options[0]->option_value.'" title="Dine '.$options[1]->option_value.'">'.$options[1]->option_value.'</a></h4>
						</div>
						<h2><a href="'.$thispermalink.'" rel="bookmark">'.$thispost[0]->post_title.'</a></h2>
						'.$fixedcontent.'
						<p class="more"><a href="'.$thispermalink.'" rel="bookmark" title="'.$thispost[0]->post_title.'">Continue reading...</a></p>
						<div class="meta">
							<div class="tags">
							</div>
						</div>'.$end_wrap;
	                $counter++;
                    }
            }
            // don't go over the limit
            if($counter >= $how_many) { 
                break; 
            }
        }
    }
}
?>