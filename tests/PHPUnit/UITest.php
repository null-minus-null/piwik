<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
use Piwik\Access;
use Piwik\AssetManager;
use Piwik\Date;
use Piwik\DbHelper;
use Piwik\Plugins\VisitsSummary\API;
use Piwik\Db;

abstract class UITest extends IntegrationTestCase
{
    const IMAGE_TYPE = 'png';
    const CAPTURE_PROGRAM = 'phantomjs';
    const SCREENSHOT_GROUP_SIZE = 25;
    const DEBUG_IMAGE_MAGICK_COMPARE = false;
    
    private static $recursiveProxyLinkNames = array('libs', 'plugins', 'tests');
    private static $imageMagickAvailable = false;

    public static function createAccessInstance()
    {
        Access::setSingletonInstance($access = new Test_Access_OverrideLogin());
        \Piwik\Piwik::postEvent('Request.initAuthenticationObject');
    }
    
    public static function setUpBeforeClass()
    {
        if (self::CAPTURE_PROGRAM == 'slimerjs'
            && !self::isSlimerJsAvailable()
        ) {
            self::markTestSkipped("slimerjs is not available, skipping UI integration tests. "
                                . "(install by downloading http://slimerjs.org/download.html)");
        } else if (self::CAPTURE_PROGRAM == 'phantomjs'
                   && !self::isPhantomJsAvailable()
        ) {
            self::markTestSkipped("phantomjs is not available, skipping UI integration tests. "
                                . "(install by downloading http://phantomjs.org/download.html)");
        }
        
        parent::setUpBeforeClass();

        DbHelper::createAnonymousUser();
        
        AssetManager::removeMergedAssets();
        
        // launch archiving so tests don't run out of time
        $date = Date::factory(static::$fixture->dateTime)->toString();
        API::getInstance()->get(static::$fixture->idSite, 'year', $date);

        // make sure processed & expected dirs exist
        self::makeDirsAndLinks();

        // run slimerjs/phantomjs w/ all urls so we only invoke it once per 25 entries (travis needs
        // there to be output)
        $urlsToTest = static::getUrlsForTesting();

        reset($urlsToTest);
        for ($i = 0; $i < count($urlsToTest); $i += self::SCREENSHOT_GROUP_SIZE) {
            $urls = array();
            for ($j = $i; $j != $i + self::SCREENSHOT_GROUP_SIZE && $j < count($urlsToTest); ++$j) {
                $currentTest = current($urlsToTest);

                if (count($currentTest) == 2) {
                    list($name, $urlQuery) = $currentTest;
                    $jsToTest = false;
                } else {
                    list($name, $urlQuery, $jsToTest) = $currentTest;
                }

                list($processedScreenshotPath, $expectedScreenshotPath) = self::getProcessedAndExpectedScreenshotPaths($name);
                $urls[] = array($processedScreenshotPath, self::getProxyUrl() . $urlQuery, $jsToTest);

                next($urlsToTest);
            }
            
            echo "Generating screenshots...\n";
            self::runCaptureProgram($urls);
        }

        // check if image magick available
        self::$imageMagickAvailable = self::checkImageMagickAvailable();

        // remove existing diffs
        self::removeExistingDiffs();
    }

    public static function removeExistingDiffs()
    {
        $files = glob(dirname(__FILE__) . '/UI/screenshot-diffs/*.png');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    public static function tearDownAfterClass()
    {
        if (file_exists("C:\\nppdf32Log\\debuglog.txt")) { // remove slimerjs oddity
            unlink("C:\\nppdf32Log\\debuglog.txt");
        }

        self::removeRecursiveLinks();

        Db::createDatabaseObject();

        self::outputDiffViewerHtmlFile();
        
        parent::tearDownAfterClass();
    }
    
    public function setUp()
    {
        parent::setUp();
        
        if (!Db::get()) {
            DbHelper::createDatabaseObject();
        }
    }
    
    public function tearDown()
    {
        parent::tearDown();
        
        Db::get()->closeConnection();
    }
    
    private static function runCaptureProgram($urlInfo)
    {
        file_put_contents(PIWIK_INCLUDE_PATH . '/tmp/urls.txt', json_encode($urlInfo));
        $cmd = self::CAPTURE_PROGRAM . " \"" . PIWIK_INCLUDE_PATH . "/tests/resources/screenshot-capture/capture.js\" 2>&1";
        
        exec($cmd, $output, $result);
        $output = implode("\n", $output);
        if ($result !== 0
            || strpos($output, "ERROR") !== false
        ) {
            echo self::CAPTURE_PROGRAM . " failed: " . $output . "\n\ncommand used: $cmd\n";
            throw new Exception("phantomjs failed");
        }
        return $output;
    }

    protected function compareScreenshots($testsInfo)
    {
        $failures = array();
        foreach ($testsInfo as $info) {
            list($name, $urlQuery) = $info;

            // compare processed w/ expected
            try {
                $this->compareScreenshot($name, $urlQuery);
            } catch (Exception $ex) {
                $failures[] = $ex;
            }
        }

        if (!empty($failures)) {
            $diffViewerPath = self::getScreenshotDiffDir() . '/diffviewer.html';
            echo "\nFailures encountered. View all diffs at:
$diffViewerPath

If processed screenshots are correct, you can copy the generated screenshots to the expected screenshot folder.

*** IMPORTANT *** In your commit message, explain the cause of the difference in rendering so other Piwik developers will be aware of it.";

            throw reset($failures);
        }
    }
    
    protected function compareScreenshot($name, $urlQuery)
    {
        list($processedPath, $expectedPath) = self::getProcessedAndExpectedScreenshotPaths($name);

        $processed = file_get_contents($processedPath);
        
        if (!file_exists($expectedPath)) {
            $this->fail("expected screenshot for '$name' test is missing.
Generated screenshot: $processedPath");
            return;
        }
        
        $expected = file_get_contents($expectedPath);
        if ($expected != $processed) {
            $diffPath = self::getScreenshotDiffPath($name);

            echo "\nFail: generated screenshot does not match expected for '$name'.
Url to reproduce: $urlQuery
Generated screenshot: $processedPath
Expected screenshot: $expectedPath
Screenshot diff: $diffPath\n";

            $this->saveImageDiff($expectedPath, $processedPath, $diffPath);
        }
        $this->assertTrue($expected == $processed, "screenshot compare failed for '$processedPath'");
    }

    private function saveImageDiff($expectedPath, $processedPath, $diffPath)
    {
        $cmd = "compare \"$expectedPath\" \"$processedPath\" \"$diffPath\" 2>&1";
        exec($cmd, $output, $result);

        if (self::DEBUG_IMAGE_MAGICK_COMPARE) {
            echo "Could not save image diff: " . implode("\n", $output) . "\n";
        }
    }

    private static function checkImageMagickAvailable()
    {
        return self::isProgramAvailable('compare');
    }
    
    private static function isSlimerJsAvailable()
    {
        return self::isProgramAvailable('slimerjs');
    }

    private static function isPhantomJsAvailable()
    {
        return self::isProgramAvailable('phantomjs');
    }
    
    private static function isProgramAvailable($name)
    {
        exec($name . ' --help 2>&1', $output, $result);
        return $result === 0 || $result === 1;
    }

    private static function getProcessedAndExpectedScreenshotPaths($name)
    {
        list($processedDir, $expectedDir) = self::getProcessedAndExpectedDirs();

        $outputPrefix = static::getOutputPrefix();

        $processedScreenshotPath = $processedDir . $outputPrefix . '_' . "$name." . self::IMAGE_TYPE;
        $expectedScreenshotPath = $expectedDir . $outputPrefix . '_' . "$name." . self::IMAGE_TYPE;

        return array($processedScreenshotPath, $expectedScreenshotPath);
    }
    
    protected static function getProcessedAndExpectedDirs()
    {
        $path = self::getPathToTestDirectory() . '/../UI';
        return array($path . '/processed-ui-screenshots/', $path . '/expected-ui-screenshots/');
    }
    
    public static function getProxyUrl()
    {
        return Test_Piwik_BaseFixture::getRootUrl() . 'tests/PHPUnit/proxy/index.php';
    }

    private static function makeDirsAndLinks()
    {
        $dirs = array_merge(self::getProcessedAndExpectedDirs(), array(
            PIWIK_INCLUDE_PATH . '/tmp/sessions', self::getScreenshotDiffDir()
        ));
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir);
            }
        }

        foreach (self::$recursiveProxyLinkNames as $linkName) {
            $linkPath = PIWIK_INCLUDE_PATH . '/tests/PHPUnit/proxy/' . $linkName;
            if (!file_exists($linkPath)) {
                symlink(PIWIK_INCLUDE_PATH . '/' . $linkName, $linkPath);
            }
        }
    }

    private static function removeRecursiveLinks()
    {
        foreach (self::$recursiveProxyLinkNames as $linkName) {
            $wholePath = PIWIK_INCLUDE_PATH . '/tests/PHPUnit/proxy/' . $linkName;
            if (file_exists($wholePath)) {
                unlink($wholePath);
            }
        }
    }

    private static function getScreenshotDiffPath($name)
    {
        $outputPrefix = static::getOutputPrefix();
        $diffDir = self::getScreenshotDiffDir();
        return $diffDir . "/" . $outputPrefix . '_' . $name . '.' . self::IMAGE_TYPE;
    }

    private static function getScreenshotDiffDir()
    {
        return dirname(__FILE__) . "/UI/screenshot-diffs";
    }

    private static function outputDiffViewerHtmlFile()
    {
        $diffDir = self::getScreenshotDiffDir();

        $diffFiles = array();
        foreach (scandir($diffDir) as $file) {
            if (strpos($file, ".png") != strlen($file) - 4) continue;

            $parts = explode('.', $file, 2);
            $name = reset($parts);

            $diffFiles[] = array(
                'name' => $name,
                'expectedUrl' => 'https://raw.github.com/piwik/piwik-ui-tests/master/expected-ui-screenshots/' . $file,
                'processedUrl' => '../processed-ui-screenshots/' . $file,
                'diffUrl' => $file
            );
        }

        $diffViewerHtml = '<html>
<head></head>
<body>
<h1>Screenshot Test Failures</h1>
<table>
    <tr>
        <th>Name</th>
        <th>Expected</th>
        <th>Processed</th>
        <th>Difference</th>
    </tr>';
        foreach ($diffFiles as $fileInfo) {
            $diffViewerHtml .= '
    <tr>
        <td>' . $fileInfo['name'] . '</td>
        <td><a href="' . $fileInfo['expectedUrl'] . '">Expected</a></td>
        <td><a href="' . $fileInfo['processedUrl'] . '">Processed</a></td>
        <td><a href="' . $fileInfo['diffUrl'] . '">Difference</a></td>
    </tr>';
        }
        $diffViewerHtml .= '
</table>
</body>
</html>';
        
        file_put_contents($diffDir . '/diffviewer.html', $diffViewerHtml);
    }
}