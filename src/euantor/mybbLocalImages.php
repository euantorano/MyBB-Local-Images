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
define('DB_NAME', 'mybb');
define('DB_USER_NAME', 'root');
define('DB_USER_PASS', '');
define('DB_PREFIX', 'mybb_');

// Change this to reflect the base of your MyBB install. By default, this file is expected to be in the ./inc/ directory
define('IMAGE_STORAGE_PATH', '../images/auto-uploads/');

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

    // Get external file
    $imgFile = file_get_contents((string) $matches['url'][0]);
    $fileName = pathinfo($matches['url'][0], PATHINFO_FILENAME).'.'.pathinfo($matches['url'][0], PATHINFO_EXTENSION);
    $storedFile = date('Y-m-d',time()).'/'.$fileName;
    file_put_contents(IMAGE_STORAGE_PATH.$storedFile, $imgFile);

    $newMessage = str_replace($matches['wholestring'], "[img]".$storedFile."[/img]", $result->message);

    $query = $link->prepare("UPDATE ". DB_PREFIX ."posts SET message = :message WHERE pid = :pid");
    $query->bindParam(':message', $newMessage);
    $query->bindParam(':pid', (int) $result->pid);
    $query->execute();

    ++$recordsAffected;
}

echo 'Affected record count: '.$recordsAffected;

$link = null;
