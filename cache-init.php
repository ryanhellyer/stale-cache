<?php

add_action(
    'init',
    function() {

        /**
         * Register shortcode for cached data display.
         * Usage: [cached_content].
         */
        function cached_content_shortcode($atts) {
            return '<h1 style="margin:50px 0;background: #ff4444;color:#fff;border-radius:40px;padding:10px 30px;border: 1px solid #666;box-shadow: 1px 1px 20px rgba(0,0,0,0.5)"><strong>' .
                StaleCache::get('some-key', [5, 30], function() {return getSomethingExpensive();})
            . '</strong></h1>';
        }
        add_shortcode('cached_content', 'cached_content_shortcode');

        /**
         * Intentionally expensive function to output something.
         */
        function getSomethingExpensive() {
            sleep(5);
            return 'Some expensive data! (' . date('Y-m-d H:i:s') . ')';
        }

    },
    10
);

if (isset($_GET['delete'])) {
    delete_transient('some-key');
    delete_transient('some-key_stale_time');
    delete_transient('some-key_refresh_lock');
    echo 'Transients deleted';
    die;
}
