
document.addEventListener('DOMContentLoaded', function () {
    var container = document.querySelector('.liveblog-meta-box-container');
    var addButton = document.getElementById('add-liveblog-meta-box');

    addButton.addEventListener('click', function () {
        var index = container.children.length;

        var data = {
            action: 'add_liveblog_meta_box',
            index: index,
            post_id: liveBlogScriptVars.postId,
            nonce: liveBlogScriptVars.nonce
        };

        // Send an AJAX request to create a new meta box
        jQuery.post(liveBlogScriptVars.ajaxUrl, data, function (response) {
            container.insertAdjacentHTML('beforeend', response);

            // Initialize the TinyMCE editor for the new content field
            var newContentEditorId = 'liveblog-content-' + index;
            
            // Remove the previous TinyMCE editor instance
            tinymce.remove('#' + newContentEditorId);

            // Initialize the TinyMCE editor for the new content field
            tinymce.init({
                selector: '#' + newContentEditorId,
                // Add other TinyMCE settings as needed
            });

            // Ensure that subsequent meta boxes don't show Coverage Times
            var newMetaBox = container.lastElementChild;
            var coverageTimes = newMetaBox.querySelector('.liveblog-coverage-times');
            if (coverageTimes) {
                coverageTimes.style.display = 'none';
            }
        });
    });
});
