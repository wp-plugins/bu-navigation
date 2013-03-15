<?php

// modified 10/18 by mgburns
// @todo BEFORE RE-LAUNCH
//	- find usage of this method (TechWeb, etc.)
//	- investigate proper suppress_filters behavior for bu_navigation_get_pages
//	- investigate proper crumb_current / anchor_current behavior

function bu_navigation_breadcrumbs($args = '')
{
	global $post;

	$defaults = array(
		'post' => $post,
		'glue' => '&nbsp;&raquo;&nbsp;',
		'container_tag' => 'div',
		'container_id' => 'breadcrumbs',
		'container_class' => '',
		'anchor_class' => 'crumb',
		'crumb_tag' => '',
		'crumb_current' => 1,
		'anchor_current' => 0,
		'echo' => 0,
		'home' => false,
		'home_label' => 'Home',
		'prefix' => '',
		'suffix' => '',
		'include_statuses' => 'publish',
		'include_hidden' => false,
		'show_links' => true // @todo change to "include_links"
		);
	$r = wp_parse_args($args, $defaults);

	if( $r['post'] ) {
		$p = null;

		if( is_numeric( $r['post'] ) ){
			$p = get_post( $r['post'] );
		} else if( is_object( $r['post'] ) ){
			$p = $r['post'];
		}

		if( is_null( $p ) ) {
			error_log('bu_navigation_breadcrumbs - invalid post argument: ' . $r['post'] );
			return false;
		}
	}

	$attrs = '';

	if ($r['container_id']) $attrs .= sprintf(' id="%s"', $r['container_id']);
	if ($r['container_class']) $attrs .= sprintf(' class="%s"', $r['container_class']);

	$html = sprintf('<%s%s>%s', $r['container_tag'], $attrs, $r['prefix']);

	/* grab ancestors */
	$post_types = ( $p->post_type == 'page' ? array('page', 'link') : array($p->post_type) );
	$ancestors = bu_navigation_gather_sections($p->ID, array( 'post_types' => $post_types ));
	if (!in_array($p->ID, $ancestors)) array_push($ancestors, $p->ID);

//	$front_page = get_option('page_on_front');
//	if ($r['home'] && (!$ancestors[0])) {
//		$ancestors[0] = $front_page;
//	}

	// @todo suppress was misspelled here, which was consequently excluding navigation excluded pages
	// while it looks like this was the intended behavior, it is NOT how it has been operating, so
	// need to investigate the ramifications of this changes

	if( $r['include_hidden'] && has_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude') )
		remove_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');

	$pages = bu_navigation_get_pages(array('pages' => $ancestors, 'post_types' => $post_types, 'post_status' => $r['include_statuses']));

	if( $r['include_hidden'] && has_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude') )
		add_filter('bu_navigation_filter_pages', 'bu_navigation_filter_pages_exclude');

	$crumbs = array(); // array of HTML fragments for each crumb

	if ((is_array($pages)) && (count($pages) > 0))
	{
		foreach ($ancestors as $page_id)
		{
			if (!$page_id && $r['home']) {
				$anchor_open = sprintf('<a href="%s" class="%s">', get_bloginfo('url'), $r['anchor_class'] );
				$anchor_close = '</a>';

				// @todo change to "include_links"
				if( $r['show_links'] ) {
					$crumb = $anchor_open . $r['home_label'] . $anchor_close;
				} else {
					$crumb = $r['home_label'];
				}

				array_push($crumbs, $crumb);
				continue;
			} else if (!array_key_exists($page_id, $pages)) continue;

			$current = $pages[$page_id];

			if (!isset($current->navigation_label))
				$current->navigation_label = apply_filters('the_title', $current->post_title);

			$title = esc_attr($current->navigation_label);

			// commented out 10/18 by mgburns -- wasn't doing anything, as $front_page was never set
			// if ($page_id == $front_page) {
			// 	$title = str_replace('[label]', $title, $r['home_label']);
			// }

			$href = $current->url;
			$classname = $r['anchor_class'];

			$crumb = $anchor_open = $anchor_close = '';


			if ($current->ID == $p->ID) $classname .= ' active';

			if( $r['show_links'] ) {
				if (($current->ID == $p->ID) && (!$r['anchor_current']))
				{
					// @todo ... why is there an anchor here at all?  anchor_current is false
					$anchor_open = sprintf('<a class="%s">', $classname );
				}
				else
				{
					$anchor_open = sprintf('<a href="%s" class="%s">', $href, $classname );
				}
				$anchor_close = '</a>';
			}

			$before_crumb = $after_crumb = '';

			if( $r['crumb_tag'] ) {
				$before_crumb = $current->ID == $p->ID ? sprintf('<%s class="current">', $r['crumb_tag']) : sprintf('<%s>', $r['crumb_tag']);
				$after_crumb = sprintf('</%s>', $r['crumb_tag']);
			}

			$crumb = $before_crumb . $anchor_open . $title . $anchor_close . $after_crumb;

			$crumb = apply_filters('bu_navigation_filter_crumb_html', $crumb, $current, $r);

			/* only crumb if not current page or if we're crumbing the current page */
			if (($current->ID != $p->ID) || ($r['crumb_current']))
				array_push($crumbs, $crumb);
		}

		$html .= implode($r['glue'], $crumbs);
	}

	$html .= sprintf('%s</%s>', $r['suffix'], $r['container_tag']);

	if ($r['echo']) echo $html;

	return $html;
}

/**
 * Returns breadcrumbs to the current page
 * Shortcode handler for 'breadcrumbs' code
 * @param $atts mixed Parameters
 * @return string HTML fragment
 */
function bu_navigation_breadcrumbs_sc($atts)
{
	global $post;

	$defaults = array(
		'glue' => '&nbsp;&raquo;&nbsp;',
		'container_tag' => 'div',
		'container_id' => '',
		'container_class' => '',
		'anchor_class' => 'crumb',
		'crumb_current' => 1,
		'anchor_current' => 0,
		'echo' => 0
		);

	$r = shortcode_atts($defaults, $atts);

	$r['echo'] = 0; // never echo

	$crumbs = bu_navigation_breadcrumbs($r);

	return $crumbs;
}
add_shortcode('breadcrumbs', 'bu_navigation_breadcrumbs_sc');
?>
