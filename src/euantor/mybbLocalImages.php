<?php
/**
 * PHP system to fetch remote images posted in MyBB forums and store them locally
 *
 * @category  Tools
 * @package   MyBB-Local-Images
 * @author    Euan T. <euan@euantor.com>
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 * @version   1.00
 */

// Change these lines to suit your database connection
define('DB_HOST', 'localhost');
define('DB_NAME', '');
define('DB_USER_NAME', '');
define('DB_USER_PASS', '');
define('DB_PREFIX', 'mybb_');

define('INCLUDE_SRC', true); // Change this to false if you do not want to add the original source of the image

// Change this to reflect the base of your MyBB install. By default, this file is expected to be in the ./inc/ directory
define('IMAGE_STORAGE_PATH', '/var/www/mybb1609/images/auto_uploads/'); // Direct path to uplaod folder - default example provided
define('BASE_URL', 'http://localhost/mybb1609/'); // Base URL, with ending slash - example provided
define('IMAGE_STORED_URL', BASE_URL.'images/auto_uploads/'); // The URL the image will be located at

// Default timezone is UTC. Not too much point changing unless you really want to
date_default_timezone_set('UTC');

// Stop editing past here
$link = null;
try {
  $link = new PDO('mysql:host='. DB_HOST .';dbname='.DB_NAME, DB_USER_NAME, DB_USER_PASS);
}
catch(PDOException $e) {
    die($e->getMessage());
}

$timeLimit = strtotime('-1 day', time());
$statement = $link->prepare("SELECT pid, message FROM ". DB_PREFIX ."posts WHERE dateline >= :time");
$statement->bindParam(':time', $timeLimit);
$statement->setFetchMode(PDO::FETCH_OBJ);
$statement->execute();

$recordsAffected = 0;

while ($result = $statement->fetch()) {
    // Match for basic [img] tags
    preg_match_all("#(?P<wholestring>\[img\](\r\n?|\n?)(?P<url>https?://([^<>\"']+?))\[/img\])#ise", $result->message, $matches);

    // No match? Let's skip this loop around
    if (empty($matches) OR !is_array($matches) OR !isset($matches['wholestring']) OR !isset($matches['url'])) {
        continue;
    }

    $i = 0;
    foreach ($matches['url'] as $match) {
    	// Do not run for local images
    	$length_url = strlen(BASE_URL);
    	if (substr($match, 0, $length_url) == BASE_URL) {
    		continue;
    	}

        // Get external file
        $imgFile = file_get_contents((string) $match);

        if (!checkImageFile($imgFile)) {
            continue;
        }

        $fileName = pathinfo($match, PATHINFO_FILENAME).'-'.time().'.'.pathinfo($match, PATHINFO_EXTENSION);
        if (!is_dir(IMAGE_STORAGE_PATH.date('Y-m-d',time()))) {
        	if (!mkdir(IMAGE_STORAGE_PATH.date('Y-m-d',time()), 0700, true)) {
        		die('Could not create directory');
        	}
        }
        $storedFile = date('Y-m-d',time()).'/'.$fileName;
        file_put_contents(IMAGE_STORAGE_PATH.$storedFile, $imgFile);

        $sourceString = '';
        if ((boolean) INCLUDE_SRC) {
            $sourceString = "\n[i][b]Source:[/b] {$match}[/i]";
        }

        $result->message = str_replace($matches['wholestring'][$i], "[img]".IMAGE_STORED_URL.$storedFile."[/img]{$sourceString}", $result->message);
        ++$i;
    }

    $query = $link->prepare("UPDATE ". DB_PREFIX ."posts SET message = :message WHERE pid = :pid");
    $query->bindParam(':message', $result->message);
    $query->bindParam(':pid', $result->pid);
    $query->execute();

    ++$recordsAffected;
}

echo 'Affected record count: '.$recordsAffected;

$link = null;

/**
 * Check if a file is an image or not.
 *
 * @param string $path The path to the file.
 * @return boolean Whether the file is an image.
 */
function checkImageFile($imgPath)
{
    $img = getimagesize($path);
    $imageType = $img[2];

    if (in_array($imageType , array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP))) {
        return true;
    }
    return false;
}
