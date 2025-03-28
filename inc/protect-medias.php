<?PHP
    //require_once(dirname($_SERVER["SCRIPT_FILENAME"], 5) . '/wp-load.php');
		require_once('/wp/6/wp-load.php');



    /* We redirect on login page only if plugin is active. Otherwise there's no protection.
    Normally, if plugin is deactivated, RewriteRule in .htaccess file redirection on present file
    is not present, so, checking plugin activation is useless. But, we do this in case of an
    inconsistency somewhere in the Matrix! */
    $epfl_intranet_plugin_full_path = 'epfl-intranet/epfl-intranet.php';
    if (is_plugin_active($epfl_intranet_plugin_full_path) && !is_user_logged_in())
    {
       $upload_dir = wp_upload_dir();
       $file = str_replace('/wp-content/uploads', '', $_SERVER['REQUEST_URI']);
       wp_redirect( wp_login_url( $upload_dir['baseurl'] . $file));
       exit();

    }

    list($basedir) = array_values(array_intersect_key(wp_upload_dir(), array('basedir' => 1)))+array(NULL);

    $file = str_replace('/wp-content/uploads', EPFL_SITE_UPLOADS_DIR, $_SERVER['REQUEST_URI']);
    if (!$basedir || !is_file($file))
    {
       status_header(404);
       die('404 — File not found.');
    }

    $mime = wp_check_filetype($file);
    if( false === $mime[ 'type' ] && function_exists( 'mime_content_type' ) )
       $mime[ 'type' ] = mime_content_type( $file );

    if( $mime[ 'type' ] )
       $mimetype = $mime[ 'type' ];
    else
       $mimetype = 'image/' . substr( $file, strrpos( $file, '.' ) + 1 );


    header( 'Content-Type: ' . $mimetype ); // always send this
    if ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) )
       header( 'Content-Length: ' . filesize( $file ) );

    $last_modified = gmdate( 'D, d M Y H:i:s', filemtime( $file ) );
    $etag = '"'. md5( $last_modified ) . '"';
    header( "Last-Modified: $last_modified GMT" );
    header( 'ETag: ' . $etag );
    header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 100000000 ) . ' GMT' );

    // Support for Conditional GET
    $client_etag = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;

    if( ! isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) )
    $_SERVER['HTTP_IF_MODIFIED_SINCE'] = false;

    $client_last_modified = trim( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
    // If string is empty, return 0. If not, attempt to parse into a timestamp
    $client_modified_timestamp = $client_last_modified ? strtotime( $client_last_modified ) : 0;

    // Make a timestamp for our most recent modification…
    $modified_timestamp = strtotime($last_modified);

    if ( ( $client_last_modified && $client_etag )
    ? ( ( $client_modified_timestamp >= $modified_timestamp) && ( $client_etag == $etag ) )
    : ( ( $client_modified_timestamp >= $modified_timestamp) || ( $client_etag == $etag ) )
    ) {
       status_header( 304 );
       exit;
    }

    // If we made it this far, just serve the file
    readfile( $file );

?>
