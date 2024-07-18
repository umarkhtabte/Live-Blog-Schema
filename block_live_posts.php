<?php

/**
 * Plugin Name: Live Blogging Plugin
 * Description: Adds live-blogging functionality to your WordPress site.
 * Version: 1.0
 * Author: Umar Khtab
 */
function enqueue_admin_script()
{
    // Enqueue the script only on the admin dashboard
    if (is_admin()) {
        wp_enqueue_script('live-blog-cat', plugin_dir_url(__FILE__) . 'js/liveblogcat.js', array('jquery'), null, true);
    }
}

add_action('admin_enqueue_scripts', 'enqueue_admin_script');
function mytheme_enqueue_editor_scripts()
{
    wp_enqueue_script(
        'live-blog-block',
        plugins_url('assets/block_live_posts.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-components', 'wp-editor')
    );
    wp_enqueue_style(
        'gutenberg-custom-styles',
        plugins_url('assets/css/block_live_posts.css', __FILE__),
        array('wp-edit-blocks'),
        filemtime(plugin_dir_path(__FILE__) . 'css/block_live_posts.css')
    );
}

add_action('enqueue_block_editor_assets', 'mytheme_enqueue_editor_scripts');

function mytheme_enqueue_frontend_scripts()
{
    global $post;
    wp_enqueue_style(
        'live-blog-admin-css',
        plugins_url('assets/css/block_live_front_end.css', __FILE__)
    );

    if (is_admin()) {
        wp_enqueue_style(
            'live-blog-admin-css',
            plugins_url('assets/css/block_live_posts.css', __FILE__)
        );
    }


    wp_enqueue_script(
        'live-blog-front-end',
        plugins_url('assets/live-blog-front-end.js', __FILE__),
        array('jquery', 'heartbeat')
    );

    wp_localize_script(
        'live-blog-front-end',
        'liveBlogData',
        array(
            'postId' => $post->ID,
            'ajaxUrl' => admin_url('admin-ajax.php')
        )
    );
}

add_action('wp_enqueue_scripts', 'mytheme_enqueue_frontend_scripts');


function get_live_blog_updates()
{
    $post_id = $_POST['post_id'];
    $last_post_id = $_POST['last_post_id'];
    $meta_values = get_post_meta($post_id, '_liveblog_meta_boxes', true);

    $updates = [];
    if (!empty($meta_values) && is_array($meta_values)) {

        foreach ($meta_values as $meta_box) {

            if ($meta_box['_additional_datetime'] != "" && $meta_box['_liveblog_heading'] != "" && $meta_box['_liveblog_content'] != "") {
                $dateTime = $meta_box['_additional_datetime'];
                $heading = $meta_box['_liveblog_heading'];
                $content = $meta_box['_liveblog_content'];
                if ($dateTime > $last_post_id) {
                    $updates[] = array('dateTime' => $dateTime, 'heading' => $heading, 'content' => $content);
                }
            }
        }
    }
    wp_send_json_success($updates);
}

add_action('wp_ajax_get_live_blog_updates', 'get_live_blog_updates');
add_action('wp_ajax_nopriv_get_live_blog_updates', 'get_live_blog_updates');

function add_live_blog_posting_schema_markup()
{
    if (is_single()) {
        $post_id = get_the_ID();
        $meta_values = get_post_meta($post_id, '_liveblog_meta_boxes', true);
        $live_blog_updates = array();

        if (!empty($meta_values) && is_array($meta_values)) {
            // Sort live blog updates by datetime in descending order
            usort($meta_values, function ($a, $b) {
                return strtotime($b['_additional_datetime']) - strtotime($a['_additional_datetime']);
            });

            // Get the date of the most recent live blog
            $most_recent_date = !empty($meta_values[0]['_additional_datetime']) ? $meta_values[0]['_additional_datetime'] : null;
            $most_recent_dateformat = date('Y-m-d H:i', strtotime($most_recent_date));

            foreach ($meta_values as $meta_box) {
                // Check if coverage start and end time are not empty
                if (!empty($meta_box['_coverage_start_time']) && !empty($meta_box['_coverage_end_time'])) {
                    $coverageStartTime = $meta_box['_coverage_start_time'];
                    $coverageEndTime = $meta_box['_coverage_end_time'];
                    $formattedcoverageStartTime = date('Y-m-d H:i', strtotime($coverageStartTime));
                    $formattedcoverageEndTime = date('Y-m-d H:i', strtotime($coverageEndTime));
                }

                // Check if additional datetime, liveblog heading, and content are not empty
                if (!empty($meta_box['_additional_datetime']) && !empty($meta_box['_liveblog_heading']) && !empty($meta_box['_liveblog_content'])) {
                    $dateTime = $meta_box['_additional_datetime'];
                    $heading = $meta_box['_liveblog_heading'];
                    $content = $meta_box['_liveblog_content'];
                    $live_blog_updates[] = array(
                        'dateTime' => $dateTime,
                        'heading' => $heading,
                        'content' => $content,
                    );
                }
            }
        }

        if (!empty($live_blog_updates)) {
            $schema_data = array(
                "@context"           => "http://schema.org",
                "@type"              => "LiveBlogPosting",
                "headline"           => get_the_title(),
                "datePublished"      => get_the_date('c'),
                "author"             => array(
                    "@type" => "Person",
                    "name"  => get_the_author(),
                ),
                "coverageStartTime"  => $formattedcoverageStartTime,
                "coverageEndTime"    => $formattedcoverageEndTime,
                "dateModified"       => $most_recent_dateformat,
                "liveBlogUpdate"     => array(),
            );

            foreach ($live_blog_updates as $update) {
                $schema_data["liveBlogUpdate"][] = array(
                    "@type"         => "BlogPosting",
                    "datePublished" => date('Y-m-d H:i', strtotime($update['dateTime'])),
                    "headline"      => $update['heading'],
                    "articleBody"   => strip_tags($update['content']),
                );
            }

            // Use wp_json_encode to handle JSON encoding and escaping
            $json_ld_markup = '<script type="application/ld+json">' . wp_json_encode($schema_data) . '</script>';
            echo $json_ld_markup;
        }
    }
}

add_action('wp_head', 'add_live_blog_posting_schema_markup');

function embed_social_media_urls($content) {
    $content = preg_replace_callback('/https?:\/\/twitter\.com\/(\w+)\/status\/(\d+)(?:\?.*)?(?![^<]*>)/', 'embed_oembed', $content);
    $content = preg_replace_callback('/https?:\/\/www\.instagram\.com\/p\/([a-zA-Z0-9_-]+)(?![^<]*<\/a>)/', 'embed_oembed', $content);
    $content = preg_replace_callback('/https?:\/\/(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]+)(?![^<]*<\/a>)/', 'embed_youtube', $content);
    $content = preg_replace_callback('/https?:\/\/(?:www\.)?facebook\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)([a-zA-Z0-9_-]+)(?![^<]*<\/a>)/', 'embed_oembed', $content);
    $content = preg_replace_callback('/https?:\/\/(?:www\.)?reddit\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)([a-zA-Z0-9_-]+)(?![^<]*<\/a>)/', 'embed_oembed', $content);
    $content = preg_replace_callback('/https?:\/\/(?:www\.)?tiktok\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)([a-zA-Z0-9_-]+)(?:\S*?[?&][^"\s>]+)?(?![^<]*>)/', 'embed_oembed', $content);
    return $content;
}

function embed_oembed($matches) {
    $url = esc_url($matches[0]);
    $oembed = wp_oembed_get($url);
    return "<div class='embedded text_embed_content'>" . $oembed . "</div>";
}

function embed_youtube($match) {

    $youtube_url = esc_url($match[0]);
    preg_match('/\?v=([^&]+)/', $youtube_url, $m);
    if (isset($m[1])) {
        $videoId = $m[1];
        return "<figure class='wp-block-embed wp-embed-aspect-16-9 wp-has-aspect-ratio  is-type-video is-provider-youtube wp-block-embed-youtube'>
            <div class='wp-block-embed__wrapper video-seo-youtube-embed-wrapper'>
                <div class='video-seo-youtube-player' data-id='".$videoId."'>
                    <div class='video-seo-youtube-embed-loader' data-id='".$videoId."' tabindex='0' role='button' aria-label='Load YouTube video'>
                        <picture class='video-seo-youtube-picture'>
                            <source class='video-seo-source-to-maybe-replace' media='(min-width: 801px)' srcset='https://i.ytimg.com/vi/".$videoId."/maxresdefault.jpg'>
                            <source class='video-seo-source-hq' media='(max-width: 800px)' srcset='https://i.ytimg.com/vi/".$videoId."/hqdefault.jpg'>
                            <img onload='videoSEOMaybeReplaceMaxResSourceWithHqSource( event );' src='https://i.ytimg.com/vi/".$videoId."/hqdefault.jpg' width='480' height='360' loading='eager' alt=''>
                        </picture>
                        <div class='video-seo-youtube-player-play'></div>
                    </div>
                </div>
            </div>
        </figure>";

    } else {
        $url = esc_url($match[0]);
        $oembed = wp_oembed_get($url);
        return "<div class='embedded text_embed_content'>" . $oembed . "</div>";
    }
}

// add_filter('the_content', 'append_liveblog_meta_to_content');
function append_liveblog_meta_to_content($content)
{
    if (is_single()) {
        $post_id = get_the_ID();
        $meta_values = get_post_meta($post_id, '_liveblog_meta_boxes', true);
        if (!empty($meta_values) && is_array($meta_values)) {
            usort($meta_values, function ($a, $b) {
                return strtotime($b['_additional_datetime']) - strtotime($a['_additional_datetime']);
            });

            // Get the date of the most recent live blog
            $most_recent_date = !empty($meta_values[0]['_additional_datetime']) ? $meta_values[0]['_additional_datetime'] : null;
            foreach ($meta_values as $meta_box) {
                if ($meta_box['_additional_datetime'] != "" || $meta_box['_liveblog_heading'] != "" || $meta_box['_liveblog_content'] != "") {
                    date_default_timezone_set('America/Chicago'); // CST time zone
                    $publishedDateTime = new DateTime($meta_box['_additional_datetime']);
                    $currentDateTime = new DateTime();
                    // Calculate the interval between the current time and the published time
                    $interval = $currentDateTime->diff($publishedDateTime);
                    // Extract the relevant components (days, hours, minutes)
                    $days = $interval->d;
                    $hours = $interval->h;
                    $minutes = $interval->i;
                    // Construct the appropriate message based on the time elapsed
                    if ($days > 0) {
                        $elapsedTime = $days == 1 ? "$days day ago" : "$days days ago";
                    } elseif ($hours > 0) {
                        $elapsedTime = $hours == 1 ? "$hours hour ago" : "$hours hours ago";
                    } else {
                        $elapsedTime = "$minutes minutes ago";
                    }

                    // Get the time of the post published in 12-hour format
                    $publishedTime = $publishedDateTime->format('g:i A'); // Format as H:MM AM/PM

                    // Get the time zone abbreviation of the post published
                    $publishedTimeZone = $publishedDateTime->format('T'); // Get the timezone abbreviation

                    // If the time zone is "America/Chicago," replace it with "CST"
                    if ($publishedTimeZone == "CST") {
                        $publishedTimeZone = "CST";
                    }

                    $formatdate = $elapsedTime . " / " . $publishedTime . " " . $publishedTimeZone;

                    // Check for Twitter, Instagram, and YouTube URLs and embed them
                    $embedded_content = embed_social_media_urls($meta_box['_liveblog_content']);

                    $meta_output = '<hr style=" height: 2px; border:0; background-color: red; opacity: 1;">';
                    $meta_output .= '<p class="live-blog-timestamp" style="color:red;">' . $formatdate . '</p>';
                    $meta_output .= '<h2>' . esc_html($meta_box['_liveblog_heading']) . '</h2>';
                    $meta_output .= '<div class="live-blog-content">' . $embedded_content . '</div>';

                    // Add the media content
                    $media_content = $meta_box['_media_content'];
                    if (!empty($media_content)) {
                        $meta_output .= '<div class="media-content">' . wp_kses_post($media_content) . '</div>';
                    }

                    // Append the meta output to the content
                    $content .= $meta_output;
                }
            }
            if($meta_values[0]['_additional_datetime'] != "" || $meta_values[0]['_liveblog_heading'] != "" || $meta_values[0]['_coverage_start_time'] != ""){

            // Updated time of article
            $formatted_date_title = date('F j, Y, g:i a', strtotime($most_recent_date));
            $publishedDateTime1 = new DateTime($meta_values[0]['_additional_datetime']);
            $currentDateTime1 = new DateTime();
            // Calculate the interval between the current time and the published time
            $interval1 = $currentDateTime1->diff($publishedDateTime1);
            // Extract the relevant components (days, hours, minutes)
            $days1 = $interval1->d;
            $hours1 = $interval1->h;
            $minutes1 = $interval1->i;
            // Construct the appropriate message based on the time elapsed
            if ($days1 > 0) {
                $elapsedTime1 = $days1 == 1 ? "Updated $days1 d ago" : "Updated $days1 d ago";
            } elseif ($hours1 > 0) {
                $elapsedTime1 = $hours1 == 1 ? "Updated $hours1 h ago" : "Updated $hours1 h ago";
            } else {
                $elapsedTime1 = "Updated $minutes1 m ago";
            }

            $formatdate1 = $elapsedTime1 ;

            ?>
                <script>
                    function updatedatepost(){
                        // Select the first time tag with the specified class
                        var $firstTimeTag = jQuery('.entry-date:first');
                        // Check if the time tag is found
                        if ($firstTimeTag.length > 0) {
                            // Change the datetime attribute
                            $firstTimeTag.attr('datetime', '<?php echo $most_recent_date; ?>');

                            // Change the title attribute
                            $firstTimeTag.attr('title', '<?php echo $formatted_date_title; ?>');
                            $firstTimeTag.text('<?php echo $formatdate1; ?>');
                        }
                    }
                    setTimeout(function() {
                        updatedatepost();
                    }, 4000);
                    setInterval(function() {
                        updatedatepost();
                    }, 1000);
                </script>
            <?php
        }
    }

    }

    return $content;
}


add_filter('the_content', 'append_liveblog_meta_to_content');


function add_liveblog_meta_box()
{
    add_meta_box(
        'liveblog_meta_box',
        'Live Blog Setting',
        'liveblog_meta_box_meta_box_html',
        'post',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_liveblog_meta_box');

function liveblog_meta_box_meta_box_html($post)
{
    $meta_values = get_post_meta($post->ID, '_liveblog_meta_boxes', true);
?>
    <div class="liveblog-meta-box-container">
        <?php
        if (!empty($meta_values) && is_array($meta_values)) {
            $first_meta_box = reset($meta_values);
            render_coverage_times($first_meta_box);

            foreach ($meta_values as $index => $meta_box) {
                // if ($index !== 0) {
                render_liveblog_meta_box($index, $meta_box, false); // Pass false to not show "Coverage Start Time" and "Coverage End Time"
                // }
            }
        } else {
            // If no meta boxes exist, render an empty one.
            render_liveblog_meta_box(0, array(), true); // Pass true to show "Coverage Start Time" and "Coverage End Time"
        }
        ?>
    </div>
    <button type="button" class="button" id="add-liveblog-meta-box" style="background: rgb(0 128 0 / 10%);color: green;border-color: green;">Add New Update</button>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var container = document.querySelector('.liveblog-meta-box-container');
            var addButton = document.getElementById('add-liveblog-meta-box');

            addButton.addEventListener('click', function() {
                var index = container.children.length;
                var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';

                var data = {
                    action: 'add_liveblog_meta_box',
                    index: index,
                    post_id: <?php echo $post->ID; ?>,
                    nonce: '<?php echo wp_create_nonce('liveblog_add_meta_box_nonce'); ?>'
                };

                // Send an AJAX request to create a new meta box
                jQuery.post(ajaxUrl, data, function(response) {
                    container.insertAdjacentHTML('beforeend', response);

                    // Refresh the TinyMCE editor for the new content field
                    tinymce.execCommand('mceRemoveEditor', true, 'liveblog-content-' + index);
                    tinymce.execCommand('mceAddEditor', true, 'liveblog-content-' + index);
                    setTimeout(() => {
                        console.log("add new");
                        var iframes = document.querySelectorAll('iframe');
                        iframes.forEach(function(iframe) {
                            var iframeDocument = iframe.contentWindow.document;
                            var iframeBody = iframeDocument.body;
                            iframeBody.style.maxWidth = '100%';
                        });
                    }, 3000)
                    // Ensure that subsequent meta boxes don't show Coverage Times
                    var newMetaBox = container.lastElementChild;
                    var coverageTimes = newMetaBox.querySelector('.liveblog-coverage-times');
                    if (coverageTimes) {
                        coverageTimes.style.display = 'none';
                    }
                });
            });

            container.addEventListener('click', function(event) {
                var target = event.target;
                if (target.classList.contains('remove-meta-box')) {
                    target.closest('.liveblog-single-meta-box').remove();
                }
            });

            // Hide Coverage Times for existing meta boxes on page load
            var existingMetaBoxes = document.querySelectorAll('.liveblog-coverage-times');
            existingMetaBoxes.forEach(function(coverageTimes) {
                coverageTimes.style.display = 'none';
            });
        });
    </script>

<?php
}

function render_coverage_times($meta_box)
{
    $start_time_value = isset($meta_box['_coverage_start_time']) ? esc_attr($meta_box['_coverage_start_time']) : '';
    $end_time_value = isset($meta_box['_coverage_end_time']) ? esc_attr($meta_box['_coverage_end_time']) : '';
?>
    <label style="font-size: 16px;" for="coverage-start-time-0" style="display:block; margin-bottom: 8px;">Coverage Start Time</label>
    <input type="datetime-local" id="coverage-start-time-0" name="liveblog-meta-boxes[0][_coverage_start_time]" value="<?php echo $start_time_value; ?>">
    <br><br>
    <label style="font-size: 16px;" for="coverage-end-time-0" style="display:block; margin-bottom :8px;">Coverage End Time</label>
    <input type="datetime-local" id="coverage-end-time-0" name="liveblog-meta-boxes[0][_coverage_end_time]" value="<?php echo $end_time_value; ?>">
    <br><br>
<?php
}


function render_liveblog_meta_box($index, $meta_box, $showCoverageTimes = true)
{
    $liveblog_heading = isset($meta_box['_liveblog_heading']) ? esc_attr($meta_box['_liveblog_heading']) : '';
    $liveblog_content = isset($meta_box['_liveblog_content']) ? $meta_box['_liveblog_content'] : '';
    $additional_datetime = isset($meta_box['_additional_datetime']) ? esc_attr($meta_box['_additional_datetime']) : '';
    $start_time_value = isset($meta_box['_coverage_start_time']) ? esc_attr($meta_box['_coverage_start_time']) : '';
    $end_time_value = isset($meta_box['_coverage_end_time']) ? esc_attr($meta_box['_coverage_end_time']) : '';

?>
    <div class="liveblog-single-meta-box">
        <?php
        if ($showCoverageTimes) {
        ?>
            <div class="liveblog-coverage-times">
                <label style="font-size: 16px;" for="coverage-start-time-<?php echo $index; ?>" style="display:block; margin-bottom: 8px;">Coverage Start Time</label>
                <input type="datetime-local" id="coverage-start-time-<?php echo $index; ?>" name="liveblog-meta-boxes[<?php echo $index; ?>][_coverage_start_time]" value="<?php echo $start_time_value; ?>">
                <br><br>
                <label style="font-size: 16px;" for="coverage-end-time-<?php echo $index; ?>" style="display:block; margin-bottom :8px;">Coverage End Time</label>
                <input type="datetime-local" id="coverage-end-time-<?php echo $index; ?>" name="liveblog-meta-boxes[<?php echo $index; ?>][_coverage_end_time]" value="<?php echo $end_time_value; ?>">
                <br><br>
            </div>
        <?php
        }
        ?>
        <br><br><br><br>
        <label style="font-size: 16px;" for="liveblog-heading-<?php echo $index; ?>">Heading</label>
        <input type="text" style="display: block;width: 100%;height: 40px;" id="liveblog-heading-<?php echo $index; ?>" name="liveblog-meta-boxes[<?php echo $index; ?>][_liveblog_heading]" value="<?php echo $liveblog_heading; ?>">
        <br><br>
        <label style="font-size: 16px;" for="liveblog-content-<?php echo $index; ?>">Content</label>
        <?php
        $content_editor_id = 'liveblog-content-' . $index;
        $content_editor_settings = array(
            'textarea_name' => "liveblog-meta-boxes[{$index}][_liveblog_content]",
            'textarea_rows' => 8,
            'tinymce' => array(
                'extended_valid_elements' => 'a[href|target=_blank],div[class|id|style]',
            ),
        );
        wp_editor($liveblog_content, $content_editor_id, $content_editor_settings);
        ?>
        <br><br>
        <label style="font-size: 16px;" for="additional-datetime-<?php echo $index; ?>">Publish Datetime</label>
        <input type="datetime-local" id="additional-datetime-<?php echo $index; ?>" name="liveblog-meta-boxes[<?php echo $index; ?>][_additional_datetime]" value="<?php echo $additional_datetime; ?>">
        <br><br>
        <?php
        // Display "Coverage Start Time" and "Coverage End Time" only for the first meta box
        if ($showCoverageTimes) {
        ?>
            <br><br>
            <label style="font-size: 16px;" for="coverage-start-time-<?php echo $index; ?>" style="display:block; margin-bottom: 8px;">Coverage Start Time</label>
            <input type="datetime-local" id="coverage-start-time-<?php echo $index; ?>" name="liveblog-meta-boxes[<?php echo $index; ?>][_coverage_start_time]" value="<?php echo $start_time_value; ?>">
            <br><br>
            <label style="font-size: 16px;" for="coverage-end-time-<?php echo $index; ?>" style="display:block; margin-bottom :8px;">Coverage End Time</label>
            <input type="datetime-local" id="coverage-end-time-<?php echo $index; ?>" name="liveblog-meta-boxes[<?php echo $index; ?>][_coverage_end_time]" value="<?php echo $end_time_value; ?>">
            <br><br>
        <?php
        }
        ?>
        <button type="button" class="button remove-meta-box" style="float: right; background: rgba(255,0,0,0.1); color: red; border-color: red;">Remove Update</button>
    </div>
<?php
}


function save_liveblog_meta_boxes($post_id)
{
    if (array_key_exists('liveblog-meta-boxes', $_POST)) {
        $meta_boxes = $_POST['liveblog-meta-boxes'];

        // Sanitize and save other meta box data
        update_post_meta($post_id, '_liveblog_meta_boxes', $meta_boxes);

        // Handle media attachments
        foreach ($meta_boxes as $meta_box) {
            $media_content = isset($meta_box['_media_content']) ? $meta_box['_media_content'] : '';

            // Check if media content is present
            if (!empty($media_content)) {
                // Insert the media content as attachment
                $attachment_id = media_handle_sideload(array('name' => basename($media_content), 'tmp_name' => $media_content), $post_id);

                // If attachment was successful, associate it with the post
                if (!is_wp_error($attachment_id)) {
                    update_post_meta($post_id, '_thumbnail_id', $attachment_id);
                }
            }
        }
    }
}

add_action('save_post', 'save_liveblog_meta_boxes');

// AJAX handler to add a new meta box
function add_liveblog_meta_box_callback()
{
    check_ajax_referer('liveblog_add_meta_box_nonce', 'nonce');

    $index = $_POST['index'];
    $meta_box = array(
        '_liveblog_heading' => '',
        '_liveblog_content' => '',
        '_additional_datetime' => '',
    );

    // Display only "Heading" and "Content" fields for new meta boxes
    render_liveblog_meta_box($index, $meta_box, false);

    wp_die();
}

add_action('wp_ajax_add_liveblog_meta_box', 'add_liveblog_meta_box_callback');
?>
