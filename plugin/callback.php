<?php
/**
 * @package   repository_res
 * @copyright 2017, Elliot Smith <elliot.smith@bbc.co.uk>
 * @license   Apache v2 - http://www.apache.org/licenses/LICENSE-2.0
 */

// extract and decode querystring
if (!isset($_GET['media'])) {
    die('media parameter must be set');
}

$selected = json_decode($_GET['media']);

$uri = $selected->uri;
$label = $selected->label;

$thumbnail = '';
if (property_exists($selected, 'thumbnail')) {
    $thumbnail = $selected->thumbnail;
}

$date = '';
if (property_exists($selected, 'date')) {
    $date = $selected->date;
}

$html =<<<HTML
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <script type="text/javascript">
    window.onload = function() {
        var resource = {};
        resource.source = "$uri";
        resource.title = "$label";
        resource.thumbnail = "$thumbnail";
        resource.datecreated = "$date";
        resource.author = "";
        resource.license = "";
        parent.M.core_filepicker.select_file(resource);
    }
    </script>
</head>
<body><noscript></noscript></body>
</html>
HTML;

// Output the generated javascript
header('Content-Type: text/html; charset=utf-8');
echo $html;
?>
