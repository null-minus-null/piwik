<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Piwik
 */
namespace Piwik;

use Exception;
use JSMin;
use lessc;
use Piwik\Translate;

/**
 * @see libs/jsmin/jsmin.php
 */
require_once PIWIK_INCLUDE_PATH . '/libs/jsmin/jsmin.php';

/**
 * AssetManager is the class used to manage the inclusion of UI assets:
 * JavaScript and CSS files.
 *
 * It performs the following actions:
 *  - Identifies required assets
 *  - Includes assets in the rendered HTML page
 *  - Manages asset merging and minifying
 *  - Manages server-side cache
 *
 * Whether assets are included individually or as merged files is defined by
 * the global option 'disable_merged_assets'. When set to 1, files will be
 * included individually.
 * When set to 0, files will be included within a pair of files: 1 JavaScript
 * and 1 css file.
 *
 * @package Piwik
 */
class AssetManager
{
    const MERGED_CSS_FILE = "asset_manager_global_css.css";
    const MERGED_JS_FILE = "asset_manager_global_js.js";
    const STYLESHEET_IMPORT_EVENT = "AssetManager.getStylesheetFiles";
    const JAVASCRIPT_IMPORT_EVENT = "AssetManager.getJavaScriptFiles";
    const MERGED_FILE_DIR = "tmp/assets/";
    const COMPRESSED_FILE_LOCATION = "/tmp/assets/";

    const CSS_IMPORT_DIRECTIVE = "<link rel=\"stylesheet\" type=\"text/css\" href=\"%s\" />\n";
    const JS_IMPORT_DIRECTIVE = "<script type=\"text/javascript\" src=\"%s\"></script>\n";
    const GET_CSS_MODULE_ACTION = "index.php?module=Proxy&action=getCss";
    const GET_JS_MODULE_ACTION = "index.php?module=Proxy&action=getJs";
    const MINIFIED_JS_RATIO = 100;

    /**
     * @param $file
     * @param $less
     * @internal param $mergedContent
     * @return string
     */
    protected static function getCssContentFromFile($file, $less)
    {
        self::validateCssFile($file);

        $fileLocation = self::getAbsoluteLocation($file);
        $less->addImportDir(dirname($fileLocation));

        $content = file_get_contents($fileLocation);
        $content = self::rewriteCssPathsDirectives($file, $content);

        return $content;
    }

    /**
     * Returns CSS file inclusion directive(s) using the markup <link>
     *
     * @return string
     */
    public static function getCssAssets()
    {
        return sprintf(self::CSS_IMPORT_DIRECTIVE, self::GET_CSS_MODULE_ACTION);
    }

    /**
     * Returns JS file inclusion directive(s) using the markup <script>
     *
     * @return string
     */
    public static function getJsAssets()
    {
        $result = "<script type=\"text/javascript\">\n" . Translate::getJavascriptTranslations() . "\n</script>";

        if (self::isMergedAssetsDisabled()) {
            // Individual includes mode
            self::removeMergedAsset(self::MERGED_JS_FILE);
            $result .= self::getIndividualJsIncludes();
        } else {
            $result .= sprintf(self::JS_IMPORT_DIRECTIVE, self::GET_JS_MODULE_ACTION);
        }

        return $result;
    }

    /**
     * Assets are cached in the browser and Piwik server returns 304 after initial download.
     * when the Cache buster string changes, the assets will be re-generated
     *
     * @return string
     */
    public static function generateAssetsCacheBuster()
    {
        $pluginList = md5(implode(",", \Piwik\Plugin\Manager::getInstance()->getLoadedPluginsName()));
        $cacheBuster = md5(SettingsPiwik::getSalt() . $pluginList . PHP_VERSION . Version::VERSION);
        return $cacheBuster;
    }

    /**
     * Generate the merged css file.
     *
     * @throws Exception if a file can not be opened in write mode
     */
    private static function prepareMergedCssFile()
    {
        $mergedCssAlreadyGenerated = self::isGenerated(self::MERGED_CSS_FILE);
        $isDevelopingPiwik = self::isMergedAssetsDisabled();

        if ($mergedCssAlreadyGenerated && !$isDevelopingPiwik) {
            return;
        }

        $files = self::getStylesheetFiles();
        $less = self::makeLess();

        // Loop through each css file
        $mergedContent = "";
        foreach ($files as $file) {
            $mergedContent .= self::getCssContentFromFile($file, $less, $mergedContent);
        }

        $fileHash = md5($mergedContent);
        $firstLineCompileHash = "/* compile_me_once=$fileHash */";

        // Disable Merged Assets ==> Check on each request if file needs re-compiling
        if ($mergedCssAlreadyGenerated
            && !$isDevelopingPiwik
        ) {
            $pathMerged = self::getAbsoluteMergedFileLocation(self::MERGED_CSS_FILE);
            $f = fopen($pathMerged, 'r');
            $firstLine = fgets($f);
            fclose($f);
            if (!empty($firstLine)
                && trim($firstLine) == trim($firstLineCompileHash)
            ) {
                return;
            }
            // Some CSS file in the merge, has changed since last merged asset was generated
            // Note: we do not detect changes in @import'ed LESS files
        }

        $mergedContent = $less->compile($mergedContent);

        /**
         * This event is triggered after the less stylesheets are compiled to CSS and after the CSS is minified and
         * merged into one file but before the generated CSS is written to disk. It can be used to change the modify the
         * stylesheets to your needs, like replacing image paths or adding further custom stylesheets.
         */
        Piwik::postEvent('AssetManager.filterMergedStylesheets', array(&$mergedContent));

        $mergedContent =
            $firstLineCompileHash . "\n"
            . "/* Piwik CSS file is compiled with Less. You may be interested in writing a custom Theme for Piwik! */\n"
            . $mergedContent;

        self::writeAssetToFile($mergedContent, self::MERGED_CSS_FILE);
    }

    protected static function makeLess()
    {
        if (!class_exists("lessc")) {
            throw new Exception("Less was added to composer during 2.0. ==> Execute this command to update composer packages: \$ php composer.phar update");
        }
        $less = new lessc;
        return $less;
    }

    /**
     * Returns the base.less compiled to css
     *
     * @return string
     */
    public static function getCompiledBaseCss()
    {
        $file = '/plugins/Zeitgeist/stylesheets/base.less';
        $less = self::makeLess();
        $lessContent = self::getCssContentFromFile($file, $less);
        $css = $less->compile($lessContent);
        return $css;
    }

    /*
     * Rewrite css url directives
     * - rewrites relative paths
     *  - rewrite windows directory separator \\ to /
     */
    protected static function rewriteCssPathsDirectives($relativePath, $content)
    {
        static $rootDirectoryLength = null;
        if (is_null($rootDirectoryLength)) {
            $rootDirectoryLength = self::countDirectoriesInPathToRoot();
        }

        $baseDirectory = dirname($relativePath);
        $content = preg_replace_callback(
            "/(url\(['\"]?)([^'\")]*)/",
            function ($matches) use ($rootDirectoryLength, $baseDirectory) {
                $absolutePath = substr(realpath(PIWIK_DOCUMENT_ROOT . "/$baseDirectory/" . $matches[2]), $rootDirectoryLength);
                $rewritten = str_replace('\\', '/', $absolutePath);

                if (is_file($rewritten)) { // only rewrite the URL if transforming it points to a valid file
                    return $matches[1] . $rewritten;
                } else {
                    return $matches[1] . $matches[2];
                }
            },
            $content
        );
        return $content;
    }

    protected static function countDirectoriesInPathToRoot()
    {
        $rootDirectory = realpath(PIWIK_DOCUMENT_ROOT);
        if ($rootDirectory != '/' && substr_compare($rootDirectory, '/', -1)) {
            $rootDirectory .= '/';
        }
        $rootDirectoryLen = strlen($rootDirectory);
        return $rootDirectoryLen;
    }

    private static function writeAssetToFile($mergedContent, $name)
    {
        // Remove the previous file
        self::removeMergedAsset($name);

        // Tries to open the new file
        $newFilePath = self::getAbsoluteMergedFileLocation($name);
        $newFile = @fopen($newFilePath, "w");

        if (!$newFile) {
            throw new Exception ("The file : " . $newFile . " can not be opened in write mode.");
        }

        // Write the content in the new file
        fwrite($newFile, $mergedContent);
        fclose($newFile);
    }

    /**
     * Returns individual CSS file inclusion directive(s) using the markup <link>
     *
     * @return string
     */
    private static function getIndividualCssIncludes()
    {
        $cssIncludeString = '';

        $stylesheets = self::getStylesheetFiles();

        foreach ($stylesheets as $cssFile) {

            self::validateCssFile($cssFile);
            $cssIncludeString = $cssIncludeString . sprintf(self::CSS_IMPORT_DIRECTIVE, $cssFile);
        }

        return $cssIncludeString;
    }

    /**
     * Returns required CSS files
     *
     * @return Array
     */
    private static function getStylesheetFiles()
    {
        $stylesheets = array();

        /**
         * This event is triggered to gather a list of all stylesheets (CSS and LESS). Use this event to add your own
         * stylesheets. Note: In case you are in development you may enable the config setting `disable_merged_assets`.
         * Otherwise your custom stylesheets won't be loaded. It is best practice to place stylesheets within a
         * `stylesheets` folder.
         *
         * Example:
         * ```
         * public function getStylesheetFiles(&$stylesheets)
         * {
         *     $stylesheets[] = "plugins/MyPlugin/stylesheets/myfile.less";
         *     $stylesheets[] = "plugins/MyPlugin/stylesheets/myfile.css";
         * }
         * ```
         */
        Piwik::postEvent(self::STYLESHEET_IMPORT_EVENT, array(&$stylesheets));

        $stylesheets = self::sortCssFiles($stylesheets);

        // We look for the currently enabled theme and add CSS from the json
        $theme = \Piwik\Plugin\Manager::getInstance()->getThemeEnabled();
        if ($theme && $theme->getPluginName() != \Piwik\Plugin\Manager::DEFAULT_THEME) {
            $info = $theme->getInformation();
            if (isset($info['stylesheet'])) {
                $themeStylesheetFile = 'plugins/' . $theme->getPluginName() . '/' . $info['stylesheet'];
            }
            $stylesheets[] = $themeStylesheetFile;
        }
        return $stylesheets;
    }

    /**
     * Ensure CSS stylesheets are loaded in a particular order regardless of the order that plugins are loaded.
     *
     * @param array $stylesheets Array of CSS stylesheet files
     * @return array
     */
    private static function sortCssFiles($stylesheets)
    {
        $priorityCssOrdered = array(
            'libs/',
            'plugins/CoreHome/stylesheets/color_manager.css', // must be before other Piwik stylesheets
            'plugins/Zeitgeist/stylesheets/base.less',
            'plugins/Zeitgeist/stylesheets/',
            'plugins/',
            'plugins/Dashboard/stylesheets/dashboard.less',
            'tests/',
        );

        return self::prioritySort($priorityCssOrdered, $stylesheets);
    }

    /**
     * Check the validity of the css file
     *
     * @param string $cssFile CSS file name
     * @return boolean
     * @throws Exception if a file can not be opened in write mode
     */
    private static function validateCssFile($cssFile)
    {
        if (!self::assetIsReadable($cssFile)) {
            throw new Exception("The css asset with 'href' = " . $cssFile . " is not readable");
        }
    }

    /**
     * Generate the merged js file.
     *
     * @throws Exception if a file can not be opened in write mode
     */
    private static function generateMergedJsFile()
    {
        $mergedContent = "";

        // Loop through each js file
        $files = self::getJsFiles();
        foreach ($files as $file) {

            self::validateJsFile($file);

            $fileLocation = self::getAbsoluteLocation($file);
            $content = file_get_contents($fileLocation);

            if (!self::isMinifiedJs($content)) {
                $content = JSMin::minify($content);
            }

            $mergedContent = $mergedContent . PHP_EOL . $content;
        }
        $mergedContent = str_replace("\n", "\r\n", $mergedContent);

        /**
         * This event is triggered after the JavaScript files are minified and merged to a single file but before the
         * generated JS file is written to disk. It can be used to change the generated JavaScript to your needs,
         * like adding further scripts or storing the generated file somewhere else.
         */
        Piwik::postEvent('AssetManager.filterMergedJavaScripts', array(&$mergedContent));

        self::writeAssetToFile($mergedContent, self::MERGED_JS_FILE);
    }

    /**
     * Returns individual JS file inclusion directive(s) using the markup <script>
     *
     * @return string
     */
    private static function getIndividualJsIncludes()
    {
        $jsIncludeString = '';

        $jsFiles = self::getJsFiles();
        foreach ($jsFiles as $jsFile) {
            self::validateJsFile($jsFile);
            $jsIncludeString = $jsIncludeString . sprintf(self::JS_IMPORT_DIRECTIVE, $jsFile);
        }
        return $jsIncludeString;
    }

    /**
     * Returns required JS files
     *
     * @return Array
     */
    private static function getJsFiles()
    {
        $jsFiles = array();

        /**
         * This event is triggered to gather a list of all JavaScript files. Use this event to add your own JavaScript
         * files. Note: In case you are in development you may enable the config setting `disable_merged_assets`.
         * Otherwise your custom JavaScript won't be loaded. It is best practice to place all your JavaScript files
         * within a `javascripts` folder.
         *
         * Example:
         * ```
         * public function getJsFiles(&jsFiles)
         * {
         *     jsFiles[] = "plugins/MyPlugin/javascripts/myfile.js";
         *     jsFiles[] = "plugins/MyPlugin/javascripts/anotherone.js";
         * }
         * ```
         */
        Piwik::postEvent(self::JAVASCRIPT_IMPORT_EVENT, array(&$jsFiles));
        $jsFiles = self::sortJsFiles($jsFiles);
        return $jsFiles;
    }

    /**
     * Ensure core JS (jQuery etc.) are loaded in a particular order regardless of the order that plugins are loaded.
     *
     * @param array $jsFiles Arry of JavaScript files
     * @return array
     */
    private static function sortJsFiles($jsFiles)
    {
        $priorityJsOrdered = array(
            'libs/jquery/jquery.js',
            'libs/jquery/jquery-ui.js',
            'libs/jquery/jquery.browser.js',
            'libs/',
            'plugins/Zeitgeist/javascripts/piwikHelper.js',
            'plugins/Zeitgeist/javascripts/',
            'plugins/CoreHome/javascripts/broadcast.js',
            'plugins/',
            'tests/',
        );

        return self::prioritySort($priorityJsOrdered, $jsFiles);
    }

    /**
     * Check the validity of the js file
     *
     * @param string $jsFile JavaScript file name
     * @return boolean
     * @throws Exception if js file is not valid
     */
    private static function validateJsFile($jsFile)
    {
        if (!self::assetIsReadable($jsFile)) {
            throw new Exception("The js asset with 'src' = " . $jsFile . " is not readable");
        }
    }

    /**
     * Returns the global option disable_merged_assets
     *
     * @return string
     */
    public static function isMergedAssetsDisabled()
    {
        return Config::getInstance()->Debug['disable_merged_assets'];
    }

    /**
     * Returns the css merged file absolute location.
     * If there is none, the generation process will be triggered.
     *
     * @return string The absolute location of the css merged file
     */
    public static function getMergedCssFileLocation()
    {
        self::prepareMergedCssFile();
        return self::getAbsoluteMergedFileLocation(self::MERGED_CSS_FILE);
    }

    /**
     * Returns the js merged file absolute location.
     * If there is none, the generation process will be triggered.
     *
     * @return string The absolute location of the js merged file
     */
    public static function getMergedJsFileLocation()
    {
        $isGenerated = self::isGenerated(self::MERGED_JS_FILE);

        if (!$isGenerated) {
            self::generateMergedJsFile();
        }

        return self::getAbsoluteMergedFileLocation(self::MERGED_JS_FILE);
    }

    /**
     * Check if the provided merged file is generated
     *
     * @param string $filename filename of the merged asset
     * @return boolean true is file exists and is readable, false otherwise
     */
    private static function isGenerated($filename)
    {
        return is_readable(self::getAbsoluteMergedFileLocation($filename));
    }

    /**
     * Removes the previous merged file if it exists.
     * Also tries to remove compressed version of the merged file.
     *
     * @param string $filename filename of the merged asset
     * @see ProxyStaticFile::serveStaticFile(serveFile
     * @throws Exception if the file couldn't be deleted
     */
    private static function removeMergedAsset($filename)
    {
        $isGenerated = self::isGenerated($filename);

        if ($isGenerated) {
            if (!unlink(self::getAbsoluteMergedFileLocation($filename))) {
                throw new Exception("Unable to delete merged file : " . $filename . ". Please delete the file and refresh");
            }

            // Tries to remove compressed version of the merged file.
            // See ProxyHttp::serverStaticFile() for more info on static file compression
            $compressedFileLocation = PIWIK_USER_PATH . self::COMPRESSED_FILE_LOCATION . $filename;
            $compressedFileLocation = SettingsPiwik::rewriteTmpPathWithHostname($compressedFileLocation);

            @unlink($compressedFileLocation . ".deflate");
            @unlink($compressedFileLocation . ".gz");
        }
    }

    /**
     * Remove previous merged assets
     */
    public static function removeMergedAssets()
    {
        self::removeMergedAsset(self::MERGED_CSS_FILE);
        self::removeMergedAsset(self::MERGED_JS_FILE);
    }

    /**
     * Check if asset is readable
     *
     * @param string $relativePath Relative path to file
     * @return boolean
     */
    private static function assetIsReadable($relativePath)
    {
        return is_readable(self::getAbsoluteLocation($relativePath));
    }

    /**
     * Check if the merged file directory exists and is writable.
     *
     * @return string The directory location
     * @throws Exception if directory is not writable.
     */
    private static function getMergedFileDirectory()
    {
        $mergedFileDirectory = PIWIK_USER_PATH . '/' . self::MERGED_FILE_DIR;
        $mergedFileDirectory = SettingsPiwik::rewriteTmpPathWithHostname($mergedFileDirectory);

        if (!is_dir($mergedFileDirectory)) {
            Filesystem::mkdir($mergedFileDirectory);
        }

        if (!is_writable($mergedFileDirectory)) {
            throw new Exception("Directory " . $mergedFileDirectory . " has to be writable.");
        }

        return $mergedFileDirectory;
    }

    /**
     * Builds the absolute location of the requested merged file
     *
     * @param string $mergedFile Name of the merge file
     * @return string absolute location of the merged file
     */
    private static function getAbsoluteMergedFileLocation($mergedFile)
    {
        return self::getMergedFileDirectory() . $mergedFile;
    }

    /**
     * Returns the full path of an asset file
     *
     * @param string $relativePath Relative path to file
     * @return string
     */
    private static function getAbsoluteLocation($relativePath)
    {
        // served by web server directly, so must be a public path
        return PIWIK_DOCUMENT_ROOT . "/" . $relativePath;
    }

    /**
     * Indicates if the provided JavaScript content has already been minified or not.
     * The heuristic is based on a custom ratio : (size of file) / (number of lines).
     * The threshold (100) has been found empirically on existing files :
     * - the ratio never exceeds 50 for non-minified content and
     * - it never goes under 150 for minified content.
     *
     * @param string $content Contents of the JavaScript file
     * @return boolean
     */
    private static function isMinifiedJs($content)
    {
        $lineCount = substr_count($content, "\n");
        if ($lineCount == 0) {
            return true;
        }

        $contentSize = strlen($content);

        $ratio = $contentSize / $lineCount;

        return $ratio > self::MINIFIED_JS_RATIO;
    }

    /**
     * Sort files according to priority order. Duplicates are also removed.
     *
     * @param array $priorityOrder Ordered array of paths (first to last) serving as buckets
     * @param array $files Unsorted array of files
     * @return array
     */
    public static function prioritySort($priorityOrder, $files)
    {
        $newFiles = array();
        foreach ($priorityOrder as $filePattern) {
            $newFiles = array_merge($newFiles, preg_grep('~^' . $filePattern . '~', $files));
        }
        return array_keys(array_flip($newFiles));
    }
}
