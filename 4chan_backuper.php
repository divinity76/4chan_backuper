<?php
declare (strict_types = 1);
// change to if(0) to allow fpm/apache/etc modes..
if (1) {
    if (php_sapi_name() !== 'cli') {
        die("for security reasons, only cli mode is allowed.");
    }
}
init();
$save_path = ''; // will be set by get_url()
$url = get_url();
$ch = curl_init();
curl_setopt_array($ch, array(
    CURLOPT_ENCODING => '',
    CURLOPT_USERAGENT => '4chan_backuper_php; libcurl/' . (curl_version()['version']) . ' php/' . PHP_VERSION,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_FOLLOWLOCATION => 1,
    CURLOPT_AUTOREFERER => 1,
));
$html = fetch($url);
m200();
$new_domd = my_dom_loader($html);
$new_xp = new DOMXPath($new_domd);
if (!file_exists($save_path . "index.html")) {
    //var_dump(base64_encode($new_domd->saveHTML()));
    $clone = my_dom_loader($new_domd->saveHTML());
    $clone_xp = new DOMXPath($clone);
    $should_be_removed = (int)($clone_xp->query("//div[@class='thread']")->item(0)->childNodes->length);
    $removed = 0;
    $clone_thread = $clone_xp->query("//div[@class='thread']")->item(0);
    while ($clone_thread->childNodes->length > 0) {
        ++$removed;
        $clone_thread->removeChild($clone_thread->childNodes->item(0));
    }
    if ($removed !== $should_be_removed) {
        throw new \LogicException("removed: {$removed} - should be removed: {$should_be_removed}");
    }
    file_put_contents($save_path . "index.html", $clone->saveHTML(), LOCK_EX);
    unset($clone, $clone_xp, $should_be_removed, $removed, $clone_thread);
}
$old_domd = my_dom_loader(file_get_contents($save_path . "index.html"));
$old_xp = new DOMXPath($old_domd);
$old_thread = $old_xp->query("//div[@class='thread']")->item(0);
while (true) {
    $new_posts = 0;
    $new_images = 0;
    foreach ($new_xp->query("//div[@class='thread']//div[contains(@class,'postContainer') and @id]") as $opost) {
        $id = $opost->getAttribute("id");
        assert(!empty($id));
        if ($old_xp->query('//div[@id=' . xpath_quote($id) . ']')->length > 0) {
            //already processed this post.
            continue;
        }
        /** @var DOMNode $post */
        $post = $old_domd->importNode($opost, true);
        $post = $old_thread->appendChild($post);
        ++$new_posts;
        $img = $post->getElementsByTagName("img");
        if ($img->length < 1) {
            continue;
        }
        $img = $img->item(0);
        $a = $old_xp->query(".//a[contains(@class,'fileThumb')]", $post);
        if ($a->length < 1) {
            continue;
        }
        $a = $a->item(0);
        if (false) {
            echo "\n\n\n";
            var_dump($old_domd->saveXML($a->parentNode));
            echo "\n\n\n";
        }
        $full_url = $a->getAttribute("href");
        $full_bname = basename($full_url);
        if (empty($full_url) || empty($full_bname)) {
            continue;
        }
        $thumb_url = $img->getAttribute("src");
        $thumb_bname = basename($thumb_url);
        if (empty($thumb_url) || empty($thumb_bname)) {
            continue;
        }
        ++$new_images;
        $thumb_binary = fetch($thumb_url);
        m200();
        $full_binary = fetch($full_url);
        m200();
        file_put_contents($save_path . "images" . DIRECTORY_SEPARATOR . "thumbnails" . DIRECTORY_SEPARATOR . $thumb_bname, $thumb_binary);
        file_put_contents($save_path . "images" . DIRECTORY_SEPARATOR . $full_bname, $full_binary);
        $a->setAttribute("href", "images/" . $full_bname);
        $img->setAttribute("src", "images/thumbnails/" . $thumb_bname);
        // saving here ease debugging, and as long as it's not performance-critical..
        file_put_contents($save_path . "index.html", $old_domd->saveHTML(), LOCK_EX);
    }
    if ($new_posts > 0 || $new_images > 0) {
        file_put_contents($save_path . "index.html", $old_domd->saveHTML(), LOCK_EX);
    }
    echo "new posts: {$new_posts} - new images: {$new_images}\n";
    $sleeptime = 10;
    echo "sleeping {$sleeptime} seconds and refetching..";
    sleep($sleeptime);
    echo ".\nfetching again.\n";
    $html = fetch($url);
    m200();
    $new_domd = my_dom_loader($html);
    $new_xp = new DOMXPath($new_domd);
}

function my_dom_loader(string $html): \DOMDocument
{
    $html = trim($html);
    if (empty($html)) {
        //....
    }
    if (false === stripos($html, '<?xml encoding=')) {
        $html = '<?xml encoding="UTF-8">' . $html;
    }
    $ret = new DOMDocument('', 'UTF-8');
    $ret->preserveWhiteSpace = false;
    $ret->formatOutput = true;
    if (!(@$ret->loadHTML($html, LIBXML_NOBLANKS | LIBXML_NONET | LIBXML_BIGLINES))) {
        throw new \Exception("failed to create DOMDocument from input html!");
    }
    $ret->preserveWhiteSpace = false;
    $ret->formatOutput = true;
    return $ret;
}
// "must be http 200"
function m200(): void
{
    global $ch;
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code !== 200) {
        $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        throw new \RuntimException("expected http \"200 OK\" but got {$code} - url: \"{$url}\"");
    }
}
function fetch(string $url, int &$code = null): string
{
    if (substr($url, 0, 2) === "//") {
        $url = "http:" . $url;
    }
    echo "fetching \"{$url}\"..";
    global $ch;
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
    ));
    $data = curl_exec($ch);
    if (curl_errno($ch) !== CURLE_OK) {
        throw new \RuntimeException("curl error: " . curl_errno($ch) . ": " . curl_error($ch));
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "http code \"{$code}\".";
    return $data;
}
function get_url(): string
{
    global $argv;
    global $save_path;
    if (isset($_REQUEST['url'])) {
        echo "got url from \$_REQUEST\n";
        $url = (string)$_REQUEST['url'];
    } elseif (!empty($argv) && count($argv) > 1) {
        echo "got url from \$argv\n";
        $url = $argv;
        unset($url[0]);
        $url = implode("", $url);
    } elseif (php_sapi_name() === 'cli') {
        //interactive mode
        stream_set_blocking(STDIN, true);
        echo "enter 4chan url: ";
        $url = fgets(STDIN);
    } else {
        throw new \RuntimeException("url not specified! (and not running interactively)");
    }
    $url = trim($url);
    if (!preg_match('/^(?:https?\:\/\/)?boards\.4chan\.org\/(?<board_name>.*?)\/.*?\/(?<thread_id>\d+)/', $url, $matches)) {
        throw new \RuntimeException("url \"{$url}\" does not look like a 4chan board url! - they are supposed to look something like http://boards.4chan.org/hc/thread/1501699#p1508333");
    }
    $board_name = $matches['board_name'];
    $thread_id = $matches['thread_id'];
    $url = "http://boards.4chan.org/{$board_name}/thread/{$thread_id}";
    echo "url parsed: \"{$url}\"\n";
    $save_path = getcwd() . DIRECTORY_SEPARATOR . "backups";
    mymkdir($save_path);
    $save_path .= DIRECTORY_SEPARATOR . $board_name;
    mymkdir($save_path);
    $save_path .= DIRECTORY_SEPARATOR . $thread_id;
    mymkdir($save_path);
    $save_path .= DIRECTORY_SEPARATOR;
    mymkdir($save_path . "images");
    mymkdir($save_path . "images" . DIRECTORY_SEPARATOR . "thumbnails");
    return $url;
}
function mymkdir(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    echo "making folder \"{$path}\"..";
    if (!mkdir($path)) {
        throw new \RuntimeException("ERROR: could not make folder!");
    }
    echo ". done.\n";
}
function init()
{
    static $firstrun = true;
    if ($firstrun !== true) {
        return;
    }
    $firstrun = false;
    error_reporting(E_ALL);
    set_error_handler("hhb_exception_error_handler");
    // ini_set("log_errors",'On');
    // ini_set("display_errors",'On');
    // ini_set("log_errors_max_len",'0');
    // ini_set("error_prepend_string",'<error>');
    // ini_set("error_append_string",'</error>'.PHP_EOL);
    // ini_set("error_log",__DIR__.DIRECTORY_SEPARATOR.'error_log.php.txt');
    assert_options(ASSERT_ACTIVE, 1);
    assert_options(ASSERT_WARNING, 0);
    assert_options(ASSERT_QUIET_EVAL, 1);
    assert_options(ASSERT_CALLBACK, 'hhb_assert_handler');
}
function hhb_exception_error_handler($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
function hhb_assert_handler($file, $line, $code, $desc = null)
{
    $errstr = 'Assertion failed at ' . $file . ':' . $line . ' ' . $desc . ' code: ' . $code;
    throw new ErrorException($errstr, 0, 1, $file, $line);
}
//based on https://stackoverflow.com/a/1352556/1067003
function xpath_quote(string $value): string
{
    if (false === strpos($value, '"')) {
        return '"' . $value . '"';
    }
    if (false === strpos($value, '\'')) {
        return '\'' . $value . '\'';
    }
    // if the value contains both single and double quotes, construct an
    // expression that concatenates all non-double-quote substrings with
    // the quotes, e.g.:
    //
    //    concat("'foo'", '"', "bar")
    $sb = 'concat(';
    $substrings = explode('"', $value);
    for ($i = 0; $i < count($substrings); ++$i) {
        $needComma = ($i > 0);
        if ($substrings[$i] !== '') {
            if ($i > 0) {
                $sb .= ', ';
            }
            $sb .= '"' . $substrings[$i] . '"';
            $needComma = true;
        }
        if ($i < (count($substrings) - 1)) {
            if ($needComma) {
                $sb .= ', ';
            }
            $sb .= "'\"'";
        }
    }
    $sb .= ')';
    return $sb;
}
