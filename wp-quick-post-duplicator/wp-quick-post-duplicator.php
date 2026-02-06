<?php
/**
 * WP Quick Post Duplicator
 *
 *
 * @package WP Quick Post Duplicator
 * @version 2.2
 * @since 1.0
 */
/**
 * Plugin Name: WP Quick Post Duplicator
 * Plugin URI:  https://wordpress.org/plugins/wp-quick-post-duplicator/
 * Description: Copy or Duplicate any post types, including pages, taxonomies & custom fields with a single click.
 * Author:      Arul Prasad J
 * Author URI:  https://profiles.wordpress.org/arulprasadj/
 * Version:     2.2
 * Text Domain: wp-quick-post-duplicator
 * Domain Path: /languages
 * License:     GPLv2 or later (license.txt)

 Copyright (C)  2020-2021 arulprasadj
 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU General Public License for more details.
 */
/**
* Class and Function List:
* Function list:
* - apj_duplicate_post_link()
* - apj_duplicate_post_as_a_draft()
* Classes list:
*/
/**
 * Add duplicate link to post/page row actions
 */
function apj_duplicate_post_link( $actions, $post ) {

    if ( ! current_user_can( 'edit_posts' ) ) {
        return $actions;
    }

    if ( $post->post_type !== 'post' && $post->post_type !== 'page' ) {
        return $actions;
    }

    // Only show link if user can edit THIS post
    if ( ! current_user_can( 'edit_post', $post->ID ) ) {
        return $actions;
    }

    $url = admin_url( 'admin.php' );

    // 🔐 Nonce bound to post ID
    $copy_link = wp_nonce_url(
        add_query_arg(
            array(
                'action' => 'apj_duplicate_post_as_a_draft',
                'post'   => $post->ID,
            ),
            $url
        ),
        'wqpd_clone_post_' . $post->ID
    );

    $actions['duplicate'] = '<a href="' . esc_url( $copy_link ) . '" rel="permalink">Duplicate This Item</a>';

    return $actions;
}

/**
 * Attach duplicate link to all post types
 */
$post_types = get_post_types( array(), 'names' );
foreach ( $post_types as $post_type ) {
    add_filter( $post_type . '_row_actions', 'apj_duplicate_post_link', 10, 2 );
}

/**
 * Duplicate post handler (SECURE)
 */
function apj_duplicate_post_as_a_draft() {
    global $wpdb;

    // Must be logged in and able to edit posts
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( "You don't have permission." );
    }

    // Validate request
    if (
        ! isset( $_GET['post'], $_GET['_wpnonce'] ) ||
        ! isset( $_REQUEST['action'] ) ||
        $_REQUEST['action'] !== 'apj_duplicate_post_as_a_draft'
    ) {
        wp_die( 'Invalid request.' );
    }

    $post_id = absint( $_GET['post'] );
    if ( ! $post_id ) {
        wp_die( 'Invalid post ID.' );
    }

    // 🔐 Verify nonce bound to post
    check_admin_referer( 'wqpd_clone_post_' . $post_id );

    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_die( 'Post not found.' );
    }

    /**
     * 🔐 CRITICAL FIX
     * Ensure user is allowed to READ this post
     */
    if ( ! current_user_can( 'read_post', $post_id ) ) {
        wp_die( 'You are not allowed to duplicate this post.' );
    }

    /**
     * Extra protection for private posts
     */
    if ( $post->post_status === 'private' && ! current_user_can( 'edit_post', $post_id ) ) {
        wp_die( 'You are not allowed to duplicate private posts.' );
    }

    $current_user = wp_get_current_user();

    $args = array(
        'comment_status' => $post->comment_status,
        'ping_status'    => $post->ping_status,
        'post_author'    => $current_user->ID,
        'post_content'   => $post->post_content,
        'post_excerpt'   => $post->post_excerpt,
        'post_name'      => $post->post_name,
        'post_parent'    => $post->post_parent,
        'post_password'  => $post->post_password,
        'post_status'    => 'draft',
        'post_title'     => $post->post_title,
        'post_type'      => $post->post_type,
        'to_ping'        => $post->to_ping,
        'menu_order'     => $post->menu_order,
    );

    $new_post_id = wp_insert_post( $args );

    if ( is_wp_error( $new_post_id ) ) {
        wp_die( 'Failed to create duplicate post.' );
    }

    // Copy taxonomies
    $taxonomies = get_object_taxonomies( $post->post_type );
    foreach ( $taxonomies as $taxonomy ) {
        $terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
        wp_set_object_terms( $new_post_id, $terms, $taxonomy, false );
    }

    // Copy post meta (safe method)
    $post_meta_infos = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d",
            $post_id
        )
    );

    if ( $post_meta_infos ) {
        foreach ( $post_meta_infos as $meta_info ) {
            add_post_meta(
                $new_post_id,
                $meta_info->meta_key,
                maybe_unserialize( $meta_info->meta_value )
            );
        }
    }

    wp_redirect( admin_url( 'edit.php?post_type=' . $post->post_type ) );
    exit;
}

add_action( 'admin_action_apj_duplicate_post_as_a_draft', 'apj_duplicate_post_as_a_draft' );

function PluginRowMeta($links_array, $plugin_file_name)
{

    if (strpos($plugin_file_name, 'wp-quick-post-duplicator.php')) $links_array = array_merge($links_array, array(
        '<a target="_blank" href="https://paypal.me/arulprasadj?locale.x=en_GB"><span style="font-size: 20px; height: 20px; width: 20px;" class="dashicons dashicons-heart"></span>Donate</a>'
    ));

    return $links_array;
}

add_filter("plugin_row_meta", 'PluginRowMeta' , 1, 2);
?>
