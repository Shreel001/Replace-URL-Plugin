<?php

/**
 * Plugin Name: Replace URL Plugin
 * Description: Replace matching substring on the fly followed by saving the post.
 */

// Function to replace substrings in content
function replace_substring_on_the_fly($content) {

    global $post;

    // Check if the flag is set in post meta
    $processed_once = get_post_meta($post->ID, '_post_processed_once', true);

    if ($processed_once === 'true') {
        return $content; // Skip processing if already processed
    }

    $search_pattern = '/https?:\/\/catalogue\.library\.(torontomu|ryerson)\.ca\/record=b[0-9]{7}' . '(~S0)?/';

    if(!preg_match($search_pattern, $content, $matches)){
        return $content;
    }

    // Load the JSON file or data from a URL
    $json_url = 'http://159.203.56.66:3000/data/query';  // Example URL to retrieve JSON data
    $response = wp_remote_get($json_url);

    // Check for errors in fetching JSON data
    if (is_wp_error($response)) {
        return $content; // Return original content if there's an error
    }

    $json_body = wp_remote_retrieve_body($response);
    $json_data = json_decode($json_body, true);

    // Check if JSON data is valid
    if ($json_data === null || !is_array($json_data)) {
        return $content; // Return original content if JSON is not valid or empty
    }

    // Iterate through JSON data
    foreach ($json_data as $str1 => $str2) {
        // Extract the bib_id from the string
        $split = explode('=', $str1);
        $bib_id = $split[1];

        // Construct the search pattern
        $search_pattern = '/https?:\/\/catalogue\.library\.(torontomu|ryerson)\.ca\/record=' . preg_quote($bib_id, '/') . '(~S0)?/';
        
        if (preg_match_all($search_pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $original_url = $match[0];
                $replace_url = $str2;

                // Replace the content using str_replace
                $content = str_replace($original_url, $replace_url, $content);
            }
        }
    }

    // Set flag in post meta to indicate content has been processed
    update_post_meta($post->ID, '_post_processed_once', 'true');

    // Return the modified content
    return $content;
}

// Apply the filter to the_content for front-end display
add_filter('the_content', 'replace_substring_on_the_fly');

// Function to replace substrings in content before saving to the database
function replace_links_before_save($data, $postarr) {

    if (isset($data['post_content'])) {
        $data['post_content'] = replace_substring_on_the_fly($data['post_content']);
    }
    return $data;
}

// Apply the filter to wp_insert_post_data for saving post content
add_filter('wp_insert_post_data', 'replace_links_before_save', 10, 2);

?>
