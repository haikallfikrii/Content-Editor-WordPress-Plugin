jQuery(document).ready(function($) {
    var mediaUploader;

    // Handle "Browse" button click for Featured Image
    $('.browse-featured-image').on('click', function(e) {
        e.preventDefault();

        // If the uploader already exists, open it
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // Extend the wp.media object
        mediaUploader = wp.media({
            title: 'Select Featured Image',
            button: {
                text: 'Use this image'
            },
            multiple: false // Set to true to allow multiple file selection
        });

        // When a file is selected, grab the URL and ID and set it to the input fields
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#featured_image_url').val(attachment.url);
            $('#featured_image_id').val(attachment.id);
            $('#featured-image-preview img').attr('src', attachment.url);
            $('#featured-image-preview').show();
        });

        // Open the uploader dialog
        mediaUploader.open();
    });

    // Handle "Remove Image" button click for Featured Image
    $('.remove-featured-image').on('click', function(e) {
        e.preventDefault();
        $('#featured_image_url').val('');
        $('#featured_image_id').val('');
        $('#featured-image-preview img').attr('src', '');
        $('#featured-image-preview').hide();
    });

    // --- Category Search Functionality ---
    $('#category_search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('#categories-list .category-item').each(function() {
            var categoryName = $(this).find('label').text().toLowerCase();
            if (categoryName.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Ensure TinyMCE content is saved back to the textarea before submission
    $(document).on('submit', '#my-custom-post-form', function(e) {
        if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
            tinyMCE.activeEditor.save();
        }
        // The console.log lines are for debugging and can be removed in production
        // console.log('Form submission initiated.');
        // var formData = $(this).serializeArray();
        // console.log('Form Data:', formData);
        // console.log('Selected Categories:', $('input[name="post_category[]"]:checked').map(function(){ return $(this).val(); }).get());
    });

    // --- Password Toggle Functionality ---
    $('[data-password-toggle]').on('click', function() {
        const targetId = $(this).data('password-toggle');
        const pwdField = $('#' + targetId);
        const eyeIcon = $(this).find('i');

        if (pwdField.attr('type') === 'password') {
            pwdField.attr('type', 'text');
            eyeIcon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            pwdField.attr('type', 'password');
            eyeIcon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

});