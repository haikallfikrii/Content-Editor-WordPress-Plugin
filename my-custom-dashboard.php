<?php
/*
Plugin Name: My Custom Post Dashboard
Plugin URI:  https://example.com/
Description: A custom dashboard for creating and managing posts from the frontend.
Version:     1.0.0
Author:      Muhamad Fikri Haikal
Author URI:  https://caastedu.com/
License:     GPL2
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue styles and scripts
function my_custom_dashboard_enqueue_assets() {
    wp_enqueue_style( 'my-custom-dashboard-style', plugins_url( 'css/my-custom-dashboard.css', __FILE__ ) );
    wp_enqueue_script( 'tailwind-cdn', 'https://cdn.tailwindcss.com', array(), null, true );
    wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4' );

    if ( is_page( 'content-editor-dashboard' ) ) {
        wp_enqueue_media();
    }

    wp_enqueue_script( 'my-custom-dashboard-script', plugins_url( 'js/my-custom-dashboard.js', __FILE__ ), array( 'jquery' ), null, true );

    wp_localize_script( 'my-custom-dashboard-script', 'myDashboardAjax', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'my_featured_image_nonce' ),
    ) );

    // Add custom CSS for scrollbar hiding directly for quick testing.
    // In a production environment, this should ideally be in your my-custom-dashboard.css file.
    $custom_css = '
        .hide-scrollbar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .hide-scrollbar::-webkit-scrollbar {
            display: none;  /* Chrome, Safari, Opera */
        }
    ';
    wp_add_inline_style( 'my-custom-dashboard-style', $custom_css );
}
add_action( 'wp_enqueue_scripts', 'my_custom_dashboard_enqueue_assets' );


// Handle post creation (INI TIDAK BERUBAH SEDIKITPUN)
function my_custom_dashboard_handle_post_submission() {
    if ( isset( $_POST['submit_post_action'] ) && is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'my_post_submission' ) ) {
            wp_die( 'Security check failed. Please refresh the page and try again.' );
        }

        $post_title        = sanitize_text_field( $_POST['post_title'] );
        $post_content      = wp_kses_post( $_POST['post_content'] );
        
        // Ensure post_category is an array and sanitize each integer value
        $post_category     = isset( $_POST['post_category'] ) ? array_map( 'intval', (array) $_POST['post_category'] ) : array();
        
        $featured_image_id = isset( $_POST['featured_image_id'] ) ? intval( $_POST['featured_image_id'] ) : 0;
        $submit_action     = sanitize_text_field( $_POST['submit_post_action'] );

        if ( empty( $post_title ) || empty( $post_content ) ) {
            wp_safe_redirect( add_query_arg( 'status', 'error_empty_fields', wp_get_referer() ) );
            exit;
        }

        $post_status = 'pending';
        if ( $submit_action === 'publish' ) {
            if ( current_user_can( 'publish_posts' ) ) {
                $post_status = 'publish';
            } else {
                $post_status = 'pending';
            }
        } elseif ( $submit_action === 'save_draft' ) {
            $post_status = 'draft';
        } elseif ( $submit_action === 'unpublish' ) {
            $post_status = 'draft';
        }

        $new_post = array(
            'post_title'    => $post_title,
            'post_content'  => $post_content,
            'post_status'   => $post_status,
            'post_type'     => 'post',
            'post_author'   => get_current_user_id(),
            'post_category' => $post_category, // Ini akan terbaca karena kategori berada dalam form
        );

        $post_id = wp_insert_post( $new_post );

        if ( !empty($featured_image_id) && $post_id && !is_wp_error($post_id) ) {
            set_post_thumbnail( $post_id, $featured_image_id );
        }

        if ( is_wp_error( $post_id ) ) {
            error_log( 'Error creating/updating post: ' . $post_id->get_error_message() );
            wp_safe_redirect( add_query_arg( 'status', 'error_db', wp_get_referer() ) );
        } else {
            $redirect_status = 'success';
            if ($post_status === 'publish') {
                $redirect_status = 'published';
            } elseif ($post_status === 'draft') {
                $redirect_status = 'saved_draft';
            } elseif ($post_status === 'pending') {
                $redirect_status = 'pending_review';
            }
            wp_safe_redirect( add_query_arg( 'status', $redirect_status, wp_get_referer() ) );
        }
        exit;
    }
}
add_action( 'init', 'my_custom_dashboard_handle_post_submission' );


// Shortcode for the custom dashboard
function my_custom_dashboard_shortcode() {
    ob_start();

    // --- Login Handling ---
    if ( ! is_user_logged_in() ) {
        $error_message = '';
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['caast_login_submit'] ) ) {
            $creds = array();
            $creds['user_login']    = sanitize_user( $_POST['log'] );
            $creds['user_password'] = $_POST['pwd'];
            $creds['remember']      = isset( $_POST['rememberme'] );

            $user = wp_signon( $creds, false );

            if ( ! is_wp_error( $user ) ) {
                wp_redirect( site_url( '/content-editor-dashboard/' ) );
                exit;
            } else {
                $error_message = $user->get_error_message();
            }
        }
        ?>
        <div class="h-screen overflow-y-auto bg-gray-100 px-4 py-12 flex flex-col items-center justify-start">
            <div class="bg-white shadow-md rounded-none p-6 w-full max-w-lg text-sm">
                <h2 class="text-lg font-semibold text-gray-700 mb-4">Login to Access Content Editor</h2>
                <?php if ( ! empty( $error_message ) ) : ?>
                    <div class="bg-red-100 text-red-700 border border-red-300 rounded-none px-3 py-2 mb-3 text-xs">
                        <?php echo wp_kses_post( $error_message ); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="" class="relative">
                    <div class="mb-4">
                        <label for="log" class="block text-gray-600 mb-1">Username or Email</label>
                        <input type="text" name="log" id="log" class="w-full px-3 py-2 border border-gray-300 rounded-none focus:outline-none focus:ring-1 focus:ring-blue-500 text-sm" required>
                    </div>
                    <div class="mb-4 relative">
                        <label for="pwd" class="block text-gray-600 mb-1">Password</label>
                        <input type="password" name="pwd" id="pwd" class="w-full px-3 py-2 border border-gray-300 rounded-none focus:outline-none focus:ring-1 focus:ring-blue-500 text-sm pr-10" required>
                        <span class="absolute right-3 top-9 transform -translate-y-1/2 cursor-pointer text-gray-500 text-sm" data-password-toggle="pwd">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div class="mb-4">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="rememberme" value="forever" class="form-checkbox text-blue-600 rounded-none">
                            <span class="ml-2 text-sm text-gray-700">Remember Me</span>
                        </label>
                    </div>
                    <button type="submit" name="caast_login_submit" class="w-full bg-black text-white py-2 px-4 rounded-none hover:bg-gray-800 transition text-xs uppercase tracking-wider">Login</button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // --- Dashboard Content (Only if logged in) ---
    global $current_user;
    wp_get_current_user();

    // --- Display status messages ---
    if ( isset( $_GET['status'] ) ) {
        echo '<div class="notice ';
        if ( $_GET['status'] == 'success' || $_GET['status'] == 'published' || $_GET['status'] == 'saved_draft' || $_GET['status'] == 'pending_review' ) {
            echo 'notice-success bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-none relative mb-4';
        } elseif ( $_GET['status'] == 'error_empty_fields' || $_GET['status'] == 'error_db' ) {
            echo 'notice-error bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-none relative mb-4';
        }
        echo ' is-dismissible"><p>';
        if ( $_GET['status'] == 'published' ) {
            echo 'Article successfully published!';
        } elseif ( $_GET['status'] == 'saved_draft' ) {
            echo 'Article saved as draft!';
        } elseif ( $_GET['status'] == 'pending_review' ) {
            echo 'Article submitted for review!';
        } elseif ( $_GET['status'] == 'error_empty_fields' ) {
            echo 'Error: Title and content cannot be empty. Please fill all required fields.';
        } elseif ( $_GET['status'] == 'error_db' ) {
            echo 'An error occurred while submitting the article. Please try again.';
        }
        echo '</p></div>';
    }
    // --- End status messages ---

    // --- START MASKED DASHBOARD LAYOUT ---

    // Top Navbar
    echo '<div class="bg-gray-100 text-[12px] text-gray-600 font-mono flex justify-end gap-9 px-20 py-1 max-w-12xl mx-auto">';
    echo '<span>myEMAIL</span>';
    echo '<span>myCALENDAR</span>';
    echo '<span>myPROJECTS</span>';
    echo '<span>myLEARNING</span>';
    echo '<span>myWORK</span>';
    echo '<a href="' . esc_url( wp_logout_url( site_url( '/content-editor-dashboard/' ) ) ) . '" class="text-gray-600 hover:text-black">Logout</a>';
    echo '</div>';

    // Main Content Area (Left Sidebar + Main Panel + Right Sidebar)
    // Adding min-h-[118vh] to main to ensure it defines a height for its flex children
    // Changed `max-w-7xl` to `w-full` for main to allow more width for the form, and reduced padding.
    echo '<main class="w-full mx-auto flex flex-col md:flex-row gap-2 px-2 py-2 min-h-[118vh]">'; // Set min-height for main container, reduced px, increased width.

    // Left Navbar (Sidebar)
    // Adjusted width to be smaller to give more space to the form
    echo '<aside aria-label="Left admin navigation panel" class="w-[35px] md:w-auto border border-gray-300 rounded-none p-3 text-xs text-gray-700 font-sans bg-white flex-shrink-0 min-h-[118vh] overflow-y-auto hide-scrollbar">'; // Adjusted width, set min-height, added scroll and hide classes
    echo '<div class="flex items-center gap-2 mb-4">';
    echo '<img alt="User avatar placeholder" class="rounded-full w-6 h-6" src="https://storage.googleapis.com/a1aa/image/2284957a-9c5a-4c99-b75c-ed6110b26f73.jpg" />';
    echo '<span class="truncate text-[11px] text-gray-600">' . esc_html( $current_user->user_email ) . '</span>';
    echo '<i class="fas fa-sync-alt cursor-pointer text-gray-400 text-[12px] ml-auto"></i>';
    echo '</div>';
    echo '<nav class="space-y-0.5">';
    echo '<h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Website Content Editor</h2>';
    echo '<a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/author-endpoint/"><i class="fas fa-user text-gray-500"></i> Author Endpoint Page</a>';
    echo '<a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/book-endpoint/"><i class="fas fa-book-open text-gray-500"></i> Book Endpoint Page</a>';
    echo '<a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/video-endpoint/"><span><i class="fab fa-youtube text-gray-500"></i> Video Endpoint Page</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>';
    echo '<div><br/></div>'; // Empty div for spacing
    echo '<a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/"><i class="fas fa-th-large text-gray-500"></i> Dashboard</a>';
    echo '<a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/profile/"><i class="fas fa-user text-gray-500"></i> My Profile</a>';
    echo '<a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/enrolled-courses/"><i class="fas fa-book-open text-gray-500"></i> Enrolled Courses</a>';
    echo '<a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/my-bookings/"><i class="fas fa-calendar-check text-gray-500"></i> My Tutor Bookings</a>';
    echo '<a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/download-certificate/"><i class="fas fa-download text-gray-500"></i> Download Certificates</a>';
    echo '<a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/wishlist/"><i class="far fa-heart text-gray-500"></i> Wishlist</a>';
    echo '<a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/reviews/"><i class="far fa-star text-gray-500"></i> Reviews</a>';
    echo '<a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/purchase-history/"><i class="fas fa-history text-gray-500"></i> Purchase History</a>';
    echo '<a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/my-account/"><span><i class="fas fa-store text-gray-500"></i> Store Dashboard</span></a>';
    echo '<a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/courses/"><span><i class="fas fa-book text-gray-500"></i> Courses</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>';
    echo '<a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/lessons/"><span><i class="fas fa-file-alt text-gray-500"></i> All Lessons</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>';
    echo '<a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/quizzes/"><span><i class="fas fa-question-circle text-gray-500"></i> Quizzes</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>';
    echo '<a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/meeting/"><span><i class="fab fa-youtube text-gray-500"></i> Meetings</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>';
    echo '<a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/tutor-booking/"><span><i class="fas fa-calendar-check text-gray-500"></i> Tutor Bookings</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>';
    echo '<a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/assignments/"><span><i class="fas fa-tasks text-gray-500"></i> Assignments</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>';
    echo '<a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/question-answer/"><span><i class="fas fa-question text-gray-500"></i> Question &amp; Answer</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>';
    echo '<a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/announcements/"><i class="fas fa-bullhorn text-gray-500"></i> Announcements</a>';
    echo '</nav>';
    echo '<div class="mt-4 flex items-center gap-2 text-[11px] text-gray-600 cursor-pointer">';
    echo '<a href="https://caastedu.com/dashboard/settings/" class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px] w-full">';
    echo '<i class="fas fa-cog text-gray-500"></i> Settings';
    echo '</a>';
    echo '</div>';
    echo '</aside>'; // End Left Navbar (Sidebar)

    // Form starts here, now acting as a flex container for the main content and right sidebar
    ?>
    <form id="my-custom-post-form" method="post" enctype="multipart/form-data" class="flex-grow flex flex-col md:flex-row gap-2">
        <?php wp_nonce_field( 'my_post_submission', '_wpnonce' ); ?>

        <div class="flex-grow bg-white border border-gray-300 rounded-none p-2 min-h-[calc(118vh - 40px)]"> <h2 class="text-xl font-semibold mb-4">Content Editor</h2>

            <p class="mb-4">
                <label for="post_title" class="block text-sm font-medium text-gray-700">Article Title:</label>
                <input type="text" name="post_title" id="post_title" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
            </p>

            <p class="mb-4">
                <label for="post_content" class="block text-sm font-medium text-gray-700">Article Content:</label>
                <?php
                wp_editor( '', 'post_content', array(
                    'textarea_name' => 'post_content',
                    'textarea_rows' => 10,
                    'teeny'         => true,
                    'media_buttons' => false,
                    'tinymce'       => array(
                        'height' => 200,
                    ),
                ) );
                ?>
            </p>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Featured Image:</label>
                <div class="mt-1 flex items-center gap-2">
                    <input type="text" id="featured_image_url" name="featured_image_url" class="flex-grow border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Browse featured images" readonly />
                    <input type="hidden" id="featured_image_id" name="featured_image_id" value="" />
                    <button type="button" class="button button-secondary browse-featured-image bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Browse</button>
                </div>
                <div id="featured-image-preview" class="mt-2" style="display:none;">
                    <img src="" alt="Featured Image Preview" style="max-width: 150px; height: auto;" />
                    <button type="button" class="remove-featured-image text-red-500 hover:text-red-700 text-xs mt-1 rounded-none">Remove Image</button>
                </div>
            </div>

            <div class="flex justify-start gap-4 mt-6">
                <button type="submit" name="submit_post_action" value="save_draft" class="px-6 py-2 border border-gray-300 rounded-none shadow-sm text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Save Draft</button>
                <button type="submit" name="submit_post_action" value="review" class="px-6 py-2 border border-gray-300 rounded-none shadow-sm text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Review</button>
                <button type="submit" name="submit_post_action" value="publish" class="px-6 py-2 border border-gray-300 rounded-none shadow-sm text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Publish</button>
                <button type="submit" name="submit_post_action" value="unpublish" class="px-6 py-2 border border-gray-300 rounded-none shadow-sm text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Unpublish</button>
            </div>
        </div>

        <aside aria-label="Right sidebar" class="w-full md:w-48 border border-gray-300 rounded-none p-3 text-xs text-gray-700 font-sans bg-white flex-shrink-0 min-h-[118vh] overflow-y-auto hide-scrollbar">
            <div class="border-t border-gray-200 pt-4">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Categories</h2>
                <div class="relative mb-2">
                    <input type="text" id="category_search" placeholder="Search Categories" class="w-[35px] md:w-auto pl-8 pr-3 py-2 border border-gray-300 rounded-none text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
                <div id="categories-list" class="space-y-2 pr-2">
                    <?php
                    $categories = get_categories( array( 'hide_empty' => 0 ) );
                    if ( ! empty( $categories ) ) {
                        foreach ( $categories as $category ) {
                            echo '<div class="flex items-center category-item">';
                            // TETAP: name="post_category[]" di sini agar PHP bisa langsung membacanya
                            echo '<input type="checkbox" name="post_category[]" id="cat_' . esc_attr( $category->term_id ) . '" value="' . esc_attr( $category->term_id ) . '" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded-none">';
                            echo '<label for="cat_' . esc_attr( $category->term_id ) . '" class="ml-2 text-sm text-gray-900">' . esc_html( $category->name ) . '</label>';
                            echo '</div>';
                        }
                    } else {
                        echo '<p class="text-sm text-gray-500">No categories found.</p>';
                    }
                    ?>
                </div>
            </div>
        </aside>
    </form>
    </main>
    <?php
    // --- END MASKED DASHBOARD LAYOUT ---

    return ob_get_clean();
}
add_shortcode( 'my_post_dashboard', 'my_custom_dashboard_shortcode' );