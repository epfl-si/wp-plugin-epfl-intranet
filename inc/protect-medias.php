<?php
    require_once('/wp/6/wp-load.php');

    $upload_dir = wp_upload_dir();

    $epfl_intranet_plugin_full_path = 'epfl-intranet/epfl-intranet.php';
    if (!is_user_logged_in())
    {
       // Specific authorization for Search Inside crawler
       if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
          $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
          if (str_starts_with($authHeader, 'Basic ')) {
             $encodedCredentials = substr($authHeader, 6);
             $decodedCredentials = base64_decode($encodedCredentials);
             if (password_verify($decodedCredentials, getenv('SEARCH_INSIDE_WP_API_HASHED_TOKEN'))){
                $search_crawler_authenticated = true;
             }
          }
       }
       if (!isset($search_crawler_authenticated)) {
          $file = str_replace($_SERVER["WP_ROOT_URI"] . 'wp-content/uploads', '', $_SERVER['REQUEST_URI']);
          wp_redirect( wp_login_url( $upload_dir['baseurl'] . $file));
          exit();
       }
    }

    list($basedir) = array_values(array_intersect_key(wp_upload_dir(), array('basedir' => 1)))+array(NULL);
    $file = str_replace($_SERVER["WP_ROOT_URI"] . 'wp-content/uploads', $upload_dir["basedir"], urldecode($_SERVER['REQUEST_URI']));
    if ( (strpos($file, "/../") !== FALSE) ||
         (strpos($file, "../") === 0) )
    {
       status_header(403);
       die('403 — Try harder');
    }

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


    header( 'Content-Type: ' . $mimetype );
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
