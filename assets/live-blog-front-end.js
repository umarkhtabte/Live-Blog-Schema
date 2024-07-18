jQuery(document).ready(function ($) {
    function checkForUpdates() {
        const lastUpdateTimestamp = $("p[data-date]:last").attr("data-date");
        const postId = liveBlogData.postId;

        console.log("last_id: " + lastUpdateTimestamp);

        $.ajax({
            url: liveBlogData.ajaxUrl,
            type: "POST",
            data: {
                action: "get_live_blog_updates",
                post_id: postId,
                last_post_id: lastUpdateTimestamp,
            },
            success: function (response) {
                console.log(response);
                if (response.success && response.data && response.data.length > 0) {
                    response.data.forEach(function (update) {
                        var dateObj = new Date(update.dateTime);
                        var formattedDate = dateObj.toLocaleDateString("en-US", {
                            year: "numeric",
                            month: "long",
                            day: "numeric",
                            hour: "2-digit",
                            minute: "2-digit",
                        });

                        // Create a new live-blog entry
                        var newEntry = $(
                            '<div class="wp-block-live-blogging-plugin-live-blog-update live-blog-entry">'
                        )
                            .append(
                                $('<span class="live-blog-timestamp">').text(formattedDate)
                            )
                            .append(
                                $('<p data-date="' + update.dateTime + '">').text(
                                    update.content
                                )
                            );

                        // Append the new entry after the last live-blog entry
                        $(".live-blog-entry").last().after(newEntry);
                    });
                }
            },
        });
    }
    // Use the Heartbeat API
    $(document).on("heartbeat-tick", checkForUpdates);
});