<?php

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

require_once 'inc/helpers/setup.php';
require_once 'inc/helpers/myignite-image-sync.php';

// Custom code added by Dhairya to fix team member image not displaying on the site
add_action('admin_enqueue_scripts', function() {
    if (get_post_type() !== 'team_member') return;

    wp_add_inline_script('wp-blocks', '
        wp.domReady(() => {
            const { subscribe, dispatch, select } = wp.data;
            let lastValidThumbnail = null;
            let restoreAttempts = 0;

            const forceRestore = () => {
                if (!lastValidThumbnail) return;
                
                console.log("🛡️ Force restoring thumbnail:", lastValidThumbnail);
                dispatch("core/editor").editPost({
                    featured_media: lastValidThumbnail,
                    meta: {
                        _thumbnail_id: lastValidThumbnail
                    }
                });
                restoreAttempts++;
            };

            // Heavy monitoring
            const unsubscribe = subscribe(() => {
                const editor = select("core/editor");
                if (!editor) return;

                const currentFeatured = editor.getEditedPostAttribute("featured_media") || 
                                       editor.getCurrentPost()?.featured_media || 
                                       editor.getEditedPostAttribute("meta")?._thumbnail_id;

                if (currentFeatured && currentFeatured > 0) {
                    lastValidThumbnail = currentFeatured;
                    restoreAttempts = 0;
                } 
                // If wiped → restore aggressively
                else if (lastValidThumbnail && (currentFeatured === null || currentFeatured === 0)) {
                    if (restoreAttempts < 5) {
                        forceRestore();
                    }
                }
            });

            // Initial protection after editor loads
            setTimeout(() => {
                const post = select("core/editor").getCurrentPost();
                if (post?.featured_media > 0) {
                    lastValidThumbnail = post.featured_media;
                } else {
                    // Extra aggressive check
                    setTimeout(forceRestore, 600);
                    setTimeout(forceRestore, 1200);
                }
            }, 1200);

            // Cleanup on unload
            window.addEventListener("beforeunload", () => unsubscribe());
        });
    ', 'after');
});

add_action('save_post_team_member', function($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $featured = absint($_POST['featured_media'] ?? 0);

    if ($featured > 0) {
        set_post_thumbnail($post_id, $featured);
    } else {
        // Restore if wiped
        $existing = get_post_thumbnail_id($post_id);
        if ($existing) {
            set_post_thumbnail($post_id, $existing);
        }
    }
}, 5, 3);