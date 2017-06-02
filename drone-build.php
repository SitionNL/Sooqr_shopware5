<?php

if( !getenv('DRONE') ) {
	echo 'Command is not executed by drone!'
	exit(1);
}

if( !file_exists(__DIR__ . '/.drone.yml') ) {
	echo ".drone.yml doesn't exist"
	exit(1);
}

if( !file_exists(__DIR__ . '/../../IMAGE_VERSION') ) {
	echo "IMAGE_VERSION file doesn't exist"
	exit(1);
}

$matrixTag = 'IMAGE_VERSION:';


// get the matrix builds

$droneYml = file_get_contents(__DIR__ . '/.drone.yml');

$begin = stripos($droneYml, $matrixTag) + strlen($matrixTag);
$end = stripos($droneYml, "\n", $begin);

$tags = preg_split('/\s+/', substr($droneYml, $begin, $end));
$tags = array_filter($tags, function($tag) { return $tag !== '-'; });


// get the builds that ran
$versions = preg_split('/\s+/', file_get_contents(__DIR__ . '/../../IMAGE_VERSION'));

echo "tags\n"
print_r($tags);


echo "versions\n"
print_r($versions);
