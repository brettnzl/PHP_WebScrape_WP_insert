<?php 
$urlstoCrawl = array(
    'https://anaesthesia.nz/advocacy/news/presidents-blog/',
    'https://anaesthesia.nz/advocacy/news/mental-ill-health-how-do-we-better-support-our-colleagues/'
);


function upload_migrate_image($image_url) {

    $arraycontextOptions = array(
        "ssl"=>array(
            "verify_peer"=>false,
            "verify_peer_name"=>false,
        ),   
    );
        
    $upload_dir = wp_upload_dir();

    $image_data = file_get_contents( $image_url , false, stream_context_create($arraycontextOptions));

    $filename = basename( $image_url );

    if ( wp_mkdir_p( $upload_dir['path'] ) ) {
        $file = $upload_dir['path'] . '/' . $filename;
    }

    else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    file_put_contents( $file, $image_data );

    $wp_filetype = wp_check_filetype( $filename, null );

    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name( $filename ),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    $attach_id = wp_insert_attachment( $attachment, $file );
    
    require_once( ABSPATH . 'wp-admin/includes/image.php' );
    
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
    wp_update_attachment_metadata( $attach_id, $attach_data );

    //Return the new uploaded image 
    return $attach_id;
}

if (isset($_GET['import']) && $_GET['import'] == 'true') {

    foreach($urlstoCrawl as $url) {

        $arraycontextOptions = array(
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),   
        );

        $html = file_get_contents($url, false, stream_context_create($arraycontextOptions));

        //article summary
        $start = stripos($html, '<div class="reusableSummary__pageSummary">');
        $end = stripos($html, '</div>', $offset = $start);
        $length = $end - $start;
        $htmlSection = substr($html, $start, $length);
        $article['summary'] = strip_tags($htmlSection,"<p><h2><h3><h4>");

        // Title 
        $start = stripos($html, '<h1 class="title">');
        $end = stripos($html, '</h1>', $offset = $start);
        $length = $end - $start;
        $htmlSection = substr($html, $start, $length);
        $article['title'] = strip_tags($htmlSection,"<p><h2><h3><h4>");

        // featureImage <img class="featureImage" src="

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML( $html );
        libxml_clear_errors();
        $imageTags = $doc->getElementsByTagName('img');

        foreach($imageTags as $tag) {
            if ($tag->getAttribute('class') == 'featureImage') {
                $article['featured_image'][] = $tag->getAttribute('src');
            }
        }


        // <div class="content-element__content">
        $start = stripos($html, '<div class="content-element__content">');
        $end = stripos($html, '</div>', $offset = $start);
        $length = $end - $start;
        $htmlSection = substr($html, $start, $length);
        $article['content'] = strip_tags($htmlSection, "<p><h2><h3><h4>");

        echo "<pre>";
        print_r($article);

        $args = array(
            'post_type' => 'news',
            'post_title' => $article['title'],
            'post_excerpt' =>$article['summary'],
            'post_content' => $article['content']
        );

        $newpost = wp_insert_post($args);

        if (!empty($article['featured_image'][0])) {
            $image_id = upload_migrate_image('https://anaesthesia.nz'.$article['featured_image'][0]);
            set_post_thumbnail( $newpost, $image_id );
        }

        // Reset Images
        unset($article['featured_image']);

    }
    
    exit();
}
?>
